<?php

namespace Kingbes\PebView;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private IOInterface $io;
    private Composer $composer;

    // 插件激活时初始化
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    // 以下两个方法为插件接口必填（空实现即可）
    public function deactivate(Composer $composer, IOInterface $io) {}
    public function uninstall(Composer $composer, IOInterface $io) {}

    // 注册要监听的事件（安装/更新）
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackageUpdate',
        ];
    }

    // 包安装后触发（仅你的包安装时执行）
    public function onPackageInstall(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        // 精准匹配你的包名
        if ($package->getName() !== 'kingbes/pebview') {
            return;
        }
        $this->downloadFile();
    }

    // 包更新后触发（仅你的包更新时执行）
    public function onPackageUpdate(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        if ($package->getName() !== 'kingbes/pebview') {
            return;
        }
        $this->downloadFile();
    }

    public function downloadFile()
    {
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
                $this->io->write(PHP_EOL . '<error>不支持的操作系统：' . PHP_OS_FAMILY . '</error>' . PHP_EOL);
                return;
        }
        $arch = PHP_INT_SIZE === 8 ? 'x86_64' : 'aarch64';
        $url = "https://github.com/KingBes/pebview/releases/latest/download/$os-$arch.zip";
        // 下载文件
        $this->io->write(PHP_EOL . '<info>下载文件：' . $url . '</info>' . PHP_EOL);
        
        // 下载文件然后解压到lib目录
        $libDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . $os . DIRECTORY_SEPARATOR . $arch;
        if (!is_dir($libDir)) {
            // 递归创建目录
            mkdir($libDir, 0755, true);
        }
        
        // 临时文件路径
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "pebview-$os-$arch.zip";
        
        // 下载文件
        try {
            $this->io->write('<info>正在下载...</info>');
            $context = stream_context_create([
                'http' => [
                    'follow_location' => true,
                    'max_redirects' => 10,
                    'timeout' => 60,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            
            $fileContent = file_get_contents($url, false, $context);
            if ($fileContent === false) {
                $this->io->write('<error>下载失败，请到仓库自行下载或编译安装到目录：' . $libDir . '</error>');
                return;
            }
            
            if (file_put_contents($tempFile, $fileContent) === false) {
                throw new \Exception('保存文件失败');
            }
            
            $this->io->write('<info>下载完成</info>');
        } catch (\Exception $e) {
            $this->io->write('<error>下载错误：' . $e->getMessage() . '</error>');
            return;
        }
        
        // 解压文件
        try {
            $this->io->write('<info>正在解压到：' . $libDir . '</info>');
            $zip = new \ZipArchive();
            if ($zip->open($tempFile) !== true) {
                throw new \Exception('无法打开压缩文件');
            }
            
            // 解压所有文件到目标目录
            if (!$zip->extractTo($libDir)) {
                throw new \Exception('解压失败');
            }
            
            $zip->close();
            $this->io->write('<info>解压完成</info>');
        } catch (\Exception $e) {
            $this->io->write('<error>解压错误：' . $e->getMessage() . '</error>');
            return;
        } finally {
            // 删除临时文件
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
        $this->io->write('<info>文件已成功下载并解压到：' . $libDir . '</info>');
    }
}
