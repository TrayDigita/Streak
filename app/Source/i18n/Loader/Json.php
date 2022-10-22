<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\i18n\Loader;

use ErrorException;
use GuzzleHttp\Psr7\Stream;
use Laminas\I18n\Translator\Loader\AbstractFileLoader;
use Laminas\I18n\Translator\Plural\Rule as PluralRule;
use Laminas\I18n\Translator\TextDomain;
use Laminas\Stdlib\ErrorHandler;
use Laminas\I18n\Exception\InvalidArgumentException;
use Throwable;

class Json extends AbstractFileLoader
{
    const DEFAULT_PLURAL = "nplurals=2; plural=(n > 1);";

    private function determinePlurals($plurals) : ?string
    {
        if (is_numeric($plurals)) {
            return is_string($plurals) && str_contains($plurals, '.')
                ? null
                : sprintf("nplurals=%d; plural=(n > 1);", (int)$plurals);
        }
        if (!is_string($plurals) || ($plurals = trim($plurals)) === '') {
            return null;
        }
        if (preg_match('~nplurals\s*=\s*(\d+)~', $plurals)
            && preg_match('~plural\s*=\s*[^;\n]+~', $plurals)
        ) {
            $plurals = preg_replace('~nplurals\s*=\s*(\d+)~', 'nplurals=$1', $plurals);
            return preg_replace('~plural\s*=\s*([^;\n]+)~', 'plural=$1', $plurals);
        }
        return null;
    }

    /**
     * load(): defined by FileLoaderInterface.
     *
     * @param  string $locale
     * @param  string $filename
     *
     * @return TextDomain
     * @throws InvalidArgumentException|ErrorException
     * @see    FileLoaderInterface::load()
     *
     */
    public function load($locale, $filename): TextDomain
    {
        $resolvedFile = $this->resolveFile($filename);
        if (! $resolvedFile) {
            throw new InvalidArgumentException(sprintf(
                'Could not find or open file %s for reading.',
                $filename
            ));
        }

        ErrorHandler::start();
        $file  = fopen($resolvedFile, 'rb');
        $error = ErrorHandler::stop();
        if (false === $file) {
            throw new InvalidArgumentException(sprintf(
                'Could not open file %s for reading.',
                $filename
            ), 0, $error);
        }
        $stream = new Stream($file);
        $json = (string) $stream;
        unset($stream);
        $json = json_decode($json, true);
        if (!is_array($json)) {
            throw new InvalidArgumentException(
                sprintf('File %s is not valid translations.', $filename),
                0
            );
        }

        $plurals = $json['plurals']??null;
        $pluralSet = $this->determinePlurals($plurals);

        $messages = null;
        if (!$pluralSet && isset($json['plural-forms']) && is_string($json['plural-forms'])) {
            $pluralSet = $this->determinePlurals($json['plural-forms']);
        }
        if (isset($json['locale_data']) && is_array($json['locale_data'])) {
            $pluralSet = !$pluralSet ?
                (
                    isset($json['locale_data']['plurals'])
                    ? $this->determinePlurals($json['locale_data']['plurals'])
                    : null
                ) : $pluralSet;
            if (isset($json['locale_data']['messages']) && is_array($json['locale_data']['messages'])) {
                $messages = $json['locale_data']['messages'];
            }
        } elseif (isset($json['messages']) && is_array($json['messages'])) {
            $messages = $json['messages'];
        }
        unset($json);

        if (is_array($messages) && isset($messages[''])) {
            $messagePlurals = $messages[''];
            unset($messages['']);
            if (!$pluralSet && is_array($messagePlurals)) {
                $messagePlurals = $messagePlurals['plural-forms']??($messagePlurals['plural']??null);
                if (is_numeric($messagePlurals) || is_string($messagePlurals)) {
                    $pluralSet = $this->determinePlurals($messagePlurals);
                }
            }
        }
        $pluralSet = $pluralSet?:self::DEFAULT_PLURAL;
        $messages = is_array($messages) ? $messages : [];
        $textDomain = new TextDomain($messages);
        try {
            $textDomain->setPluralRule(PluralRule::fromString($pluralSet));
        } catch (Throwable) {
            // pass
        }

        return $textDomain;
    }
}
