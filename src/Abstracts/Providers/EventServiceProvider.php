<?php

declare(strict_types=1);

namespace Apiato\Core\Abstracts\Providers;

use Apiato\Core\Foundation\Facades\Apiato;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as LaravelEventServiceProvider;

abstract class EventServiceProvider extends LaravelEventServiceProvider
{
    #[\Override]
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }

    #[\Override]
    protected function discoverEventsWithin(): array
    {
        return array_map(static fn (string $path): string => $path . '/Listeners', Apiato::getAllContainerPaths());
    }
}
