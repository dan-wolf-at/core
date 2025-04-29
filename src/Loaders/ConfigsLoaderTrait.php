<?php

declare(strict_types=1);

namespace Apiato\Core\Loaders;

use Illuminate\Support\Facades\File;

trait ConfigsLoaderTrait
{
    public function loadConfigsFromShip(): void
    {
        $shipConfigsDirectory = base_path('app/Ship/Configs');
        $this->loadConfigs($shipConfigsDirectory);
    }

    public function loadConfigsFromContainers(string $containerPath): void
    {
        $containerConfigsDirectory = $containerPath . '/Configs';
        $this->loadConfigs($containerConfigsDirectory);
    }

    private function loadConfigs(string $configFolder): void
    {
        if (File::isDirectory($configFolder)) {
            $files = File::files($configFolder);

            foreach ($files as $file) {
                $name = File::name($file);
                $path = $configFolder . '/' . $name . '.php';

                $this->mergeConfigFrom($path, $name);
            }
        }
    }
}
