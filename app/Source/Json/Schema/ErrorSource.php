<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Json\Schema;

use JetBrains\PhpStorm\Pure;
use WoohooLabs\Yin\JsonApi\Schema\Error\ErrorSource as YinSource;

class ErrorSource extends YinSource
{
    private string $header;

    /**
     * @param string $pointer
     * @param string $parameter
     * @param string $header
     */
    #[Pure] public function __construct(string $pointer, string $parameter, string $header = '')
    {
        parent::__construct($pointer, $parameter);
        $this->header = $header;
    }

    /**
     * @param string $header
     *
     * @return ErrorSource
     */
    #[Pure] public static function fromHeader(string $header) : ErrorSource
    {
        return new ErrorSource("", "", $header);
    }

    /**
     * @return string|null
     */
    public function getHeader(): ?string
    {
        return $this->header;
    }

    /**
     * @internal
     */
    #[Pure] public function transform(): array
    {
        $content = [];
        if ($this->getPointer() !== "") {
            $content["pointer"] = $this->getPointer();
        }

        if ($this->getParameter() !== "") {
            $content["parameter"] = $this->getParameter();
        }
        if ($this->getHeader() !== "") {
            $content['header'] = $this->getHeader();
        }
        return $content;
    }
}
