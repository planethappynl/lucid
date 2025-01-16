<?php

namespace Lucid\Units;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * An abstract Action that can be managed with a queue
 * when extended the action will be queued by default.
 */
class QueueableAction extends Action implements ShouldQueue
{
    use SerializesModels;
    use InteractsWithQueue;
    use Queueable;
}
