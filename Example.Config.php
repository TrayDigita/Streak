<?php

use Monolog\Level;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use TrayDigita\Streak\Source\Session\Driver\CacheDriver;

return [
    'application' => [
        // Deployment: string(debug|development|production) -> production
        'environment' => 'production',
        // Site URL: string(https://example.com)
        'siteUrl' => null,
        // Language: string(i18n language code)
        'language' => 'id',
        // filtering html with dom document
        'filterHtml' => true,
    ],
    'json' => [
        // Prettify Json: boolean(true|false) -> false
        'pretty' => true,
        // Display Meta Version: boolean(true|false) -> false
        'displayVersion' => true,
    ],
    'logging' => [
        // Logging: boolean(true|false) -> false
        'enable' => null,
        'level'   => Level::Warning
    ],
    'error' => [
        'hidePath' => true,
    ],
    'security' => [
        'salt' => 'random string',
        'secret' => 'random string',
    ],
    'path' => [
        'api'    => 'api',
        'admin'  => 'admin',
        'member' => 'member',
        'storage' => 'storage',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'database_name',
        'user' => 'database_password',
        'password' => 'password'
    ],
    'cache' => [
        // class-string
        // @uses \Symfony\Component\Cache\Adapter\AbstractAdapter
        'adapter' => RedisAdapter::class,
        // options redis port
        'port' => 6379,
        // options only use database
        'options' => []
    ],
    'session' => [
        // class-string
        // @uses \TrayDigita\Streak\Source\Session\Abstracts\AbstractSessionDriver
        'driver' => CacheDriver::class,
        // session name
        'name' => 'streak'
    ],
    'directory' => [
        /* for `streak!` app */
        'sbinDir'  => "/usr/sbin",
        'binDir'   => "/usr/bin",
        'root'     => "/",
    ],
];
