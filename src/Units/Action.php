<?php

namespace Lucid\Units;

use Lucid\Testing\MockMe;

/**
 * An abstract Action to be extended by every action.
 * Note that this action is self-handling which
 * means it will NOT be queued, rather
 * will have the "handle()" method
 * called instead.
 *
 * @template ReturnType
 * @implements Unit<ReturnType>
 */
abstract class Action implements Unit
{
    use MockMe;
}
