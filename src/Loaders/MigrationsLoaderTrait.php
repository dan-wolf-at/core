<?php

declare(strict_types=1);

namespace Apiato\Core\Loaders;

use Illuminate\Support\Facades\File;

trait MigrationsLoaderTrait
{
    public function loadMigrationsFromContainers(string $containerPath): void
    {
        $containerMigrationDirectory = $containerPath . '/Data/Migrations';
        $this->loadMigrations($containerMigrationDirectory);
    }

    private function loadMigrations($directory): void
    {
        if (File::isDirectory($directory)) {
            $this->loadMigrationsFrom($directory);
        }
    }

    public function loadMigrationsFromShip(): void
    {
        $shipMigrationDirectory = base_path('app/Ship/Migrations');
        $this->loadMigrations($shipMigrationDirectory);
    }
}
