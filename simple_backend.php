<?php
/**
 * 简化版财务管理系统API服务器
 * 无需Laravel依赖，直接可用
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 获取请求路径
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 清理路径，移除结尾的斜杠
$path = rtrim($path, '/');
if (empty($path)) $path = '/';

// 路由处理
switch ($path) {
    case '/api/health':
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'message' => '财务管理系统API运行正常',
            'server' => 'Simple PHP Backend'
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/api/auth/login':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            
            // 简单的用户验证
            $users = [
                'admin' => ['password' => 'admin123', 'role' => 'admin', 'name' => '管理员'],
                'finance' => ['password' => 'finance123', 'role' => 'finance', 'name' => '财务'],
                'field001' => ['password' => '123456', 'role' => 'field', 'name' => '外勤001']
            ];
            
            if (isset($users[$username]) && $users[$username]['password'] === $password) {
                echo json_encode([
                    'success' => true,
                    'message' => '登录成功',
                    'access_token' => base64_encode($username . ':' . time()),
                    'user' => [
                        'username' => $username,
                        'name' => $users[$username]['name'],
                        'role' => $users[$username]['role']
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => '用户名或密码错误'
                ], JSON_UNESCAPED_UNICODE);
            }
        }
        break;
        
    case '/api/channels':
        echo json_encode([
            'success' => true,
            'data' => [
                ['id' => 1, 'name' => '中国银行', 'category' => 'bank', 'status' => 'active'],
                ['id' => 2, 'name' => '支付宝', 'category' => 'ewallet', 'status' => 'active'],
                ['id' => 3, 'name' => '现金', 'category' => 'cash', 'status' => 'active']
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/api/transactions':
        echo json_encode([
            'success' => true,
            'data' => [
                [
                    'id' => 1,
                    'type' => 'income',
                    'rmb_amount' => 1000.00,
                    'hkd_amount' => 1150.00,
                    'exchange_rate' => 1.15,
                    'channel' => '中国银行',
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 2,
                    'type' => 'outcome',
                    'rmb_amount' => 500.00,
                    'hkd_amount' => 575.00,
                    'exchange_rate' => 1.15,
                    'channel' => '支付宝',
                    'created_at' => date('Y-m-d H:i:s', time() - 3600)
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/api/admin/balance/overview':
        echo json_encode([
            'success' => true,
            'data' => [
                'total_rmb' => 15000.00,
                'total_hkd' => 17250.00,
                'channels' => [
                    ['name' => '中国银行', 'rmb_balance' => 8000.00, 'hkd_balance' => 9200.00],
                    ['name' => '支付宝', 'rmb_balance' => 5000.00, 'hkd_balance' => 5750.00],
                    ['name' => '现金', 'rmb_balance' => 2000.00, 'hkd_balance' => 2300.00]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/admin':
        // 重定向到专用后台页面
        header('Location: /backend_admin.php');
        exit;
        break;
        
    case '/backend_admin.php':
        // 直接包含后台管理页面
        include 'backend_admin.php';
        exit;
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => '接口不存在',
            'available_endpoints' => [
                '/api/health',
                '/api/auth/login',
                '/api/channels',
                '/api/transactions',
                '/admin'
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
}
?>
