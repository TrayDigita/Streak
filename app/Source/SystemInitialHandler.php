<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Traits\EventsMethods;

class SystemInitialHandler extends AbstractContainerization
{
    use EventsMethods;

    /**
     * @var bool
     */
    private bool $registered = false;

    /**
     * @var ?StreamInterface|false
     */
    private null|StreamInterface|bool $stream = null;

    public function register()
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;
        if (!Validator::isCli()) {
            $obHandler = ob_list_handlers();
            $content   = null;
            if (count($obHandler) === 1 && isset($obHandler[0]) && !is_callable($obHandler[0])) {
                if (ob_get_length()) {
                    $content = ob_get_clean();
                }

                ob_get_level() > 0 && ob_end_flush();
            }

            ob_start([$this, 'handleBuffer'], 4096);
            set_error_handler(fn (...$args) => $this->handleError(...$args));
            register_shutdown_function([$this, 'handleShutdown']);
            set_exception_handler([$this, 'handleException']);
            // do echo let previous buffer served
            if ($content) {
                echo $content;
            }
            unset($content);
        }
    }

    private function handleBuffer(string $content): string
    {
        if ($this->stream === null) {
            try {
                $this->stream = $this->getContainer(
                    ResponseFactoryInterface::class
                )->createResponse()->getBody();
            } catch (Throwable) {
                $this->stream = false;
            }
        }

        $this->stream && $this->stream->write($content);
        return $content;
    }

    /**
     * @return ?StreamInterface
     */
    public function getStream(): ?StreamInterface
    {
        return $this->stream;
    }

    private function handleShutdown()
    {
        $this
            ->getContainer(Benchmark::class)
            ->addStop('Application:shutdown');
        $this->eventDispatch('Shutdown:handler', $this);
    }

    private function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline,
        ?array $errcontext = null
    ) {
        $this
            ->eventDispatch(
                'Error:handler',
                $errno,
                $errstr,
                $errfile,
                $errline,
                $errcontext,
                $this
            );
    }

    private function handleException(Throwable $exception)
    {
        $this
            ->eventDispatch(
                'Exception:handler',
                $exception,
                $this
            );
    }
}
