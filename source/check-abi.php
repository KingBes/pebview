<?php

/**
 * 校验动态库是否导出完整的 PebView C ABI。
 *
 * 用法：
 *     php source/check-abi.php --def source/exports.def --lib lib/linux/x86_64/PebView.so
 *     source/build.cmd / linux.sh / macos.sh 会在构建结束后自动调用本脚本。
 *
 * 检查两件事：
 *
 * 1. ABI 完整性
 *    include/PebView.h 与 source/exports.def 里的每个函数都必须真的被导出。
 *    为什么必须查：Windows 上"没有导出、也没被内部引用"的函数会被链接器当死代码
 *    丢掉（曾漏掉 webview_create，DLL 从 646 KB 掉到 294 KB，功能已坏而链接不报错）；
 *    Linux/macOS 则是编译时漏了源文件也不会报错。
 *
 * 2. 漏编实现文件
 *    ELF / Mach-O 的共享库默认允许存在未解析符号。如果库里出现本项目命名空间
 *    （webview_ / osdialog_ / window_ / set_icon / toastShow）的 UND 符号，
 *    说明某个实现文件没参与编译 —— 例如 Linux 曾漏编 osdialog.c，
 *    导致 osdialog_filters_parse 缺失、osdialog_strdup 未解析。
 *
 * 只依赖 PHP 本身，直接解析 PE / ELF / Mach-O 的符号表，不需要 dumpbin 或 nm。
 *
 * 注意：本脚本打印的内容必须保持纯 ASCII。输出会同时进 CI 日志和 Windows cmd
 * 控制台，后者按 OEM 代码页解码，中文会变成乱码。
 */

declare(strict_types=1);

const MH_MAGICS = [
    0xFEEDFACE => ['big', 32],
    0xCEFAEDFE => ['little', 32],
    0xFEEDFACF => ['big', 64],
    0xCFFAEDFE => ['little', 64],
];

const LC_SYMTAB = 0x2;
const N_EXT = 0x01;
const N_TYPE = 0x0E;
const N_UNDF = 0x00;
const N_SECT = 0x0E;
const SHT_DYNSYM = 11;

/**
 * 二进制读取器：所有取值都带边界检查，遇到截断文件返回 0 而不是抛错。
 */
final class Bin
{
    private string $data;
    private bool $big;

    public function __construct(string $data, bool $bigEndian = false)
    {
        $this->data = $data;
        $this->big = $bigEndian;
    }

    /** 按大端读出前 4 字节，用于识别文件格式 */
    public static function magic(string $data): int
    {
        if (strlen($data) < 4) {
            return 0;
        }
        return (int) unpack('N', substr($data, 0, 4))[1];
    }

    public function u8(int $o): int
    {
        return $o >= 0 && $o < strlen($this->data) ? ord($this->data[$o]) : 0;
    }

    public function u16(int $o): int
    {
        if ($o < 0 || $o + 2 > strlen($this->data)) {
            return 0;
        }
        return (int) unpack($this->big ? 'n' : 'v', substr($this->data, $o, 2))[1];
    }

    public function u32(int $o): int
    {
        if ($o < 0 || $o + 4 > strlen($this->data)) {
            return 0;
        }
        return (int) unpack($this->big ? 'N' : 'V', substr($this->data, $o, 4))[1];
    }

    public function u64(int $o): int
    {
        if ($o < 0 || $o + 8 > strlen($this->data)) {
            return 0;
        }
        return (int) unpack($this->big ? 'J' : 'P', substr($this->data, $o, 8))[1];
    }
}

/**
 * 从缓冲区 offset 处读取一个 NUL 结尾字符串
 */
function cstr_at(string $buf, int $off): string
{
    if ($off < 0 || $off >= strlen($buf)) {
        return '';
    }
    $end = strpos($buf, "\0", $off);
    if ($end === false) {
        $end = strlen($buf);
    }
    return substr($buf, $off, $end - $off);
}

/**
 * 判断是否属于本项目自己的符号命名空间
 */
