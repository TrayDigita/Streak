<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use TrayDigita\Streak\Source\Helper\Util\Validator;

trait NamespacesArray
{
    protected array $namespaces = [];

    /**
     * @param string $namespace
     *
     * @return bool
     */
    public function addNamespace(string $namespace) : bool
    {
        $namespace = trim(trim($namespace), '\\');
        if (!Validator::isValidNamespace($namespace)) {
            return false;
        }
        $this->namespaces[$namespace] = $namespace;
        return true;
    }

    public function removeNameSpace(string $namespace) : bool
    {
        $namespace = trim(trim($namespace), '\\');
        if (!Validator::isValidNamespace($namespace)) {
            return false;
        }
        unset($this->namespaces[$namespace]);
        return true;
    }

    public function getNamespaces() : array
    {
        return array_values($this->namespaces);
    }
}
