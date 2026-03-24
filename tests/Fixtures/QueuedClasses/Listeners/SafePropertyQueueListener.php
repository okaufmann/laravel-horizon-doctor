<?php

namespace Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SafePropertyQueueListener implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'emails';

    public function handle(): void {}
}
