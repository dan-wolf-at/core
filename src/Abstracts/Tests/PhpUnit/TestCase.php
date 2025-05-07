<?php

declare(strict_types=1);

namespace Apiato\Core\Abstracts\Tests\PhpUnit;

use Apiato\Core\Traits\HashIdTrait;
use Apiato\Core\Traits\TestCaseTrait;
use Apiato\Core\Traits\TestTraits\PhpUnit\TestAssertionHelperTrait;
use Apiato\Core\Traits\TestTraits\PhpUnit\TestAuthHelperTrait;
use Apiato\Core\Traits\TestTraits\PhpUnit\TestRequestHelperTrait;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

abstract class TestCase extends LaravelTestCase
{
    use HashIdTrait;
    use LazilyRefreshDatabase;
    use TestAssertionHelperTrait;
    use TestAuthHelperTrait;
    use TestCaseTrait;
    use TestRequestHelperTrait;

    /**
     * The base URL to use while testing the application.
     */
    protected string $baseUrl;

    /**
     * Seed the DB on migrations.
     */
    protected bool $seed = true;

    /**
     * Refresh a conventional test database.
     */
    protected function refreshTestDatabase(): void
    {
        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            $this->setupPassportOAuth2();

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
