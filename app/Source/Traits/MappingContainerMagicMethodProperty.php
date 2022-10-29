<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;
use Slim\App;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\i18n\Translator;
use TrayDigita\Streak\Source\Benchmark;
use TrayDigita\Streak\Source\i18n\Translation;

/**
 * @property-read Translation $translation
 * @property-read Translator $translator
 * @property-read App $app
 * @property-read App $slim
 * @property-read Events $events
 * @property-read Container $container
 * @property-read SharedEventManager $sharedEventManager
 * @property-read EventManager $eventManager
 * @property-read Benchmark $timeRecord
 */
trait MappingContainerMagicMethodProperty
{
    private ?bool $traitIsInContainer = null;

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($this->traitIsInContainer === null) {
            $this->traitIsInContainer = is_subclass_of($this, AbstractContainerization::class)
                || method_exists($this, 'getContainer');
        }
        if (!$this->traitIsInContainer) {
            return $this->$name;
        }
        return $this->getContainer()->$name;
    }
}
