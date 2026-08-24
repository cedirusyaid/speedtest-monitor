<?php
/**
 * Speedtest Monitoring Center - Global Configuration
 */

return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'user'     => 'root',
        'password' => '',
        'database' => 'db_monitoring',
        'charset'  => 'utf8mb4',
    ],
    'speedtest' => [
        'binary'   => '/data/data/com.termux/files/usr/bin/speedtest-cli',
        'timeout'  => 120,
        'log_file' => __DIR__ . '/speedtest.log',
    ]
];
