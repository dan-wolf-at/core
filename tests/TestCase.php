<?php

declare(strict_types=1);

namespace Apiato\Core\Tests;

use Apiato\Core\Providers\ApiatoServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Vinkla\Hashids\Facades\Hashids;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;
    use WithWorkbench;

    public function decode(string $hashedId): null|int
    {
        $result = Hashids::decode($hashedId);

        if (empty($result)) {
            return null;
        }

        return $result[0];
    }

    public function encode(int $id): string
    {
        return Hashids::encode($id);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach ((app(ApiatoServiceProvider::class, ['app' => $this->app]))->serviceProviders as $provider) {
            App::register($provider);
        }
    }
}
