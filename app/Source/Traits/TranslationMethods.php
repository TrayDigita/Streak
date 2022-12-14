<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use TrayDigita\Streak\Source\i18n\Translator;

trait TranslationMethods
{
    /**
     * @param string $message
     * @param string|null $textDomain
     * @param string|null $locale
     *
     * @return string
     * @uses Translator::translate()
     */
    public function translate(
        string $message,
        ?string $textDomain = null,
        ?string $locale = null
    ) : string {
        if (!method_exists($this, 'getContainer')) {
            return $message;
        }
        $translation = $this->getContainer(Translator::class);
        if ($translation instanceof Translator) {
            return $translation->translate($message, $textDomain, $locale);
        }
        return $message;
    }

    /**
     * @param string $singular
     * @param string $plural
     * @param int $number
     * @param string|null $textDomain
     * @param string|null $locale
     *
     * @return string
     * @uses Translator::translatePlural()
     */
    public function translatePlural(
        string $singular,
        string $plural,
        int $number,
        ?string $textDomain = null,
        ?string $locale = null
    ) : string {
        if (method_exists($this, 'getContainer')) {
            $translation = $this->getContainer(Translator::class);
            if ($translation instanceof Translator) {
                return $translation->translatePlural(
                    $singular,
                    $plural,
                    $number,
                    $textDomain,
                    $locale
                );
            }
        }
        return $number === 1 ? $singular : $plural;
    }
}
