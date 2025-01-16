<?php

namespace Lucid\Console\Commands;

use Illuminate\Console\Command;
use Lucid\Filesystem;
use Lucid\Finder;
use Lucid\Str;

class ActionDeleteCommand extends Command
{
    use Filesystem, Finder;

    protected $signature = 'delete:action
                            {action : The action\'s name.}
                            {domain : The domain from which the action will be deleted.}
                            ';

    protected $description = 'Delete an existing Action in a domain';

    public function handle(): void
    {
        try {
            $domain = Str::studly($this->argument('domain'));
            $title = Str::action($this->argument('action'));

            // Delete action
            if (! $this->exists($action = $this->findActionPath($domain, $title))) {
                $this->error("Action class $title cannot be found.");
            } else {
                $this->delete($action);

                if (count($this->listActions($domain)->first()) === 0) {
                    $this->delete($this->findDomainPath($domain));
                }

                $this->info("Action class <comment>$title</comment> deleted successfully.");
            }

            // Delete action tests
            $testTitle = $title.'Test';
            if (! $this->exists($action = $this->findActionTestPath($domain, $testTitle))) {
                $this->error("Action test class $testTitle cannot be found.");
            } else {
                $this->delete($action);

                $this->info("Action test class <comment>$testTitle</comment> deleted successfully.");
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
