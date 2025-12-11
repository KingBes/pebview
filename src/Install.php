<?php

namespace Kingbes\PebView;

use Composer\Script\Event;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = array(
        'config/plugin/kingbes/pebview' => 'config/plugin/kingbes/pebview',
    );

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        static::installByRelation();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        self::uninstallByRelation();
    }

    /**
     * installByRelation
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parent_dir = base_path() . '/' . substr($dest, 0, $pos);
                if (!is_dir($parent_dir)) {
                    mkdir($parent_dir, 0777, true);
                }
            }
            //symlink(__DIR__ . "/$source", base_path()."/$dest");
            copy_dir(__DIR__ . "/$source", base_path() . "/$dest");
            echo "Create $dest
";
        }
    }

    /**
     * uninstallByRelation
     * @return void
     */
    public static function uninstallByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            $path = base_path() . "/$dest";
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            echo "Remove $dest
";
            if (is_file($path) || is_link($path)) {
                unlink($path);
                continue;
            }
            remove_dir($path);
        }
    }

    public static function fileDownload(Event $event)
    {
        $composer = $event->getComposer();
        $io = $event->getIO();
        // 询问是否安装 动态库文件
        $answer = strtolower($io->ask("Should the pebview dynamic library file be installed? (y/n):\n", "y"));
        if ($answer === 'n') {
            return;
        }
        $io->write("... download dynamic library file ...");

        // 获取当前库的版本
        $package = $composer->getPackage();
        $version = $package->getVersion(); // 获取当前库的版本
        $io->write("Current pebview version: {$version}");
        // 获取当前库链接
        $url = $package->getDistUrl();
        $io->write("Current pebview url: {$url}");
        // 当前系统
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $os = 'windows';
                break;
            case 'Linux':
                $os = 'linux';
                break;
            case 'Darwin':
                $os = 'macos';
                break;
            default:
                // 其他系统不支持
                throw new \RuntimeException("Unsupported operating system: " . PHP_OS_FAMILY);
                return;
        }
        // 架构 x86_64还是arm64架构
        $arch = PHP_INT_SIZE === 8 ? 'x86_64' : 'aarch64';
        // 下载链接
        $downloadUrl = "https://github.com/KingBes/pebview/releases/download/{$version}/{$os}-{$arch}.zip";
        // 下载文件
        $io->write("... download $downloadUrl ...");
        // 下载文件
        
    }
}
