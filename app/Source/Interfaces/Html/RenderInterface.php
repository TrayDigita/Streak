<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Html;

use Psr\Http\Message\ResponseInterface;

interface RenderInterface
{
    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle(string $title) : static;

    /**
     * @return string
     */
    public function getTitle() : string;

    /**
     * @return string
     */
    public function getCharset() : string;

    /**
     * @param string $charset
     *
     * @return $this
     */
    public function setCharset(string $charset) : static;

    /**
     * @param AttributeInterface[] $attributes
     *
     * @return $this
     */
    public function setBodyAttributes(array $attributes) : static;

    /**
     * @return array<string, AttributeInterface>
     */
    public function getBodyAttributes() : array;

    /**
     * @param AttributeInterface $attribute
     */
    public function addBodyAttribute(AttributeInterface $attribute) : static;

    /**
     * @param AttributeInterface|string $attribute
     */
    public function removeBodyAttribute(AttributeInterface|string $attribute) : static;

    /**
     * @param AttributeInterface[] $attributes
     *
     * @return $this
     */
    public function setHtmlAttributes(array $attributes) : static;

    /**
     * @return array<string, AttributeInterface>
     */
    public function getHtmlAttributes() : array;

    /**
     * @param AttributeInterface $attribute
     */
    public function addHtmlAttribute(AttributeInterface $attribute) : static;

    /**
     * @param AttributeInterface|string $attribute
     */
    public function removeHtmlAttribute(AttributeInterface|string $attribute) : static;

    /**
     * @param string $content
     *
     * @return $this
     */
    public function setBodyContent(string $content) : static;

    /**
     * The body Content
     *
     * @return string
     */
    public function getBodyContent() : string;

    /**
     * @param string $content
     *
     * @return $this
     */
    public function setHeaderContent(string $content) : static;

    /**
     * The head Content
     *
     * @return string
     */
    public function getHeaderContent() : string;

    /**
     * Magic Method
     *
     * @return string
     */
    public function __toString() : string;

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function render(ResponseInterface $response) : ResponseInterface;
}
