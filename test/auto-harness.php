<?php

// 严格模式
declare(strict_types=1);

namespace PebTest;

/**
 * PebView 自动测试组 —— 极简断言器（零第三方依赖）
 *
 * 本项目 composer.json 里没有 require-dev，也没引 PHPUnit，所以自带一套最小实现。
 * 三个刻意的设计选择：
 *
 *  1. check() 内部包 try/catch：单个用例断言失败不会中断整轮测试，
 *     失败详情收集起来在末尾统一汇总。
 *  2. skip() 的 $reason 是必填参数，省略不了 —— 跳过必须说明原因。
 *     这条是用来防"悄悄跳过、看起来全绿"的假通过，比少敲几个字重要。
 *  3. report() 返回退出码（0 = 全过 / 1 = 有失败），可直接被 shell 判断。
 */
final class AssertionFailed extends \Exception
{
}

/**
 * 用例内部想"跳过剩余断言"时抛这个（配合 Harness::skipIf()）。
 * 与 AssertionFailed 分开：跳过不是失败。
 */
final class Skipped extends \Exception
{
}

final class Harness
{
    /**
     * 两次窗口操作之间的强制间隔（微秒）。
     *
     * 原因：WebView2 是异步初始化的。本机实测连续创建/销毁窗口时，第 7 个左右会在
     * destroy() 里死锁 —— 环境回调还没回来，窗口就被销毁了。插入约 150ms 间隔后，
     * 15 个连续实例全部正常。
     *
     * 这是运行环境的限制（本机 WebView2 本身就不渲染页面），不是库的缺陷，
     * 所以在测试侧统一节流，而不是去改 src/。
     */
    private const SETTLE_US = 150000;

    /** @var string[] 通过的用例名 */
    private array $passed = [];

    /** @var array<int, array{0: string, 1: string}> [用例名, 失败详情] */
    private array $failed = [];

    /** @var array<int, array{0: string, 1: string}> [用例名, 跳过原因] */
    private array $skipped = [];

    private float $startedAt;

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    // ------------------------------------------------------------------ 分组

    public function section(string $label): void
    {
        echo "\n", $label, "\n";
    }

    // ------------------------------------------------------------------ 执行

    /**
     * 执行一个用例：断言失败或抛异常都记 FAIL，不影响后续用例。
     *
     * 每个用例结束后都会让出一点时间（见 SETTLE_US 的说明），
     * 否则连续创建/销毁窗口会触发 WebView2 的销毁死锁。
     */
    public function check(string $name, callable $fn): void
    {
        try {
            $fn();
            $this->passed[] = $name;
            echo "  PASS  {$name}\n";
        } catch (AssertionFailed $e) {
            $this->fail($name, $e->getMessage());
        } catch (Skipped $e) {
            $this->skip($name, $e->getMessage());
        } catch (\Throwable $e) {
            $this->fail($name, '意外异常 ' . get_class($e) . ': ' . $e->getMessage());
        } finally {
            usleep(self::SETTLE_US);
        }
    }

    /**
     * 在同一条用例内部需要连续操作多个窗口时，手动插入间隔。
     */
    public function settle(): void
    {
        usleep(self::SETTLE_US);
    }

    public function fail(string $name, string $detail): void
    {
        $this->failed[] = [$name, $detail];
        echo "  FAIL  {$name}\n";
        echo "        └ {$detail}\n";
    }

    /**
     * @param string $reason 必填。跳过而不写原因，等于把没验证的东西伪装成通过。
     */
    public function skip(string $name, string $reason): void
    {
        $this->skipped[] = [$name, $reason];
        echo "  SKIP  {$name}\n";
        echo "        └ {$reason}\n";
    }

    /**
     * 在 check() 内部使用：条件成立则跳过该用例的剩余断言。
     */
    public function skipIf(bool $condition, string $reason): void
    {
        if ($condition) {
            throw new Skipped($reason);
        }
    }

    // ------------------------------------------------------------------ 断言

