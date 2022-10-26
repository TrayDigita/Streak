<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use Composer\Autoload\ClassLoader;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;

trait ComposerLoaderObject
{
    /**
     * @return ?ClassLoader
     */
    public function getComposerClassLoader() : ?ClassLoader
    {
        return Consolidation::composerClassLoader();
    }

    public function registerNamespace(string $directory, string $namespace) : bool
    {
        return Consolidation::registerComposerNamespace($directory, $namespace);
    }
}
