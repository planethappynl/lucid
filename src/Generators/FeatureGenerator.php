<?php

namespace Lucid\Generators;

use Exception;
use Lucid\Entities\Feature;
use Lucid\Str;

class FeatureGenerator extends Generator
{
    /**
     * @throws Exception
     */
    public function generate(string $feature, ?string $service, array $actions = []): Feature
    {
        $feature = Str::feature($feature);
        $service = Str::service($service);

        $path = $this->findFeaturePath($service, $feature);
        $classname = $this->classname($feature);

        if ($this->exists($path)) {
            throw new Exception('Feature already exists!');
        }

        $namespace = $this->findFeatureNamespace($service, $feature);

        $content = file_get_contents($this->getStub());

        $useActions = ''; // stores the `use` statements of the actions
        $runActions = ''; // stores the `$this->run` statements of the actions

        foreach ($actions as $index => $action) {
            $useActions .= 'use '.$action['namespace'].'\\'.$action['className'].";\n";
            $runActions .= "\t\t".'$this->run('.$action['className'].'::class);';

            // only add carriage returns when it's not the last action
            if ($index != count($actions) - 1) {
                $runActions .= "\n\n";
            }
        }

        $content = str_replace(
            ['{{feature}}', '{{namespace}}', '{{unit_namespace}}', '{{use_actions}}', '{{run_actions}}'],
            [$classname, $namespace, $this->findUnitNamespace(), $useActions, $runActions],
            $content ?: ''
        );

        $this->createFile($path, $content);

        // generate test file
        $this->generateTestFile($feature, $service);

        return new Feature(
            $feature,
            basename($path),
            $path,
            $this->relativeFromReal($path),
            ($service) ? $this->findService($service) : null,
            $content
        );
    }

    private function classname(string $feature): string
    {
        $parts = explode(DS, $feature);

        return array_pop($parts);
    }

    /**
     * Generate the test file.
     *
     * @throws Exception
     */
    private function generateTestFile(string $feature, ?string $service)
    {
        $content = file_get_contents($this->getTestStub());

        $namespace = $this->findFeatureTestNamespace($service);
        $featureClass = $this->classname($feature);
        $featureNamespace = $this->findFeatureNamespace($service, $feature).'\\'.$featureClass;
        $testClass = $featureClass.'Test';

        $content = str_replace(
            ['{{namespace}}', '{{testclass}}', '{{feature}}', '{{feature_namespace}}'],
            [$namespace, $testClass, Str::snake(str_replace(DS, '', $feature)), $featureNamespace],
            $content ?: ''
        );

        $path = $this->findFeatureTestPath($service, $feature.'Test');

        $this->createFile($path, $content);
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/feature.stub';
    }

    /**
     * Get the test stub file for the generator.
     */
    private function getTestStub(): string
    {
        return __DIR__.'/stubs/feature-test.stub';
    }
}
