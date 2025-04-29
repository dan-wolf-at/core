<?php

declare(strict_types=1);

namespace Apiato\Core\Commands;

use Apiato\Core\Abstracts\Commands\ConsoleCommand;
use Apiato\Core\Foundation\Facades\Apiato;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\ConsoleOutput;

class ListTasksCommand extends ConsoleCommand
{
    /**
     * @var ConsoleOutput
     */
    public $console;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'apiato:list:tasks {--withfilename}';

    /**
     * The console command description.
     */
    protected $description = 'List all Tasks in the Application.';

    public function __construct(ConsoleOutput $console)
    {
        parent::__construct();

        $this->console = $console;
    }

    public function handle(): void
    {
        foreach (Apiato::getSectionNames() as $sectionName) {
            foreach (Apiato::getSectionContainerNames($sectionName) as $containerName) {
                $this->console->writeln(\sprintf('<fg=yellow> [%s]</fg=yellow>', $containerName));

                $directory = base_path('app/Containers/' . $sectionName . '/' . $containerName . '/Tasks');

                if (!File::isDirectory($directory)) {
                    continue;
                }

                $files = File::allFiles($directory);

                foreach ($files as $file) {
                    // Get the file name as is
                    $fileName = $file->getFilename();
                    $originalFileName = $fileName;
                    // Remove the Task.php postfix from each file name
                    // Further, remove the `.php', if the file does not end on 'Task.php'
                    $fileName = str_replace(['Task.php', '.php'], '', $fileName);

                    // UnCamelize the word and replace it with spaces
                    $fileName = uncamelize($fileName);

                    // Check if flag exists
                    $includeFileName = '';

                    if ($this->option('withfilename')) {
                        $includeFileName = \sprintf('<fg=red>(%s)</fg=red>', $originalFileName);
                    }

                    $this->console->writeln(\sprintf('<fg=green>  - %s</fg=green>  %s', $fileName, $includeFileName));
                }
            }
        }
    }
}
