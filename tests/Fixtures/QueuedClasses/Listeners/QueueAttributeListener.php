<?php

namespace Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Queue('emails')]
final class QueueAttributeListener implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void {}
}
