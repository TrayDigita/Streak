#!/usr/bin/env php
<?php
/**
 * @internal internal use only before commit
 */
declare(strict_types=1);

namespace TrayDigita\Streak\Bin {

    use FilesystemIterator;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use SplFileInfo;
    use TrayDigita\Streak\Source\Application;

    if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
        die(sprintf('%s only allowed on cli mode', basename(__FILE__)));
    }
    require dirname(__DIR__) .'/vendor/autoload.php';
    $base       = dirname(__DIR__);
    $directory  = [
        "$base/Example.Config.php",
        "$base/Loader.php",
        "$base/app/Middleware/ErrorMiddlewareHandler.php",
        "$base/app/Middleware/SafePathMiddlewareDebugHandler.php",
        "$base/app/Middleware/SchedulerRegistrationMiddleware.php",
        "$base/app/Source",
        "$base/bin/streak-console",
        "$base/bin/console.php",
        "$base/bin/streak-cron",
        "$base/bin/cron.php",
        "$base/bin/sha-sum.php",
        "$base/bin/streak-sha-sum",
        "$base/public/index.php",
        "$base/languages",
        "$base/composer.json",
        "$base/phpcs.xml",
        "$base/LICENSE",
        "$base/README.md",
        "$base/VERSION",
        "$base/docs-md",
    ];
    $baseLength = strlen($base);
    $replaceDir = function ($dir) use ($baseLength) {
        return substr($dir, $baseLength + 1);
    };

    $checksums = [];
    foreach ($directory as $path) {
        $recursive = is_dir($path) ? new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
            | FilesystemIterator::KEY_AS_PATHNAME
            | FilesystemIterator::CURRENT_AS_SELF
            | FilesystemIterator::UNIX_PATHS
        ) : new SplFileInfo($path);
        if (is_iterable($recursive)) {
            /**
             * @var RecursiveDirectoryIterator $recursive
             */
            foreach (new RecursiveIteratorIterator($recursive) as $recursive) {
                if (str_starts_with($recursive->getBasename(), '.')
                    || $recursive->isLink()
                ) {
                    continue;
                }
                $pathName             = $replaceDir($recursive->getRealPath());
                $checksums[$pathName] = [
                    'md5' => md5_file($recursive->getRealPath()),
                    'sha1' => sha1_file($recursive->getRealPath()),
                    'sha256' => hash_file('sha256', $recursive->getRealPath()),
                ];
            }
        } else {
            $pathName             = $replaceDir($recursive->getRealPath());
            $checksums[$pathName] = [
                'md5' => md5_file($recursive->getRealPath()),
                'sha1' => sha1_file($recursive->getRealPath()),
                'sha256' => hash_file('sha256', $recursive->getRealPath()),
            ];
        }
    }

    $checksums = json_encode($checksums, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents("$base/checksums/checksums.json", $checksums);
    file_put_contents("$base/checksums/checksums.json.sha1", sha1($checksums));
    file_put_contents("$base/checksums/checksums.json.md5", md5($checksums));
    file_put_contents("$base/checksums/checksums.json.256", hash('sha256', $checksums));
    file_put_contents("$base/VERSION", Application::VERSION);
}
