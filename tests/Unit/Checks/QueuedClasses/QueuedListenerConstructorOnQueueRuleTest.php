<?php

use Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses\QueuedListenerConstructorOnQueueRule;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassAstAnalyzer;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassDiscovery;
use PhpParser\ParserFactory;

$testsRoot = dirname(__DIR__, 3);

it('warns when a listener-shaped class only sets the queue from the constructor', function () use ($testsRoot) {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $discovery = new QueuedClassDiscovery($parser);
    $analyzer = new QueuedClassAstAnalyzer();
    $rule = new QueuedListenerConstructorOnQueueRule();

    $discovered = $discovery->discover($testsRoot, [
        'queued_class_paths' => ['Fixtures/QueuedClasses/Listeners'],
        'queued_class_exclude_patterns' => [],
    ]);

    $metadata = [];
    foreach ($discovered as $d) {
        $metadata[] = $analyzer->analyze($d);
    }

    $result = $rule->check($metadata);
    expect($result->warnings)->not->toBeEmpty()
        ->and($result->errors)->toBe([]);
});
