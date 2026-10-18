<?php

return [
    // 采集器（hysteria-traffic-collector）调用 /api/collector/v1/* 的共享令牌。
    // 留空 = 关闭采集器 API（全部返回 503），生产必须配置。
    'token' => env('COLLECTOR_TOKEN'),
];
