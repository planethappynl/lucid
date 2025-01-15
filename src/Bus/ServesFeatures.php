<?php

namespace Lucid\Bus;

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

            return $this->dispatchSync($this->marshal($feature, new Collection(), $arguments));
        }

        event(new FeatureStarted($feature::class, $arguments));

        return $this->dispatchSync($feature);
    }
}
