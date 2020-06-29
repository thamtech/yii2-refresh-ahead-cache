<?php

namespace thamtechunit\caching\refreshAhead\queue;

use thamtech\caching\refreshAhead\GeneratorInterface;
use thamtech\caching\refreshAhead\queue\RefreshJob;
use Yii;

class MockRefreshAhead
{
    public function generateAndSet($key, $generator, $duration, $dependency)
    {
        return $generator->generate(null);
    }
}

class MockGenerator implements GeneratorInterface
{
    public static $refreshCalls = [];
    public static $refreshResponses = [];

    public static $generateCalls = [];
    public static $generateResponses = [];

    public function getMutexLockTimeout()
    {
        return 10;
    }

    public function refresh($cache, $key, $duration, $dependency = null)
    {
        static::$refreshCalls[] = [
            // leaving out 'cache' to simplify test results
            'key' => $key,
            'duration' => $duration,
            'dependency' => $dependency,
        ];

        return array_shift(static::$refreshResponses);
    }

    public function generate($cache)
    {
        static::$generateCalls[] = true;

        return array_shift(static::$generateResponses);
    }

    // method in QueueGenerator
    // (RefreshJob expects the generator to be a QueueGenerator)
    public function getRefreshAhead()
    {
        return new MockRefreshAhead();
    }
}

class RefreshJobTest extends \thamtechunit\caching\refreshAhead\TestCase
{
    protected function setUp(): void
    {
        MockGenerator::$refreshCalls = [];
        MockGenerator::$refreshResponses = [];
        MockGenerator::$generateCalls = [];
        MockGenerator::$generateResponses = [];
    }

    public function testExecute()
    {
        $job = Yii::createObject([
            'class' => RefreshJob::class,
            'generatorConfig' => [
                'class' => MockGenerator::class,
            ],
            'key' => 'abc123',
            'duration' => 20,
            'dependency' => 'test123',
        ]);

        MockGenerator::$generateResponses[] = 'result123';

        $job->execute(null);
        $this->assertCount(0, MockGenerator::$refreshCalls);
        $this->assertEquals([true], MockGenerator::$generateCalls);
    }

    public function testExecuteNotExpired()
    {
        $job = Yii::createObject([
            'class' => RefreshJob::class,
            'generatorConfig' => [
                'class' => MockGenerator::class,
            ],
            'key' => 'abc123',
            'duration' => 20,
            'dependency' => 'test123',
            'expiresAt' => time() + 10,
        ]);

        MockGenerator::$generateResponses[] = 'result123';

        $job->execute(null);
        $this->assertCount(0, MockGenerator::$refreshCalls);
        $this->assertEquals([true], MockGenerator::$generateCalls);
    }

    public function testExecuteExpired()
    {
        $job = Yii::createObject([
            'class' => RefreshJob::class,
            'generatorConfig' => [
                'class' => MockGenerator::class,
            ],
            'key' => 'abc123',
            'duration' => 20,
            'dependency' => 'test123',
            'expiresAt' => time() - 10,
        ]);

        MockGenerator::$generateResponses[] = 'result123';

        $job->execute(null);
        $this->assertCount(0, MockGenerator::$refreshCalls);
        $this->assertCount(0, MockGenerator::$generateCalls);
    }
}
