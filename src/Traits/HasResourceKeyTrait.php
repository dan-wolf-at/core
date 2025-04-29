<?php

declare(strict_types=1);

namespace Apiato\Core\Traits;

trait HasResourceKeyTrait
{
    /**
     * Returns the type for JSON API Serializer.
     *
     * If the $resourceKey property is set, it will be used as the resource key.
     * Otherwise, the class name will be used.
     */
    public function getResourceKey(): string
    {
        if (isset($this->resourceKey)) {
            return $this->resourceKey;
        }
        $reflectionClass = new \ReflectionClass($this);
        return $reflectionClass->getShortName();
    }
}
