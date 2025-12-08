<?php

namespace Kingbes\PebView;

use \FFI\CData;

class Toast
{
    private \FFI $ffi;
    private CData $instance;
    private CData $template_ptr;

    public function __construct()
    {
        [$dll_path, $head_path] = $this->verification();
        $this->ffi = \FFI::cdef(file_get_contents($head_path), $dll_path);
        $this->instance = $this->ffi->toastCreate();
    }

    public function setAppName(string $app_name): self
    {
        $this->ffi->toastSetAppName($this->instance, $app_name);
        return $this;
    }

    public function setAppUserModelId(string $name, string $app_user_model_id, string $version): self
    {
        $this->ffi->toastSetAppUserModelId($this->instance, $name, $app_user_model_id, $version);
        return $this;
    }

    public function initialize(): bool
    {
        return $this->ffi->toastInitialize($this->instance);
    }

    public function createTemplate(int $template_type): self
    {
        $this->template_ptr = $this->ffi->toastCreateTemplate($template_type);
        return $this;
    }

    public function setFirstLine(string $first_line): self
    {
        $this->ffi->toastSetFirstLine($this->template_ptr, $first_line);
        return $this;
    }
    public function setSecondLine(string $second_line): self
    {
        $this->ffi->toastSetSecondLine($this->template_ptr, $second_line);
        return $this;
    }
    public function setImagePath(string $image_path): self
    {
        $this->ffi->toastSetImagePath($this->template_ptr, $image_path);
        return $this;
    }
    
    public function show(): bool
    {
        return $this->ffi->toastShow($this->instance, $this->template_ptr);
    }

    /**
     * 验证操作系统类型并返回对应的 DLL 路径和头文件路径
     *
     * @return array
     */
    private function verification(): array
    {
        $base_dir =  dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib";

        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $dll_path = $base_dir . DIRECTORY_SEPARATOR . "windows" . DIRECTORY_SEPARATOR . "WinToast.dll";
                $head_path = $base_dir . DIRECTORY_SEPARATOR . "windows" . DIRECTORY_SEPARATOR . "WinToast.h";
                break;
            case 'Linux':
                $dll_path = __DIR__ . "/build/Release/WinToast.so";
                $head_path = $base_dir . DIRECTORY_SEPARATOR . "linux" . DIRECTORY_SEPARATOR . "WinToast.h";
                break;
            case 'Darwin':
                $dll_path = __DIR__ . "/build/Release/WinToast.dylib";
                $head_path = $base_dir . DIRECTORY_SEPARATOR . "darwin" . DIRECTORY_SEPARATOR . "WinToast.h";
                break;
            default:
                throw new \Exception("Unsupported operating system family");
        }
        return [$dll_path, $head_path];
    }
}
