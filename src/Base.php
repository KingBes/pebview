<?php

// 严格模式
declare(strict_types=1);

namespace Kingbes\PebView;

/**
 * 抽象类 Base
 *
 * 每个平台只有一个 PebView 动态库（窗口 / 对话框 / 通知都在其中），
 * 所有类共用同一个 FFI 实例。
 */
abstract class Base
{
    /**
     * FFI 实例
     */
    private static ?\FFI $ffi = null;

    /**
     * 获取 FFI 实例
     */
    public static function ffi(): \FFI
    {
        if (self::$ffi === null) {
            self::getFFI();
        }
        return self::$ffi;
    }

    /**
     * 按当前平台加载 PebView 动态库
     *
     * lib/<系统>/<架构>/PebView.<扩展名>
     * - Windows: x86_64
     * - Linux:   x86_64 / aarch64
     * - macOS:   x86_64 / arm64
     *
     * @throws \RuntimeException 平台不受支持或动态库不存在时抛出
     */
    protected static function getFFI(): void
    {
        $root = dirname(__DIR__);
        $machine = strtolower(php_uname('m'));
        $isArm = str_contains($machine, 'arm64') || str_contains($machine, 'aarch64');

        switch (PHP_OS_FAMILY) {
            case 'Windows':
                // 目前只提供 x86_64 产物，arm64 系统由系统仿真运行
                $dir = 'windows' . DIRECTORY_SEPARATOR . 'x86_64';
                $file = 'PebView.dll';
                break;
            case 'Linux':
                $dir = 'linux' . DIRECTORY_SEPARATOR . ($isArm ? 'aarch64' : 'x86_64');
                $file = 'PebView.so';
                break;
            case 'Darwin':
                $dir = 'macos' . DIRECTORY_SEPARATOR . ($isArm ? 'arm64' : 'x86_64');
                $file = 'PebView.dylib';
                break;
            default:
                throw new \RuntimeException(
                    "不支持的操作系统: " . PHP_OS_FAMILY . ": " . PHP_OS . "\n"
                        . "PebView 目前仅支持 Windows、Linux 和 macOS 操作系统。"
                );
        }

        $lib = $root . DIRECTORY_SEPARATOR . "lib"
            . DIRECTORY_SEPARATOR . $dir
            . DIRECTORY_SEPARATOR . $file;

        if (!is_file($lib)) {
            throw new \RuntimeException(
                "PebView 动态库不存在: {$lib}\n"
                    . "请先执行对应平台的构建脚本：source/build.cmd（Windows）、"
                    . "source/linux.sh（Linux）、source/macos.sh（macOS）。"
            );
        }

        $header = $root . DIRECTORY_SEPARATOR . "include" . DIRECTORY_SEPARATOR . "PebView.h";

        self::$ffi = \FFI::cdef(file_get_contents($header), $lib);
    }
}
