<?php
declare(strict_types=1);

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
        'filterHtml' => false,
        // store shutdown handler buffer
        'storeBuffer' => true,
        // buffer mode (storage|memory|tempfile)
        // using 'storage' will use storage/cache/streams/uuid-v4/sock_xxxxxx
        // end will clean on each request done
        'bufferMode' => 'storage'
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
        // log level
        'level'   => Level::Warning,
        // max files using rotate
        'maxFiles' => 10
    ],
    'error' => [
        // hide root path on error
        'hidePath' => true,
    ],
    'security' => [
        // salt key -> fill with random string
        'salt' => 'random string',
        // security key -> fill with random string
        'secret' => 'random string',
    ],
    'path' => [
        // api endpoint
        'api'    => 'api',
        // admin endpoint
        'admin'  => 'admin',
        // member endpoint
        'member' => 'member',
        // storage directory from root
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
