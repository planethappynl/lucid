<?php

namespace Lucid\Generators;

use Exception;
use Lucid\Entities\Action;
use Lucid\Str;

class ActionGenerator extends Generator
{
    /**
     * @throws Exception
     */
    public function generate(string $action, string $domain, bool $isQueueable = false): Action
    {
        $action = Str::action($action);
        $domain = Str::domain($domain);
        $path = $this->findActionPath($domain, $action);

        if ($this->exists($path)) {
            throw new Exception('Action already exists');
        }

        // Make sure the domain directory exists
        $this->createDomainDirectory($domain);

        // Create the action
        $namespace = $this->findDomainActionsNamespace($domain);

        $content = file_get_contents($this->getStub($isQueueable));
        $content = str_replace(
            ['{{action}}', '{{namespace}}', '{{unit_namespace}}'],
            [$action, $namespace, $this->findUnitNamespace()],
            $content ?: ''
        );

        $this->createFile($path, $content);

        $this->generateTestFile($action, $domain);

        return new Action(
            $action,
            $namespace,
            basename($path),
            $path,
            $this->relativeFromReal($path),
            $this->findDomain($domain),
            $content
        );
    }

    /**
     * Generate test file.
     *
     * @throws Exception
     */
    private function generateTestFile(string $action, string $domain): void
    {
        $content = file_get_contents($this->getTestStub());

        $namespace = $this->findDomainActionsTestsNamespace($domain);
        $actionNamespace = $this->findDomainActionsNamespace($domain)."\\$action";
        $testClass = $action.'Test';

        $content = str_replace(
            ['{{namespace}}', '{{testclass}}', '{{action}}', '{{action_namespace}}'],
            [$namespace, $testClass, Str::snake($action), $actionNamespace],
            $content ?: ''
        );

        $path = $this->findActionTestPath($domain, $testClass);

        $this->createFile($path, $content);
    }

    /**
     * Create domain directory.
     */
    private function createDomainDirectory(string $domain)
    {
        $this->createDirectory($this->findDomainPath($domain).'/Actions');
        $this->createDirectory($this->findDomainTestsPath($domain).'/Actions');
    }

    /**
     * Get the stub file for the generator.
     */
    public function getStub(bool $isQueueable = false): string
    {
        if ($isQueueable) {
            return __DIR__.'/stubs/action-queueable.stub';
        } else {
            return __DIR__.'/stubs/action.stub';
        }
    }

    /**
     * Get the test stub file for the generator.
     */
    public function getTestStub(): string
    {
        return __DIR__.'/stubs/action-test.stub';
    }
}
