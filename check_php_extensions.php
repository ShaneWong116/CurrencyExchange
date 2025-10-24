<?php
/**
 * PHP扩展检查工具
 * 用于检查财务管理系统所需的PHP扩展是否已启用
 * 
 * 使用方法:
 * php check_php_extensions.php
 */

// 定义颜色代码（Windows CMD支持有限，Linux/Mac完全支持）
define('COLOR_RESET', "\033[0m");
define('COLOR_RED', "\033[31m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_BOLD', "\033[1m");

// Windows环境检测
$is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// 在Windows下启用ANSI颜色支持（Windows 10+）
if ($is_windows && function_exists('sapi_windows_vt100_support')) {
    @sapi_windows_vt100_support(STDOUT);
}

/**
 * 打印带颜色的文本
 */
function printColor($text, $color = COLOR_RESET) {
    echo $color . $text . COLOR_RESET;
}

/**
 * 打印标题
 */
function printHeader($text) {
    echo "\n" . str_repeat("=", 70) . "\n";
    printColor($text, COLOR_BOLD . COLOR_BLUE);
    echo "\n" . str_repeat("=", 70) . "\n\n";
}

/**
 * 打印分隔线
 */
function printSeparator() {
    echo str_repeat("-", 70) . "\n";
}

/**
 * 检查扩展状态
 */
function checkExtension($ext, $description, $required = true) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅ 已启用' : ($required ? '❌ 未启用' : '⚠️  未启用');
    $color = $loaded ? COLOR_GREEN : ($required ? COLOR_RED : COLOR_YELLOW);
    
    printf("%-15s ", $ext);
    printColor($status, $color);
    printf(" - %s\n", $description);
    
    return $loaded;
}

/**
 * 主检查函数
 */
function runChecks() {
    printHeader("PHP 扩展配置检查报告");
    
    // 基本信息
    echo "检查时间: " . date('Y-m-d H:i:s') . "\n";
    echo "PHP 版本: " . PHP_VERSION . "\n";
    echo "操作系统: " . PHP_OS . "\n";
    echo "配置文件: " . (php_ini_loaded_file() ?: '未找到') . "\n";
    echo "SAPI 模式: " . PHP_SAPI . "\n";
    
    // 必需扩展检查
    printHeader("【必需扩展】");
    
    $required_extensions = [
        'curl' => 'HTTP客户端 - API调用、Guzzle依赖',
        'fileinfo' => '文件类型检测 - 文件上传验证',
        'mbstring' => '多字节字符串 - 中文处理',
        'openssl' => 'SSL/TLS加密 - HTTPS支持',
        'pdo' => '数据库抽象层 - 数据库连接',
        'pdo_mysql' => 'MySQL数据库 - 主数据库',
        'tokenizer' => 'PHP代码解析 - Laravel必需',
        'xml' => 'XML处理 - 配置文件解析',
        'ctype' => '字符类型检测 - Laravel必需',
        'json' => 'JSON处理 - API响应',
    ];
    
    $required_missing = [];
    foreach ($required_extensions as $ext => $desc) {
        if (!checkExtension($ext, $desc, true)) {
            $required_missing[] = $ext;
        }
    }
    
    // 推荐扩展检查
    printHeader("【推荐扩展】");
    
    $recommended_extensions = [
        'gd' => '图片处理 - 缩略图、图片上传',
        'zip' => 'ZIP压缩 - Excel导出、文件打包',
        'bcmath' => '高精度数学 - 财务计算',
        'intl' => '国际化支持 - 多语言、货币格式',
    ];
    
    $recommended_missing = [];
    foreach ($recommended_extensions as $ext => $desc) {
        if (!checkExtension($ext, $desc, false)) {
            $recommended_missing[] = $ext;
        }
    }
    
    // 性能优化扩展
    printHeader("【性能优化扩展】");
    
    $performance_extensions = [
        'opcache' => 'OPcode缓存 - 大幅提升性能',
        'apcu' => '用户缓存 - 应用数据缓存',
    ];
    
    $performance_missing = [];
    foreach ($performance_extensions as $ext => $desc) {
        if (!checkExtension($ext, $desc, false)) {
            $performance_missing[] = $ext;
        }
    }
    
    // 其他有用扩展
    printHeader("【其他有用扩展】");
    
    $other_extensions = [
        'exif' => 'EXIF元数据 - 图片信息读取',
        'iconv' => '字符编码转换 - 多编码支持',
        'filter' => '数据过滤 - 输入验证',
        'hash' => '哈希算法 - 密码加密',
        'session' => '会话管理 - 用户状态',
        'dom' => 'DOM解析 - HTML/XML处理',
    ];
    
    foreach ($other_extensions as $ext => $desc) {
        checkExtension($ext, $desc, false);
    }
    
    // 总结报告
    printHeader("【检查总结】");
    
    $total_required = count($required_extensions);
    $total_recommended = count($recommended_extensions);
    $total_performance = count($performance_extensions);
    
    $required_enabled = $total_required - count($required_missing);
    $recommended_enabled = $total_recommended - count($recommended_missing);
    $performance_enabled = $total_performance - count($performance_missing);
    
    echo "必需扩展: ";
    if ($required_enabled == $total_required) {
        printColor("全部启用 ({$required_enabled}/{$total_required})", COLOR_GREEN);
    } else {
        printColor("缺少 " . count($required_missing) . " 个 ({$required_enabled}/{$total_required})", COLOR_RED);
    }
    echo "\n";
    
    echo "推荐扩展: ";
    if ($recommended_enabled == $total_recommended) {
        printColor("全部启用 ({$recommended_enabled}/{$total_recommended})", COLOR_GREEN);
    } else {
        printColor("缺少 " . count($recommended_missing) . " 个 ({$recommended_enabled}/{$total_recommended})", COLOR_YELLOW);
    }
    echo "\n";
    
    echo "性能扩展: ";
    if ($performance_enabled == $total_performance) {
        printColor("全部启用 ({$performance_enabled}/{$total_performance})", COLOR_GREEN);
    } else {
        printColor("缺少 " . count($performance_missing) . " 个 ({$performance_enabled}/{$total_performance})", COLOR_YELLOW);
    }
    echo "\n";
    
    // 缺失扩展列表
    if (!empty($required_missing) || !empty($recommended_missing)) {
        printHeader("【需要启用的扩展】");
        
        if (!empty($required_missing)) {
            printColor("必需扩展（必须启用）:\n", COLOR_RED);
            foreach ($required_missing as $ext) {
                echo "  - {$ext}\n";
            }
            echo "\n";
        }
        
        if (!empty($recommended_missing)) {
            printColor("推荐扩展（建议启用）:\n", COLOR_YELLOW);
            foreach ($recommended_missing as $ext) {
                echo "  - {$ext}\n";
            }
            echo "\n";
        }
        
        // 提供修复建议
        printHeader("【修复建议】");
        
        $php_ini = php_ini_loaded_file();
        if ($php_ini) {
            echo "1. 编辑PHP配置文件:\n";
            printColor("   {$php_ini}\n", COLOR_YELLOW);
            echo "\n";
        }
        
        echo "2. 在配置文件中启用以下扩展:\n";
        echo "   (删除行首的分号 ; )\n\n";
        
        $all_missing = array_merge($required_missing, $recommended_missing);
        foreach ($all_missing as $ext) {
            printColor("   extension={$ext}\n", COLOR_GREEN);
        }
        
        echo "\n3. 重启Web服务器或PHP-FPM\n\n";
        echo "4. 再次运行此脚本验证:\n";
        printColor("   php check_php_extensions.php\n", COLOR_YELLOW);
        
        echo "\n详细说明请查看: PHP_EXTENSIONS_SETUP.md\n";
    } else {
        printHeader("【恭喜】");
        printColor("✅ 所有必需和推荐的扩展都已启用！\n", COLOR_GREEN);
        printColor("系统已准备就绪，可以正常运行。\n", COLOR_GREEN);
    }
    
    // 所有已加载扩展
    printHeader("【所有已加载的扩展】(" . count(get_loaded_extensions()) . "个)");
    
    $loaded = get_loaded_extensions();
    sort($loaded);
    
    $chunks = array_chunk($loaded, 5);
    foreach ($chunks as $chunk) {
        echo implode(", ", $chunk) . "\n";
    }
    
    // OPcache配置检查
    if (extension_loaded('opcache')) {
        printHeader("【OPcache 配置】");
        
        $opcache_enabled = ini_get('opcache.enable');
        echo "启用状态: ";
        if ($opcache_enabled) {
            printColor("已启用 ✅\n", COLOR_GREEN);
            echo "内存消耗: " . ini_get('opcache.memory_consumption') . " MB\n";
            echo "最大文件数: " . ini_get('opcache.max_accelerated_files') . "\n";
            echo "验证频率: " . ini_get('opcache.revalidate_freq') . " 秒\n";
        } else {
            printColor("未启用 ⚠️\n", COLOR_YELLOW);
            echo "建议在php.ini中启用OPcache以提升性能\n";
        }
    }
    
    // PHP配置建议
    printHeader("【PHP 配置检查】");
    
    $settings = [
        'memory_limit' => ['min' => '256M', 'recommended' => '512M'],
        'max_execution_time' => ['min' => '60', 'recommended' => '120'],
        'upload_max_filesize' => ['min' => '20M', 'recommended' => '50M'],
        'post_max_size' => ['min' => '20M', 'recommended' => '50M'],
    ];
    
    foreach ($settings as $setting => $values) {
        $current = ini_get($setting);
        printf("%-25s: %s (推荐: %s)\n", $setting, $current, $values['recommended']);
    }
    
    printSeparator();
    echo "\n";
}

// 运行检查
try {
    runChecks();
    exit(0);
} catch (Exception $e) {
    printColor("错误: " . $e->getMessage() . "\n", COLOR_RED);
    exit(1);
}

