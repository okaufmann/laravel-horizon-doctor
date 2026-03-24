<?php

use Okaufmann\LaravelHorizonDoctor\Tests\TestCase;
use PHPUnit\Framework\TestCase as BaseTestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit/Support');
uses(TestCase::class)->in('Unit/Checks/Global');
uses(BaseTestCase::class)->in('Unit/Checks/Environment');
uses(BaseTestCase::class)->in('Unit/Checks/Supervisor');
uses(TestCase::class)->in('Unit/Checks/QueuedClasses');
