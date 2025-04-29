<?php

declare(strict_types=1);

namespace Apiato\Core\Generator\Commands;

use Apiato\Core\Generator\GeneratorCommand;
use Apiato\Core\Generator\Interfaces\ComponentsGenerator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ServiceProviderGenerator extends GeneratorCommand implements ComponentsGenerator
{
    private const string TAB2 = '        ';

    private const string TAB3 = '            ';

    /**
     * User required/optional inputs expected to be passed while calling the command.
     * This is a replacement of the `getArguments` function "which reads whenever it's called".
     */
    public array $inputs = [
        ['stub', null, InputOption::VALUE_OPTIONAL, 'The stub file to load for this generator.'],
        ['event-listeners', null, InputOption::VALUE_OPTIONAL, 'The Event Listeners that this Provider should register.'],
        ['event-service-provider', null, InputOption::VALUE_OPTIONAL, 'The Event Service Provider that this Provider should register.'],
    ];

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'apiato:generate:provider';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Service Provider for a Container';

    /**
     * The type of class being generated.
     */
    protected string $fileType = 'ServiceProvider';

    /**
     * The structure of the file path.
     */
    protected string $pathStructure = '{section-name}/{container-name}/Providers/*';

    /**
     * The structure of the file name.
     */
    protected string $nameStructure = '{file-name}';

    /**
     * The name of the stub file.
     */
    protected string $stubName = 'providers/generic.stub';

    public function getUserInputs(): null|array
    {
        $stub = $this->option('stub');
        $eventServiceProvider = $this->option('event-service-provider');
        if (!$stub) {
            $stub = $this->checkParameterOrChoice(
                'stub',
                'Select the Stub you want to load',
                ['Generic', 'MainServiceProvider', 'EventServiceProvider', 'MiddlewareServiceProvider'],
                0,
            );

            $stub = match ($stub) {
                'MainServiceProvider' => 'main-service-provider',
                'EventServiceProvider' => 'generic-event-service-provider',
                'MiddlewareServiceProvide' => 'middleware-service-provider',
                default => 'generic',
            };
        }

        $this->stubName = sprintf('providers/%s.stub', $stub);
        $eventListeners = $this->option('event-listeners');
        $eventListenersString = '[]';
        $listenersUseStatements = '';
        $eventsUseStatements = '';
        if ($eventListeners) {
            $listenersWithClass = array_map(static function ($listeners, string $listener) {
                return [$listener . '::class' => array_map(static fn ($event): string => $event . '::class', $listeners)];
            }, $eventListeners, array_keys($eventListeners));
            $eventListenersString = '[' . PHP_EOL . array_reduce($listenersWithClass, static function (string $carry, $item): string {
                return $carry . array_reduce(array_keys($item), static function ($carry, string $key) use ($item): string {
                    $carry .= self::TAB2 . $key . ' => [' . PHP_EOL;
                    $carry .= array_reduce($item[$key], static function (string $carry, string $event): string {
                        return $carry . (self::TAB3 . $event . ',' . PHP_EOL);
                    });

                    return $carry . (self::TAB2 . '],' . PHP_EOL);
                });
            }) . '    ]';
            $listenersUseStatements = array_reduce(array_keys($eventListeners), function (string $carry, string $item): string {
                return $carry . ('use App\Containers\\' . $this->sectionName . '\\' . $this->containerName . '\Listeners\\' . $item . ';' . PHP_EOL);
            });

            $eventsUseStatements = array_map(function ($listeners, $listener): array {
                return array_map(fn ($event): string => 'use App\Containers\\' . $this->sectionName . '\\' . $this->containerName . '\Events\\' . $event . ';', $listeners);
            }, $eventListeners, array_keys($eventListeners));
            $eventsUseStatements = array_reduce($eventsUseStatements, static function (string $carry, $item): string {
                return $carry . array_reduce(array_keys($item), static function (string $carry, $key) use ($item): string {
                    return $carry . ($item[$key] . PHP_EOL);
                });
            });
        }

        $useStatements = $eventsUseStatements . $listenersUseStatements;

        return [
            'path-parameters' => [
                'section-name' => $this->sectionName,
                'container-name' => $this->containerName,
            ],
            'stub-parameters' => [
                '_section-name' => Str::lower($this->sectionName),
                'section-name' => $this->sectionName,
                '_container-name' => Str::lower($this->containerName),
                'container-name' => $this->containerName,
                'class-name' => $this->fileName,
                'event-listeners' => $eventListenersString,
                'use-statements' => $useStatements,
                'event-service-provider' => $eventServiceProvider,
            ],
            'file-parameters' => [
                'file-name' => $this->fileName,
            ],
        ];
    }

    /**
     * Get the default file name for this component to be generated.
     */
    #[\Override]
    public function getDefaultFileName(): string
    {
        return 'MainServiceProvider';
    }
}
