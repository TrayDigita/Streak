#!/usr/bin/env php
<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Bin {

    use Psr\Http\Message\ResponseFactoryInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Slim\Exception\HttpSpecializedException;
    use Symfony\Component\Console\Output\ConsoleOutput;
    use Throwable;
    use TrayDigita\Streak\Source\Application;
    use TrayDigita\Streak\Source\Console\Runner;
    use TrayDigita\Streak\Source\Container;
    use TrayDigita\Streak\Source\Helper\Util\Validator;

    /**
     * @var Application $app
     */
    $app = require dirname(__DIR__).'/Loader.php';
    if (!Validator::isCli()) {
        die(sprintf('%s only allowed on cli mode', basename(__FILE__)));
    }

    try {
        $run = false;
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use (&$run) {
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
            try {
                return $handler->handle($request);
            } catch (HttpSpecializedException) {
                return $this->get(ResponseFactoryInterface::class)->createResponse();
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
