<?php

namespace Lucid\Units;

use Lucid\Bus\UnitDispatcher;
use Lucid\Testing\MockMe;

/**
 * @template ReturnType
 * @implements Unit<ReturnType>
 */
abstract class Operation implements Unit
{
    use MockMe;
    use UnitDispatcher;
}
