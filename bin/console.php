#!/usr/bin/env php
<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Bin {

    use Symfony\Component\Console\Output\ConsoleOutput;
    use Throwable;
    use TrayDigita\Streak\Source\Application;
    use TrayDigita\Streak\Source\Console\Runner;
    use TrayDigita\Streak\Source\Container;
    use TrayDigita\Streak\Source\Controller\Commands\MakeController;
    use TrayDigita\Streak\Source\Database\Commands\MakeModel;
    use TrayDigita\Streak\Source\Middleware\Commands\MakeMiddleware;
    use TrayDigita\Streak\Source\Module\Commands\MakeModule;
    use TrayDigita\Streak\Source\Helper\Util\Validator;
    use TrayDigita\Streak\Source\Scheduler\Commands\RunScheduler;

    /**
     * @var Application $app
     */
    $app = require dirname(__DIR__).'/Loader.php';
    if (!Validator::isCli()) {
        die(sprintf('%s only allowed on cli mode', basename(__FILE__)));
    }

    try {
        $run = false;
        $app->add(function () use (&$run) {
            $run = true;
            /**
             * @var Container $this
             */
            $console = $this->get(Runner::class);
            $output = $this->get(ConsoleOutput::class);
            try {
                $console->setAutoExit(false);
                $console->run();
            } catch (Throwable $e) {
                $console->renderThrowable($e, $output);
            }
        });
        return $app->run(false, true);
    } finally {
        if (!$run) {
            /**
             * @var Container $this
             */
            $output = $app->getContainer(ConsoleOutput::class);
            $console = $app->getContainer(Runner::class);
            try {
                $console->run();
            } catch (Throwable $e) {
                $console->renderThrowable($e, $output);
            }
        }
    }
}
