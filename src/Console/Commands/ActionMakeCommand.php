<?php

namespace Lucid\Console\Commands;

use Illuminate\Console\Command;
use Lucid\Generators\ActionGenerator;
use Lucid\Str;

class ActionMakeCommand extends Command
{
    protected $signature = 'make:action
                            {action : The action\'s name.}
                            {domain : The domain to be responsible for the action.}
                            {--Q|queue : Whether a action is queueable or not.}
                            ';

    protected $description = 'Create a new Action in a domain';

    public function handle(): void
    {
        try {
            $action = (new ActionGenerator())
                ->generate(
                    Str::action($this->argument('action')),
                    Str::studly($this->argument('domain')),
                    $this->option('queue')
                );

            $this->info(
                "Action class $action->title created successfully."
                ."\n\n"
                ."Find it at <comment>$action->relativePath</comment>\n"
            );
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
