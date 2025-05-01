<?php

declare(strict_types=1);

namespace Apiato\Core\Generator\Traits;

use Apiato\Core\Generator\GeneratorCommand;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;

/**
 * @mixin GeneratorCommand
 */
trait UIGeneratorTrait
{
    protected function runCallParam(): array
    {
        $useTransporters = $this->checkParameterOrConfirm('transporters', 'Would you like to use specific Transporters?', true);

        $sectionName  = $this->sectionName;
        $_sectionName = Str::lower($this->sectionName);

        $containerName  = $this->containerName;
        $_containerName = Str::lower($this->containerName);

        $model  = $this->containerName;
        $models = Pluralizer::plural($model);

        $this->printInfoMessage('Generating README File');
        $this->call('apiato:generate:readme', [
            '--section'   => $sectionName,
            '--container' => $containerName,
            '--file'      => 'README',
        ]);

        $this->printInfoMessage('Generating Configuration File');
        $this->call('apiato:generate:configuration', [
            '--section'   => $sectionName,
            '--container' => $containerName,
            '--file'      => Str::camel($this->sectionName) . '-' . Str::camel($this->containerName),
        ]);

        $this->printInfoMessage('Generating MainServiceProvider');
        $this->call('apiato:generate:provider', [
            '--section'   => $sectionName,
            '--container' => $containerName,
            '--file'      => 'MainServiceProvider',
            '--stub'      => 'main-service-provider',
        ]);

        $this->printInfoMessage('Generating Model and Repository');
        $this->call('apiato:generate:model', [
            '--section'    => $sectionName,
            '--container'  => $containerName,
            '--file'       => $model,
            '--repository' => true,
        ]);

        $this->printInfoMessage('Generating a basic Migration file');
        $this->call('apiato:generate:migration', [
            '--section'   => $sectionName,
            '--container' => $containerName,
            '--file'      => 'create_' . Str::snake($models) . '_table',
            '--tablename' => Str::snake($models),
            '--new'       => true,
        ]);

        return [
            $useTransporters,
            $sectionName,
            $_sectionName,
            $containerName,
            $_containerName,
            $model,
            $models,
        ];
    }
}
