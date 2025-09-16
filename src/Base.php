<?php

// 严格模式
declare(strict_types=1);

namespace Kingbes\PebView;

/**
 * 抽象类 Base
 */
abstract class Base
{
    // private \FFI $ffi;
    private static \FFI $ffi;

    /**
     * 获取 FFI 实例
     *
     * @return \FFI
     * @throws RuntimeException Missing Raylib dependencies.
     */
    public static function ffi(): \FFI
    {
        if (!isset(self::$ffi)) {
            $headerPath = __DIR__ . '/PebView.h';
            $dllPath = self::getLibFilePath();

            $libHeader = file_get_contents($headerPath);
            self::$ffi = \FFI::cdef($libHeader, $dllPath);
        }
        return self::$ffi;
    }

    /**
     * 获取 Raylib 库文件的路径
     *
     * 此方法根据当前操作系统的类型返回相应的 Raylib 库文件路径。
     * 支持 Windows 和 Linux 操作系统，若使用其他操作系统将抛出异常。
     *
     * @return string 包含 Raylib 库文件的完整路径
     * @throws \RuntimeException 如果当前操作系统不被支持
     */
    protected static function getLibFilePath(): string
    {
        // 判断当前系统是windows还是linux
        if (PHP_OS_FAMILY === 'Windows') {
            // 返回 Windows 系统下的 PebView 动态链接库文件路径
            return dirname(__DIR__) . DIRECTORY_SEPARATOR
                . 'build' . DIRECTORY_SEPARATOR 
                . 'lib' . DIRECTORY_SEPARATOR 
                . 'windows' . DIRECTORY_SEPARATOR 
                . 'PebView.dll';
        } else if (PHP_OS_FAMILY === 'Linux') {
            // 判断架构是否为x86_64
            if (PHP_INT_SIZE === 8) {
                $arch = 'x86_64';
            }
            // 判断架构是否为arrch64
            else if (PHP_INT_SIZE === 4) {
                $arch = 'aarch64';
            }
            // 若架构未知，抛出异常
            else {
                throw new \RuntimeException("Unsupported architecture: " . PHP_INT_SIZE . " bits");
            }
            // 返回 Linux 系统下的 PebView 共享库文件路径
            return dirname(__DIR__) . DIRECTORY_SEPARATOR
                . 'build' . DIRECTORY_SEPARATOR 
                . 'lib' . DIRECTORY_SEPARATOR 
                . 'linux' . DIRECTORY_SEPARATOR 
                . $arch . DIRECTORY_SEPARATOR 
                . 'libPebView.so';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            // 判断架构是否为x86_64
            if (PHP_INT_SIZE === 8) {
                $arch = 'x86_64';
            }
            // 判断架构是否为arm64
            else if (PHP_INT_SIZE === 4) {
                $arch = 'arm64';
            }
            // 若架构未知，抛出异常
            else {
                throw new \RuntimeException("Unsupported architecture: " . PHP_INT_SIZE . " bits");
            }
            // 返回 macOS 系统下的 PebView 共享库文件路径
            return dirname(__DIR__) . DIRECTORY_SEPARATOR
                . 'build' . DIRECTORY_SEPARATOR 
                . 'lib' . DIRECTORY_SEPARATOR 
                . 'macos' . DIRECTORY_SEPARATOR 
                . $arch . DIRECTORY_SEPARATOR 
                . 'libPebView.dylib';
        } else {
            // 若当前操作系统不被支持，抛出异常
            throw new \RuntimeException("Unsupported operating system: " . PHP_OS_FAMILY . ": " . PHP_OS . "");
        }
    }
}
