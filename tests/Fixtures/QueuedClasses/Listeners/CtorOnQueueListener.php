<?php

namespace Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CtorOnQueueListener implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('emails');
    }

    public function handle(): void {}
}
