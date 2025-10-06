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
     * 库文件路径缓存
     * 
     * @var string|null
     */
    private static ?string $libFilePathCache = null;

    /**
     * 获取 PebView 库文件的路径
     *
     * 此方法根据当前操作系统的类型和架构返回相应的 PebView 库文件路径。
     * 支持 Windows、Linux 和 macOS 操作系统，若使用其他操作系统将抛出异常。
     * 
     * @return string 包含 PebView 库文件的完整路径
     * @throws \RuntimeException 如果当前操作系统或架构不被支持
     */
    protected static function getLibFilePath(): string
    {
        // 使用缓存避免重复检测
        if (self::$libFilePathCache !== null) {
            return self::$libFilePathCache;
        }

        // 定义基础路径变量
        $baseLibPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR;

        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $path = $baseLibPath . 'windows' . DIRECTORY_SEPARATOR . 'PebView.dll';
                break;

            case 'Linux':
                $arch = self::detectArchitecture();
                $path = $baseLibPath . 'linux' . DIRECTORY_SEPARATOR . $arch . DIRECTORY_SEPARATOR . 'PebView.so';
                break;

            case 'Darwin':
                $arch = self::detectArchitecture('macos');
                $path = $baseLibPath . 'macos' . DIRECTORY_SEPARATOR . $arch . DIRECTORY_SEPARATOR . 'PebView.dylib';
                break;

            default:
                throw new \RuntimeException(
                    "不支持的操作系统: " . PHP_OS_FAMILY . ": " . PHP_OS . "\n"
                        . "PebView 目前仅支持 Windows、Linux 和 macOS 操作系统。"
                );
        }

        // 缓存结果
        self::$libFilePathCache = $path;
        return $path;
    }

    /**
      * 检测当前系统架构
      * 
      * @param string $osType 操作系统类型，用于处理特定系统的架构命名差异
      * @return string 系统架构标识符
      * @throws \RuntimeException 如果架构不被支持
      */
     private static function detectArchitecture(string $osType = ''): string
     {
         // 对于 Windows 系统，只需要检查 x86_64 架构
         if (PHP_OS_FAMILY === 'Windows') {
             return 'x86_64';
         }
         
         // 对于 Linux 和 macOS 系统，需要更精确的架构检测
         // 优先使用 php_uname 获取更准确的架构信息
         $machine = php_uname('m');
         $architecture = strtolower($machine);
          
         // 处理常见架构命名
         if (str_contains($architecture, 'x86_64') || str_contains($architecture, 'amd64')) {
             return 'x86_64';
         } 
         
         // Linux 系统检查 aarch64 架构
         if (PHP_OS_FAMILY === 'Linux' && (str_contains($architecture, 'aarch64') || str_contains($architecture, 'arm64'))) {
             return 'aarch64';
         } 
         
         // macOS 系统检查 arm64 架构
         if (PHP_OS_FAMILY === 'Darwin' && (str_contains($architecture, 'arm64') || str_contains($architecture, 'aarch64'))) {
             return 'arm64';
         }
          
         // 后备方案：使用 PHP_INT_SIZE 判断
         if (PHP_INT_SIZE === 8) {
             return 'x86_64';
         } 
         
         // Linux 系统下 32 位架构默认为 aarch64
         if (PHP_OS_FAMILY === 'Linux' && PHP_INT_SIZE === 4) {
             return 'aarch64';
         }
          
         // macOS 系统下 32 位架构默认为 arm64
         if (PHP_OS_FAMILY === 'Darwin' && PHP_INT_SIZE === 4) {
             return 'arm64';
         }
          
         // 根据操作系统类型提供具体的错误信息
         if (PHP_OS_FAMILY === 'Linux') {
             throw new \RuntimeException(
                 "不支持的 Linux 架构: " . $machine . " (" . PHP_INT_SIZE . " bits)\n"
                     . "PebView 目前仅支持 x86_64 和 aarch64 架构。"
             );
         } elseif (PHP_OS_FAMILY === 'Darwin') {
             throw new \RuntimeException(
                 "不支持的 macOS 架构: " . $machine . " (" . PHP_INT_SIZE . " bits)\n"
                     . "PebView 目前仅支持 x86_64 和 arm64 架构。"
             );
         } else {
             throw new \RuntimeException(
                 "不支持的系统架构: " . $machine . " (" . PHP_INT_SIZE . " bits)"
             );
         }
     }
}
