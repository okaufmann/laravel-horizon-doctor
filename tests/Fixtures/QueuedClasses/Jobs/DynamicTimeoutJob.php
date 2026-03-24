<?php

namespace Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DynamicTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout;

    public function __construct(
        public int $seconds = 60,
    ) {
        $this->timeout = $this->seconds;
    }
}
