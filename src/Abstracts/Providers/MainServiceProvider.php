<?php

declare(strict_types=1);

namespace Apiato\Core\Abstracts\Providers;

use Apiato\Core\Loaders\AliasesLoaderTrait;
use Apiato\Core\Loaders\ProvidersLoaderTrait;
use Illuminate\Support\ServiceProvider as LaravelAppServiceProvider;

abstract class MainServiceProvider extends LaravelAppServiceProvider
{
    use AliasesLoaderTrait;
    use ProvidersLoaderTrait;

    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->loadServiceProviders();
        $this->loadAliases();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
