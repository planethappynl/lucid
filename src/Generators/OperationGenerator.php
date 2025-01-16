<?php

namespace Lucid\Generators;

use Exception;
use Lucid\Entities\Operation;
use Lucid\Str;

class OperationGenerator extends Generator
{
    /**
     * @throws Exception
     */
    public function generate(
        string $operation,
        ?string $service,
        bool $isQueueable = false,
        array $actions = []
    ): Operation {
        $operation = Str::operation($operation);
        $service = Str::service($service);

        $path = $this->findOperationPath($service, $operation);

        if ($this->exists($path)) {
            throw new Exception('Operation already exists!');
        }

        $namespace = $this->findOperationNamespace($service);

        $content = file_get_contents($this->getStub($isQueueable));

        [$useActions, $runActions] = self::getUsesAndRunners($actions);

        $content = str_replace(
            ['{{operation}}', '{{namespace}}', '{{unit_namespace}}', '{{use_actions}}', '{{run_actions}}'],
            [$operation, $namespace, $this->findUnitNamespace(), $useActions, $runActions],
            $content ?: ''
        );

        $this->createFile($path, $content);

        // generate test file
        $this->generateTestFile($operation, $service);

        return new Operation(
            $operation,
            basename($path),
            $path,
            $this->relativeFromReal($path),
            ($service) ? $this->findService($service) : null,
            $content
        );
    }

    /**
     * Generate the test file.
     *
     * @throws Exception
     */
    private function generateTestFile(string $operation, ?string $service): void
    {
        $content = file_get_contents($this->getTestStub());

        $namespace = $this->findOperationTestNamespace($service);
        $operationNamespace = $this->findOperationNamespace($service)."\\$operation";
        $testClass = $operation.'Test';

        $content = str_replace(
            ['{{namespace}}', '{{testclass}}', '{{operation}}', '{{operation_namespace}}'],
            [$namespace, $testClass, Str::snake($operation), $operationNamespace],
            $content ?: ''
        );

        $path = $this->findOperationTestPath($service, $testClass);

        $this->createFile($path, $content);
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(bool $isQueueable = false): string
    {
        if ($isQueueable) {
            return __DIR__.'/stubs/operation-queueable.stub';
        } else {
            return __DIR__.'/stubs/operation.stub';
        }
    }

    /**
     * Get the test stub file for the generator.
     */
    private function getTestStub(): string
    {
        return __DIR__.'/stubs/operation-test.stub';
    }

    /**
     * Get de use to import the right class
     * Get de action run command
     */
    private static function getUseAndActionRunCommand(string $action): array
    {
        $str = Str::replaceLast('\\', '#', $action);
        $explode = explode('#', $str);

        $use = 'use '.$explode[0].'\\'.$explode['1'].";\n";
        $runActions = "\t\t".'$this->run('.$explode['1'].'::class);';

        return [$use, $runActions];
    }

    /**
     * Returns all users and all $this->run() generated
     */
    private static function getUsesAndRunners(array $actions): array
    {
        $useActions = '';
        $runActions = '';
        foreach ($actions as $index => $action) {
            [$useLine, $runLine] = self::getUseAndActionRunCommand($action);
            $useActions .= $useLine;
            $runActions .= $runLine;
            // only add carriage returns when it's not the last action
            if ($index != count($actions) - 1) {
                $runActions .= "\n\n";
            }
        }

        return [$useActions, $runActions];
    }
}
