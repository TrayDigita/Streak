<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\i18n;

use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;

/**
 * @mixin Translator
 */
class Translation extends AbstractContainerization
{
    public function translate(
        string $message,
        ?string $textDomain = null,
        ?string $locale = null
    ) : string {
        return $this
            ->getContainer(Translator::class)
            ->translate($message, $textDomain, $locale);
    }

    public function translatePlural(
        string $singular,
        string $plural,
        int $number,
        ?string $textDomain = null,
        ?string $locale = null
    ) : string {
        return $this
            ->getContainer(Translator::class)
             ->translatePlural($singular, $plural, $number, $textDomain, $locale);
    }

    /**
     * @param string $translate
     * @param ?string $textDomain
     * @param ?string $locale
     *
     * @return string
     */
    public function __invoke(
        string $translate,
        ?string $textDomain = null,
        ?string $locale = null
    ) : string {
        return $this->translate($translate, $textDomain, $locale);
    }

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array(
            [$this->getContainer(Translator::class), $name],
            $arguments
        );
    }
}