    public function assertSame(mixed $expected, mixed $actual, string $note = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed(
                '期望 ' . $this->dump($expected) . '，实际 ' . $this->dump($actual) . $this->suffix($note)
            );
        }
    }

    public function assertTrue(bool $condition, string $note = ''): void
    {
        if ($condition !== true) {
            throw new AssertionFailed('期望 true，实际 false' . $this->suffix($note));
        }
    }

    public function assertFalse(bool $condition, string $note = ''): void
    {
        if ($condition !== false) {
            throw new AssertionFailed('期望 false，实际 true' . $this->suffix($note));
        }
    }

    public function assertNull(mixed $value, string $note = ''): void
    {
        if ($value !== null) {
            throw new AssertionFailed('期望 null，实际 ' . $this->dump($value) . $this->suffix($note));
        }
    }

    public function assertNotNull(mixed $value, string $note = ''): void
    {
        if ($value === null) {
            throw new AssertionFailed('期望非 null，实际是 null' . $this->suffix($note));
        }
    }

    public function assertInstanceOf(string $class, mixed $value, string $note = ''): void
    {
        if (!($value instanceof $class)) {
            throw new AssertionFailed(
                '期望 ' . $class . ' 的实例，实际 ' . get_debug_type($value) . $this->suffix($note)
            );
        }
    }

    /**
     * 断言 $fn 抛出指定异常；$msgContains 非 null 时还要求消息里含该子串。
     */
    public function assertThrows(
        string $exClass,
        callable $fn,
        ?string $msgContains = null,
        string $note = ''
    ): void {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!($e instanceof $exClass)) {
                throw new AssertionFailed(
                    '期望 ' . $exClass . '，实际抛出 ' . get_class($e) . ': ' . $e->getMessage()
                        . $this->suffix($note)
                );
            }
            if ($msgContains !== null && !str_contains($e->getMessage(), $msgContains)) {
                throw new AssertionFailed(
                    '异常类型对，但消息不含 "' . $msgContains . '"，实际: ' . $e->getMessage()
                        . $this->suffix($note)
                );
            }
            return;
        }
        throw new AssertionFailed('期望抛出 ' . $exClass . '，实际未抛出' . $this->suffix($note));
    }

    /**
     * 取私有 / 受保护方法的句柄，供白盒单测使用（静态与实例方法都可以）。
     *
     * 用到反射都是有原因的（各用例文件会写明）：那几个方法只在 C 桥回调里被调用，
     * 而本机 WebView2 不渲染页面、JS 触发不到回调，只能直接对纯函数做隔离单测。
     */
    public function reflect(string $class, string $method): \ReflectionMethod
    {
        $ref = new \ReflectionMethod($class, $method);
        $ref->setAccessible(true);
        return $ref;
    }

    // ------------------------------------------------------------------ FFI 前置检查

    /**
     * FFI 是否可用。返回 null 表示可用，否则返回不可用的原因。
     *
     * 区分两层：扩展没开（环境问题，好解决）与动态库加载失败（真问题）。
     * 两者的跳过原因不同，排查时能一眼看出是哪种。
     */
    public function ffiLoadError(): ?string
    {
        if (!extension_loaded('ffi')) {
            return 'FFI 扩展未启用。需要这样运行：php -d extension=ffi -d ffi.enable=1 test/auto.php';
        }
        try {
            \Kingbes\PebView\Base::ffi();
            return null;
        } catch (\Throwable $e) {
            return 'PebView 动态库加载失败: ' . $e->getMessage();
        }
    }

    // ------------------------------------------------------------------ 汇总

    /**
     * @return array{pass: int, fail: int, skip: int}
     */
    public function counts(): array
    {
        return [
            'pass' => count($this->passed),
            'fail' => count($this->failed),
            'skip' => count($this->skipped),
        ];
    }

    /**
     * @return int 退出码：0 = 全部通过，1 = 有失败
     */
    public function report(): int
    {
        $total = count($this->passed) + count($this->failed) + count($this->skipped);

        echo "\n", str_repeat('=', 52), "\n";
        printf(
            "用例 %d | 通过 %d | 失败 %d | 跳过 %d | 耗时 %.2fs\n",
            $total,
            count($this->passed),
            count($this->failed),
            count($this->skipped),
            microtime(true) - $this->startedAt
        );

        if ($this->failed !== []) {
            echo "\n失败明细：\n";
            foreach ($this->failed as $i => [$name, $detail]) {
                printf("  %d. %s\n     %s\n", $i + 1, $name, $detail);
            }
        }

        if ($this->skipped !== []) {
            echo "\n跳过明细（这些点本轮并未验证）：\n";
            foreach ($this->skipped as $i => [$name, $reason]) {
                printf("  %d. %s\n     原因: %s\n", $i + 1, $name, $reason);
            }
        }

        $code = $this->failed === [] ? 0 : 1;
        echo "\n退出码: {$code}";
        echo $code === 0 ? "  （全部通过）\n" : "  （有失败）\n";

        return $code;
    }

    // ------------------------------------------------------------------ 内部工具

    private function suffix(string $note): string
    {
        return $note === '' ? '' : " | note: {$note}";
    }

    private function dump(mixed $value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        if (is_string($value) && strlen($value) > 60) {
            return var_export(substr($value, 0, 60) . '...', true);
        }
        return var_export($value, true);
    }
}
