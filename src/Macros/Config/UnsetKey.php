<?php

declare(strict_types=1);

namespace Apiato\Core\Macros\Config;

use Illuminate\Config\Repository;
use Illuminate\Support\Arr;

class UnsetKey
{
    public function __invoke(): callable
    {
        return function (array|string|int|float $key): void {
            $deleter = \Closure::bind(
                static function (Repository $repo, array|string|int|float $key): void {
                    Arr::forget($repo->items, $key);
                },
                null,
                Repository::class
            );

            /* @var Repository $this */
            $deleter($this, $key);
        };
    }
}
