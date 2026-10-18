<?php

return [
    // 节点多久没心跳算离线（秒）；register-hysteria 的节点默认不会心跳，部署后手动标记为在线
    'node_online_ttl' => (int) env('NODE_ONLINE_TTL', 90),

    // 心跳写库节流（秒）：同一节点在该窗口内只落一次 last_seen_at，防止高频心跳打库
    'heartbeat_write_gate' => (int) env('HEARTBEAT_WRITE_GATE', 15),

    // 注册接口是否要求共享密钥（留空 = 不校验；生产建议填）
    'register_shared_secret' => env('REGISTER_SHARED_SECRET', ''),

    // 终端用户注册成功后展示的服务器地址（/join/{code} 的欢迎页、客户端配置）
    'public_host' => env('HYSTERIA_PUBLIC_HOST', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    'public_port' => (int) env('HYSTERIA_PUBLIC_PORT', 443),

    // 服务商后台「一键安装」用的脚本地址；留空 = 用面板自己的 /api/install.sh
    'install_script_url' => env('INSTALL_SCRIPT_URL', ''),
];
