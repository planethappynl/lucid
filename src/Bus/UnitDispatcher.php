<?php

namespace Lucid\Bus;

use App;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lucid\Events\ActionStarted;
use Lucid\Events\OperationStarted;
use Lucid\Testing\UnitMock;
use Lucid\Testing\UnitMockRegistry;
use Lucid\Units\Action;
use Lucid\Units\Operation;
use Lucid\Units\Unit;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;

trait UnitDispatcher
{
    use Marshal;
    use DispatchesJobs;

    /**
     * decorator function to be called instead of the
     * laravel function dispatchFromArray.
     * When the $arguments is an instance of Request
     * it will call dispatchFrom instead.
     *
     * @template ResultType
     * @param Unit<ResultType>|class-string<Unit<ResultType>> $unit
     * @param array|Request $arguments
     * @param array $extra
     * @return ResultType|PendingDispatch
     *
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function run(
        Unit|string   $unit,
        array|Request $arguments = [],
        array         $extra = []
    ): mixed
    {
        if ($arguments instanceof Request) {
            $unit = $this->marshal($unit, $arguments, $extra);
        } else if (!is_object($unit)) {
            $unit = $this->marshal($unit, new Collection(), $arguments);
        }

        // don't dispatch unit when in tests and have a mock for it.
        if (App::runningUnitTests() && app(UnitMockRegistry::class)->has(get_class($unit))) {
            /** @var UnitMock $mock */
            $mock = app(UnitMockRegistry::class)->get(get_class($unit));
            $mock->compareTo($unit);

            // Reaching this step confirms that the expected mock is similar to the passed instance, so we
            // get the unit's mock counterpart to be dispatched. Otherwise, the previous step would
            // throw an exception when the mock doesn't match the passed instance.
            $unit = $this->marshal(
                get_class($unit),
                new Collection(),
                $mock->getConstructorExpectationsForInstance($unit)
            );
        }

        if ($unit instanceof ShouldQueue) {
            $result = $this->dispatch($unit);
        } else {
            $result = $this->dispatchSync($unit);
        }

        if ($unit instanceof Operation) {
            event(new OperationStarted(get_class($unit), $arguments));
        }

        if ($unit instanceof Action) {
            event(new ActionStarted(get_class($unit), $arguments));
        }

        return $result;
    }

    /**
     * Run the given unit in the given queue.
     *
     * @throws ReflectionException
     */
    public function runInQueue(
        string  $unit,
        array   $arguments = [],
        ?string $queue = 'default'
    ): mixed
    {
        // instantiate and queue the unit
        $reflection = new ReflectionClass($unit);
        $instance = $reflection->newInstanceArgs($arguments);
        $instance->onQueue((string)$queue);

        return $this->dispatch($instance);
    }
}
