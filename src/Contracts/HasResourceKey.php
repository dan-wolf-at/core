<?php

declare(strict_types=1);

namespace Apiato\Core\Contracts;

interface HasResourceKey
{
    public function getResourceKey(): string;
}
