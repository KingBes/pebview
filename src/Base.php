<?php

// 严格模式
declare(strict_types=1);

namespace Kingbes\PebView;

/**
 * 抽象类 Base
 */
abstract class Base
{
    /**
     * FFI 实例
     *
     * @var array<string, \FFI>
     */
    private static array $ffi;

    /**
     * 获取 FFI 实例
     *
     * @return array<string, \FFI>
     */
    public static function ffi(): array
    {
        if (!isset(self::$ffi)) {
            self::getFFI();
        }
        return self::$ffi;
    }

    protected static function getFFI(): void
    {
        $headerPath = dirname(__DIR__)
            . DIRECTORY_SEPARATOR . "include"
            . DIRECTORY_SEPARATOR;
        $libPath = dirname(__DIR__)
            . DIRECTORY_SEPARATOR . "lib"
            . DIRECTORY_SEPARATOR;
        $files = glob($headerPath . "*.h");
        $arch = PHP_INT_SIZE === 8 ? 'x86_64' : 'aarch64';
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                foreach ($files as $file) {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                    $lib = $libPath . "windows" . DIRECTORY_SEPARATOR . $arch . DIRECTORY_SEPARATOR . $name . ".dll";
                    self::$ffi[$name] = \FFI::cdef(file_get_contents($file), $lib);
                }
                break;
            case 'Linux':
                foreach ($files as $file) {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                    $lib = $libPath . "linux" . DIRECTORY_SEPARATOR . $arch . DIRECTORY_SEPARATOR . $name . ".so";
                    self::$ffi[$name] = \FFI::cdef(file_get_contents($file), $lib);
                }
                break;

            case 'Darwin':
                foreach ($files as $file) {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                    $lib = $libPath . "macos" . DIRECTORY_SEPARATOR . $arch . DIRECTORY_SEPARATOR . $name . ".dylib";
                    self::$ffi[$name] = \FFI::cdef(file_get_contents($file), $lib);
                }
                break;
            default:
                throw new \RuntimeException(
                    "不支持的操作系统: " . PHP_OS_FAMILY . ": " . PHP_OS . "\n"
                        . "PebView 目前仅支持 Windows、Linux 和 macOS 操作系统。"
                );
        }
    }
}
