<?php

declare(strict_types=1);

namespace Apiato\Core\Loaders;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait LocalizationLoaderTrait
{
    public function loadLocalsFromContainers(string $containerPath): void
    {
        $containerLocaleDirectory = $containerPath . '/Languages';
        $containerName = basename($containerPath);
        $pathParts = explode(DIRECTORY_SEPARATOR, $containerPath);
        $sectionName = $pathParts[\count($pathParts) - 2];

        $this->loadLocals($containerLocaleDirectory, $containerName, $sectionName);
    }

    public function loadLocalsFromShip(): void
    {
        $shipLocaleDirectory = base_path('app/Ship/Languages');
        $this->loadLocals($shipLocaleDirectory, 'ship');
    }

    private function loadLocals($directory, $containerName, $sectionName = null): void
    {
        if (File::isDirectory($directory)) {
            $this->loadTranslationsFrom($directory, $this->buildLocaleNamespace($sectionName, $containerName));
            $this->loadJsonTranslationsFrom($directory);
        }
    }

    private function buildLocaleNamespace(null|string $sectionName, string $containerName): string
    {
        return $sectionName !== null && $sectionName !== '' ? (Str::camel($sectionName) . '@' . Str::camel($containerName)) : Str::camel($containerName);
    }
}
