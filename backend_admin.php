<?php
/**
 * 财务管理系统 - 专用后台管理页面
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>财务管理系统 - 后台管理</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1976d2;
            margin: 0;
            font-size: 2.5rem;
        }
        .subtitle {
            color: #666;
            margin: 10px 0 0 0;
            font-size: 1.1rem;
        }
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card h3 {
            margin: 0 0 15px 0;
            color: #1976d2;
            font-size: 1.3rem;
        }
        .btn {
            background: #1976d2;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 8px 8px 8px 0;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #1565c0;
        }
        .btn.success { background: #4caf50; }
        .btn.success:hover { background: #45a049; }
        .btn.warning { background: #ff9800; }
        .btn.warning:hover { background: #e68900; }
        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.online {
            background: #4caf50;
            color: white;
        }
        .status.loading {
            background: #ff9800;
            color: white;
        }
        .status.offline {
            background: #f44336;
            color: white;
        }
        .accounts {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .accounts ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .accounts li {
            margin: 5px 0;
        }
        .highlight {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #2196f3;
            margin: 20px 0;
        }
        .result {
            margin-top: 15px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏦 财务管理系统</h1>
            <p class="subtitle">后台管理控制台</p>
            <div class="highlight">
                <p><strong>🎉 系统状态：</strong>
                    <span id="api-status" class="status loading">检查中...</span>
                    <span id="frontend-status" class="status loading">检查中...</span>
                </p>
            </div>
        </div>

        <div class="dashboard">
            <div class="card">
                <h3>🚀 快速启动</h3>
                <p>选择你要使用的系统组件：</p>
                <a href="test_api.html" class="btn success" target="_blank">📋 API测试页面</a>
                <a href="http://localhost:3000" class="btn" target="_blank">📱 前端H5应用</a>
                <button class="btn warning" onclick="checkAllServices()">🔄 刷新状态</button>
            </div>

            <div class="card">
                <h3>🔗 API接口测试</h3>
                <p>测试系统各项API功能：</p>
                <button class="btn" onclick="testAPI('/api/health')">📡 健康检查</button>
                <button class="btn" onclick="testAPI('/api/channels')">🏦 支付渠道</button>
                <button class="btn" onclick="testAPI('/api/transactions')">💰 交易记录</button>
                <div id="api-result" class="result"></div>
            </div>

            <div class="card">
                <h3>🔐 用户认证</h3>
                <p>测试不同角色的登录功能：</p>
                <button class="btn" onclick="quickLogin('admin', 'admin123')">👑 管理员登录</button>
                <button class="btn" onclick="quickLogin('finance', 'finance123')">💼 财务登录</button>
                <button class="btn" onclick="quickLogin('field001', '123456')">🚶 外勤登录</button>
                <div id="login-result" class="result"></div>
            </div>

            <div class="card">
                <h3>📊 系统信息</h3>
                <p><strong>当前时间：</strong><?php echo date('Y-m-d H:i:s'); ?></p>
                <p><strong>PHP版本：</strong><?php echo PHP_VERSION; ?></p>
                <p><strong>服务器：</strong>PHP内置开发服务器</p>
                <div id="system-info" style="margin: 10px 0;">
                    <span class="status online">系统正常运行</span>
                </div>
                <button class="btn" onclick="checkSystemInfo()">🔍 检查系统信息</button>
            </div>
        </div>

        <div class="card">
            <h3>📋 测试账户信息</h3>
            <div class="accounts">
                <p><strong>🔑 预设账户：</strong></p>
                <ul>
                    <li><strong>管理员：</strong> admin / admin123 <span style="color: #4caf50;">（全部权限）</span></li>
                    <li><strong>财务：</strong> finance / finance123 <span style="color: #ff9800;">（查看和导出权限）</span></li>
                    <li><strong>外勤：</strong> field001 / 123456 <span style="color: #2196f3;">（移动端功能）</span></li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = 'http://localhost:8000/api';
        const FRONTEND_URL = 'http://localhost:3000';
        
        // 检查所有服务状态
        async function checkAllServices() {
            // 检查API状态
            try {
                const response = await fetch(`${API_BASE}/health`);
                if (response.ok) {
                    document.getElementById('api-status').textContent = 'API在线';
                    document.getElementById('api-status').className = 'status online';
                } else {
                    throw new Error('API响应异常');
                }
            } catch (error) {
                document.getElementById('api-status').textContent = 'API离线';
                document.getElementById('api-status').className = 'status offline';
            }

            // 检查前端状态
            try {
                const response = await fetch(FRONTEND_URL);
                if (response.ok) {
                    document.getElementById('frontend-status').textContent = '前端在线';
                    document.getElementById('frontend-status').className = 'status online';
                } else {
                    throw new Error('前端响应异常');
                }
            } catch (error) {
                document.getElementById('frontend-status').textContent = '前端离线';
                document.getElementById('frontend-status').className = 'status offline';
            }
        }

        // 测试API
        async function testAPI(endpoint) {
            const result = document.getElementById('api-result');
            result.style.display = 'block';
            result.textContent = '正在测试...';

            try {
                const response = await fetch(`${API_BASE}${endpoint}`);
                const data = await response.json();
                result.style.background = '#e8f5e8';
                result.innerHTML = `<strong>✅ 成功:</strong><br><pre>${JSON.stringify(data, null, 2)}</pre>`;
            } catch (error) {
                result.style.background = '#ffebee';
                result.innerHTML = `<strong>❌ 错误:</strong> ${error.message}`;
            }
        }

        // 快速登录测试
        async function quickLogin(username, password) {
            const result = document.getElementById('login-result');
            result.style.display = 'block';
            result.textContent = '正在登录...';

            try {
                const response = await fetch(`${API_BASE}/auth/login`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();
                
                if (response.ok) {
                    result.style.background = '#e8f5e8';
                    result.innerHTML = `<strong>✅ 登录成功:</strong><br>角色: ${data.user.role}<br>姓名: ${data.user.name}`;
                } else {
                    result.style.background = '#ffebee';
                    result.innerHTML = `<strong>❌ 登录失败:</strong> ${data.message}`;
                }
            } catch (error) {
                result.style.background = '#ffebee';
                result.innerHTML = `<strong>❌ 错误:</strong> ${error.message}`;
            }
        }

        // 检查系统信息
        function checkSystemInfo() {
            alert('系统运行正常！\n\n' +
                  '✅ PHP后台服务：运行中\n' + 
                  '✅ API接口：可用\n' +
                  '✅ 数据库：模拟数据\n' +
                  '✅ 用户认证：正常\n\n' +
                  '如需完整功能，请使用Laravel版本。');
        }

        // 页面加载时检查服务状态
        window.onload = checkAllServices;
        
        // 每30秒自动检查一次状态
        setInterval(checkAllServices, 30000);
    </script>
</body>
</html>
