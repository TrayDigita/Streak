<?php
declare(strict_types=1);

namespace TrayDigita\Streak;

use ReflectionClass;
use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Configurations;
use TrayDigita\Streak\Source\Console\Runner;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Controller\Commands\MakeController;
use TrayDigita\Streak\Source\Database\Commands\MakeModel;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Middleware\Commands\MakeMiddleware;
use TrayDigita\Streak\Source\Module\Commands\MakeModule;
use TrayDigita\Streak\Source\Scheduler\Commands\RunScheduler;

return (function () {

    require __DIR__ . '/vendor/autoload.php';

    // add container
    $container = new Container();
    $events = $container->get(Events::class);
    $ref = new ReflectionClass($container);
    $appPath = dirname($ref->getFileName(), 2);
    $events->add('Middleware:directory', fn () => "$appPath/Middleware");
    $events->add('Controller:directory', fn () => "$appPath/Controller");
    $events->add('Module:directory', fn () => "$appPath/Module");

    /**
     * Add Language
     */
    if (is_dir(__DIR__.'/languages')) {
        $container->translation->addDirectory(__DIR__.'/languages', 'default');
    }

    $container->set(Configurations::class, function () : Configurations {
        $config = file_exists(__DIR__ .'/Config.php') && is_file(__DIR__ .'/Config.php')
            ? (array) (require __DIR__ .'/Config.php')
            : [];
        return new Configurations($config);
    })->protect();
    $application = new Application($container);
    unset($container);
    (function () {
        if (file_exists(__DIR__ .'/loader/Init.php')) {
            /** @noinspection PhpIncludeInspection */
            require_once __DIR__ .'/loader/Init.php';
        }
    })->call($application);

    if (Validator::isCli()) {
        $container = $application->getContainer();
        $console = $container->get(Runner::class);
        // @todo completing
        $console->addCommands([
            new MakeController($container),
            // new MakeModule($container),
            // new MakeMiddleware($container),
            new MakeModel($container),
            new RunScheduler($container)
        ]);
    }

    $events->dispatch('Serve:loader');

    return $application;
})();
