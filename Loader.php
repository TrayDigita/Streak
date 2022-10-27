<?php
/** @noinspection PhpIncludeInspection */
declare(strict_types=1);

namespace TrayDigita\Streak;

use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Configurations;
use TrayDigita\Streak\Source\Console\Runner;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Controller\Commands\MakeController;
use TrayDigita\Streak\Source\Database\Commands\MakeModel;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Middleware\Commands\MakeMiddleware;
use TrayDigita\Streak\Source\Module\Commands\MakeModule;
use TrayDigita\Streak\Source\Scheduler\Commands\RunScheduler;

return (function () {

    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require __DIR__ . '/vendor/autoload.php';
    } elseif (
        // if its on vendor
        file_exists(dirname(__DIR__, 2).'/composer/ClassLoader.php')
        && is_file(dirname(__DIR__, 2) . '/autoload.php')
    ) {
        require dirname(__DIR__, 2) . '/autoload.php';
    }

    // add container
    $container = new Container();
    $events  = $container->get(Events::class);
    $appPath = Consolidation::appDirectory();
    $rootDir = Consolidation::rootDirectory();
    $events->add('Middleware:directory', fn () => "$appPath/Middleware");
    $events->add('Controller:directory', fn () => "$appPath/Controller");
    $events->add('Module:directory', fn () => "$appPath/Module");

    /**
     * Add Language
     */
    if (is_dir(__DIR__.'/languages')) {
        $container->translation->addDirectory(__DIR__.'/languages', 'default');
    }

    if ($rootDir !== __DIR__ && is_dir("$rootDir/languages")) {
        $container->translation->addDirectory("$rootDir/languages", 'default');
    }

    $container->set(Configurations::class, fn () => new Configurations(
        is_file("$rootDir/Config.php")
            ? (array) (require "$rootDir/Config.php")
            : []
    ))->protect();
    $application = new Application($container);
    unset($container);
    (function () use ($rootDir) {
        if (file_exists("$rootDir/loader/Init.php")) {
            require_once "$rootDir/loader/Init.php";
        }
    })->call($application);

    if (Validator::isCli()) {
        $container = $application->getContainer();
        $console = $container->get(Runner::class);
        $console->addCommands([
            new MakeController($container),
            new MakeModule($container),
            new MakeMiddleware($container),
            new MakeModel($container),
            new RunScheduler($container)
        ]);
    }

    $events->dispatch('Serve:loader');

    return $application;
})();