function is_own(string $name): bool
{
    foreach (['webview_', 'osdialog_', 'window_', 'set_icon', 'toastShow'] as $prefix) {
        if ($name === $prefix || str_starts_with($name, $prefix)) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------------------
// exports.def
// ---------------------------------------------------------------------------

/**
 * @return list<string>
 */
function parse_def(string $path): array
{
    $names = [];
    $inExports = false;
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException("cannot read def file: {$path}");
    }

    foreach ($lines as $raw) {
        $line = trim(explode(';', $raw, 2)[0]);
        if ($line === '') {
            continue;
        }
        if (!$inExports) {
            if (str_starts_with(strtoupper($line), 'EXPORTS')) {
                $inExports = true;
                $tail = trim(substr($line, strlen('EXPORTS')));
                if ($tail !== '') {
                    $names[] = explode(' ', $tail)[0];
                }
            }
            continue;
        }
        $names[] = explode(' ', $line)[0];
    }

    return $names;
}

// ---------------------------------------------------------------------------
// ELF
// ---------------------------------------------------------------------------

/**
 * @return array{0: list<string>, 1: list<string>}
 */
function parse_elf(string $data): array
{
    $big = ord($data[5]) === 2;
    $is64 = ord($data[4]) === 2;
    $bin = new Bin($data, $big);

    if ($is64) {
        $shoff = $bin->u64(0x28);
        $shEntSize = $bin->u16(0x3A);
        $shNum = $bin->u16(0x3C);
    } else {
        $shoff = $bin->u32(0x20);
        $shEntSize = $bin->u16(0x2E);
        $shNum = $bin->u16(0x30);
    }

    // type / offset / size / link / entsize 在 32 位与 64 位下的偏移不同
    $sections = [];
    for ($i = 0; $i < $shNum; $i++) {
        $o = $shoff + $i * $shEntSize;
        if ($is64) {
            $sections[] = [
                'type' => $bin->u32($o + 4),
                'off' => $bin->u64($o + 24),
                'size' => $bin->u64($o + 32),
                'link' => $bin->u32($o + 40),
                'ent' => $bin->u64($o + 56),
            ];
        } else {
            $sections[] = [
                'type' => $bin->u32($o + 4),
                'off' => $bin->u32($o + 16),
                'size' => $bin->u32($o + 20),
                'link' => $bin->u32($o + 24),
                'ent' => $bin->u32($o + 36),
            ];
        }
    }

    $defined = [];
    $undefined = [];
    foreach ($sections as $sec) {
        if ($sec['type'] !== SHT_DYNSYM) {
            continue;
        }
        $strSec = $sections[$sec['link']] ?? null;
        if ($strSec === null) {
            continue;
        }
        $strtab = substr($data, $strSec['off'], $strSec['size']);
        $entSize = $sec['ent'] ?: ($is64 ? 24 : 16);

        for ($pos = $sec['off']; $pos + $entSize <= $sec['off'] + $sec['size']; $pos += $entSize) {
            // st_name 都在 +0；st_shndx 在 64 位为 +6、32 位为 +14
            $name = cstr_at($strtab, $bin->u32($pos));
            if ($name === '') {
                continue;
            }
            $shndx = $is64 ? $bin->u16($pos + 6) : $bin->u16($pos + 14);
            if ($shndx === 0) {
                $undefined[$name] = true;
            } else {
                $defined[$name] = true;
            }
        }
    }

    return [array_keys($defined), array_keys($undefined)];
}

// ---------------------------------------------------------------------------
// PE
// ---------------------------------------------------------------------------

/**
 * @return array{0: list<string>, 1: list<string>}
 */
function parse_pe(string $data): array
{
    $bin = new Bin($data, false);
    $peOff = $bin->u32(0x3C);
    if (substr($data, $peOff, 4) !== "PE\0\0") {
        throw new RuntimeException('not a valid PE file');
    }

    // COFF 头：NumberOfSections 在 +2，SizeOfOptionalHeader 在 +16
    $coff = $peOff + 4;
    $nsec = $bin->u16($coff + 2);
    $optSize = $bin->u16($coff + 16);
    $opt = $coff + 20;

    $magic = $bin->u16($opt);
    if ($magic !== 0x10B && $magic !== 0x20B) {
        throw new RuntimeException('unknown PE optional header');
    }

    $dataDir = $opt + ($magic === 0x20B ? 112 : 96);
    $expRva = $bin->u32($dataDir);
    if ($expRva === 0) {
        return [[], []];
    }

    $sections = [];
    $sh = $opt + $optSize;
    for ($i = 0; $i < $nsec; $i++) {
        $o = $sh + $i * 40;
        $vsize = $bin->u32($o + 8);
        $vaddr = $bin->u32($o + 12);
        $rawsize = $bin->u32($o + 16);
        $rawptr = $bin->u32($o + 20);
        $sections[] = [$vaddr, max($vsize, $rawsize), $rawptr];
    }

    $rva2off = static function (int $rva) use ($sections): ?int {
        foreach ($sections as [$vaddr, $vsize, $rawptr]) {
            if ($rva >= $vaddr && $rva < $vaddr + $vsize) {
                return $rawptr + ($rva - $vaddr);
            }
        }
        return null;
    };

    $expOff = $rva2off($expRva);
    if ($expOff === null) {
        return [[], []];
    }
    // IMAGE_EXPORT_DIRECTORY：NumberOfNames 在 +24，AddressOfNames 在 +32
    $nameCount = $bin->u32($expOff + 24);
    $namesRva = $bin->u32($expOff + 32);
    $namesOff = $rva2off($namesRva);
    if ($namesOff === null) {
        return [[], []];
    }

    $exported = [];
    for ($i = 0; $i < $nameCount; $i++) {
        $nameOff = $rva2off($bin->u32($namesOff + $i * 4));
        if ($nameOff === null) {
            continue;
        }
        $name = cstr_at($data, $nameOff);
        if ($name !== '') {
            $exported[$name] = true;
        }
    }

    // PE 的内部未解析符号会在链接期直接失败，产物里不存在这种状态
    return [array_keys($exported), []];
}

// ---------------------------------------------------------------------------
// Mach-O
// ---------------------------------------------------------------------------

/**
 * @return array{0: list<string>, 1: list<string>}
 */
function parse_macho(string $data, int $depth = 0): array
{
    $be = Bin::magic($data);

    // 通用（FAT）二进制：逐个 slice 合并
    if (($be === 0xCAFEBABE || $be === 0xCAFEBABF) && $depth === 0) {
        $fat = new Bin($data, true);
        $count = $fat->u32(4);
        $defined = [];
        $undefined = [];
        for ($i = 0; $i < $count; $i++) {
            $entry = 8 + $i * 20;
            $offset = $fat->u32($entry + 8);
            $size = $fat->u32($entry + 12);
            [$d, $u] = parse_macho(substr($data, $offset, $size), $depth + 1);
            foreach ($d as $name) {
                $defined[$name] = true;
            }
            foreach ($u as $name) {
                $undefined[$name] = true;
            }
        }
        return [array_keys($defined), array_keys($undefined)];
    }

    if (!isset(MH_MAGICS[$be])) {
        throw new RuntimeException('unknown Mach-O magic');
    }
    [$endian, $bits] = MH_MAGICS[$be];
    $bin = new Bin($data, $endian === 'big');
    $ncmds = $bin->u32(16);
    $lc = $bits === 64 ? 32 : 28;
    $nlistSize = $bits === 64 ? 16 : 12;

    $symoff = 0;
    $nsyms = 0;
    $stroff = 0;
    for ($i = 0; $i < $ncmds; $i++) {
        $cmd = $bin->u32($lc);
        $cmdsize = $bin->u32($lc + 4);
        if ($cmd === LC_SYMTAB) {
            $symoff = $bin->u32($lc + 8);
            $nsyms = $bin->u32($lc + 12);
            $stroff = $bin->u32($lc + 16);
            break;
        }
        if ($cmdsize <= 0) {
            break;
        }
        $lc += $cmdsize;
    }

    $strtab = substr($data, $stroff);
    $defined = [];
    $undefined = [];
    for ($i = 0; $i < $nsyms; $i++) {
        $o = $symoff + $i * $nlistSize;
        $nType = $bin->u8($o + 4);
        if (($nType & N_EXT) === 0) {
            continue;
        }
        // Mach-O 符号名带前导下划线
        $name = ltrim(cstr_at($strtab, $bin->u32($o)), '_');
        if ($name === '') {
            continue;
        }
        $type = $nType & N_TYPE;
        if ($type === N_UNDF) {
            $undefined[$name] = true;
        } elseif ($type === N_SECT) {
            $defined[$name] = true;
        }
    }

    return [array_keys($defined), array_keys($undefined)];
}

// ---------------------------------------------------------------------------

/**
 * @return array{0: string, 1: array{0: list<string>, 1: list<string>}}
 */
function parse_library(string $data): array
{
    if (str_starts_with($data, 'MZ')) {
        return ['PE', parse_pe($data)];
    }
    if (str_starts_with($data, "\x7fELF")) {
        return ['ELF', parse_elf($data)];
    }
    $magic = Bin::magic($data);
    if (isset(MH_MAGICS[$magic]) || $magic === 0xCAFEBABE || $magic === 0xCAFEBABF) {
        return ['Mach-O', parse_macho($data)];
    }
    throw new RuntimeException('unrecognised file format');
}

function usage(): void
{
    echo "usage: php check-abi.php --def <exports.def> --lib <library>\n";
}

function main(array $argv): int
{
    $defPath = null;
    $libPath = null;

    for ($i = 1, $n = count($argv); $i < $n; $i++) {
        $arg = $argv[$i];
        if ($arg === '--def' && isset($argv[$i + 1])) {
            $defPath = $argv[++$i];
        } elseif ($arg === '--lib' && isset($argv[$i + 1])) {
            $libPath = $argv[++$i];
        } elseif ($arg === '-h' || $arg === '--help') {
            usage();
            return 0;
        }
    }

    if ($defPath === null || $libPath === null) {
        usage();
        return 2;
    }

    if (PHP_INT_SIZE < 8) {
        echo "[ABI] ERROR: 64-bit PHP is required\n";
        return 2;
    }
    if (!is_file($defPath)) {
        echo "[ABI] ERROR: def file not found: {$defPath}\n";
        return 2;
    }
    if (!is_file($libPath)) {
        echo "[ABI] ERROR: library not found: {$libPath}\n";
        return 2;
    }

    $expected = parse_def($defPath);

    $data = file_get_contents($libPath);
    if ($data === false) {
        echo "[ABI] ERROR: cannot read library: {$libPath}\n";
        return 2;
    }

    [$format, [$defined, $undefined]] = parse_library($data);

    echo "[ABI] lib      : {$libPath}\n";
    echo "[ABI] format   : {$format}\n";
    echo '[ABI] defined  : ' . count($defined) . '   undefined: ' . count($undefined) . "\n";

    $definedMap = array_flip($defined);
    $missing = [];
    foreach ($expected as $name) {
        if (!isset($definedMap[$name])) {
            $missing[] = $name;
        }
    }

    $leaked = [];
    foreach ($undefined as $name) {
        if (is_own($name)) {
            $leaked[] = $name;
        }
    }
    sort($leaked);

    $ok = true;

    if ($missing === []) {
        echo '[ABI] exports  : ' . count($expected) . '/' . count($expected) . " present\n";
    } else {
        $ok = false;
        echo '[ABI] MISSING ' . count($missing) . ' of ' . count($expected) . ":\n";
        foreach ($missing as $name) {
            echo "[ABI]   MISS {$name}\n";
        }
    }

    if ($leaked !== []) {
        $ok = false;
        echo '[ABI] UNRESOLVED ' . count($leaked)
            . " own symbol(s) - a source file was probably not compiled:\n";
        foreach ($leaked as $name) {
            echo "[ABI]   UND  {$name}\n";
        }
    }

    echo $ok ? "[ABI] PASSED\n" : "[ABI] FAILED\n";

    return $ok ? 0 : 1;
}

try {
    exit(main($argv));
} catch (Throwable $e) {
    echo '[ABI] ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
