<?php
/**
 * 修复版：不使用COM扩展读取Windows主题（适配REG_DWORD/REG_SZ解析）
 * 解决theme_path解析为0x0的问题，增加调试信息
 */
function getWindowsThemeWithoutCOM() {
    // 定义注册表查询命令（2>nul 屏蔽错误，chcp 65001 解决中文路径乱码）
    $commands = [
        'system_mode' => 'chcp 65001 >nul && reg query "HKCU\Software\Microsoft\Windows\CurrentVersion\Themes\Personalize" /v "SystemUsesLightTheme" 2>nul',
        'app_mode'    => 'chcp 65001 >nul && reg query "HKCU\Software\Microsoft\Windows\CurrentVersion\Themes\Personalize" /v "AppsUseLightTheme" 2>nul',
        'theme_path'  => 'chcp 65001 >nul && reg query "HKCU\Software\Microsoft\Windows\CurrentVersion\Themes" /v "CurrentTheme" 2>nul'
    ];

    $result = [];
    foreach ($commands as $key => $cmd) {
        exec($cmd, $output, $returnCode);
        $result[$key . '_raw_output'] = $output; // 保留原始输出（调试用）
        
        if ($returnCode !== 0) {
            $result[$key] = '未找到键值（Windows版本/权限问题）';
            unset($output);
            continue;
        }

        // 解析逻辑：过滤空行 + 精准匹配包含REG_的行
        $value = '';
        foreach ($output as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'HKEY_') === 0) {
                continue; // 跳过空行、注册表路径行
            }
            // 匹配包含 REG_DWORD/REG_SZ 的行（核心修正）
            if (strpos($line, 'REG_DWORD') !== false || strpos($line, 'REG_SZ') !== false) {
                // 分割规则：按“至少2个空格”分割（兼容不同系统的输出格式）
                $parts = preg_split('/\s{2,}/', $line, -1, PREG_SPLIT_NO_EMPTY);
                // 取值：最后一个元素（REG_DWORD是数值，REG_SZ是路径）
                $value = end($parts);
                break;
            }
        }

        $result[$key] = $value;
        unset($output); // 清理临时输出
    }

    // 转换DWORD值为易读文字（0=深色，1=浅色）
    $convertDword = function($dword) {
        if (str_starts_with($dword, '0x')) {
            return hexdec($dword) === 1 ? '浅色主题' : '深色主题';
        }
        return '未知';
    };
    $result['system_mode_text'] = $convertDword($result['system_mode']);
    $result['app_mode_text'] = $convertDword($result['app_mode']);

    // 提取主题名称（修正：仅当theme_path是有效路径时处理）
    if (!empty($result['theme_path']) && strpos($result['theme_path'], '0x') === false) {
        $result['theme_name'] = basename($result['theme_path'], '.theme');
    } else {
        $result['theme_name'] = '未知（无主题路径/解析失败）';
    }

    // 移除调试用的原始输出（如需调试可保留）
    unset($result['system_mode_raw_output'], $result['app_mode_raw_output'], $result['theme_path_raw_output']);
    return $result;
}

// 调用并输出结果
$themeInfo = getWindowsThemeWithoutCOM();
echo "=== Windows主题信息（修复版） ===\n";
print_r($themeInfo);
?>