<?php

namespace Lucid\Bus;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Collection;
use Lucid\Events\FeatureStarted;
use Lucid\Units\Feature;

trait ServesFeatures
{
    use Marshal;
    use DispatchesJobs;

    /**
     * Serve the given feature with the given arguments.
     */
    public function serve(string|Feature $feature, array $arguments = []): mixed
    {
        if (!$feature instanceof Feature) {
            event(new FeatureStarted($feature, $arguments));
            $job = $this->marshal($feature, new Collection(), $arguments);
        } else {
            event(new FeatureStarted($feature::class, $arguments));
            $job = $feature;
        }

        if ($job instanceof ShouldQueue) {
            return $this->dispatch($job);
        }

        return $this->dispatchSync($feature);
    }
}
