<?php

namespace thamtechunit\caching\refreshAhead;

use thamtech\caching\refreshAhead\RefreshAheadCacheBehavior;
use thamtech\caching\refreshAhead\CallableGenerator;
use Yii;

class CallableGeneratorTest extends \thamtechunit\caching\refreshAhead\TestCase
{
    public $sideEffect = null;

    protected function setUp(): void
    {
        $this->sideEffect = null;
    }

    public function testEnsureCallable()
    {
        $generator = $this->callableGenerator();

        $this->assertEquals(0, $generator->getMutexLockTimeout());

        // calling refresh on config generated from a single callable should
        // return false and not call the callable
        $this->assertFalse($generator->refresh('cache'));
        $this->assertNull($this->sideEffect);

        $this->assertEquals('generated', $generator->generate('cache'));
        $this->assertEquals('cache', $this->sideEffect);
    }

    public function testEnsureEmptyArray()
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('You must specify a generate callable.');
        RefreshAheadCacheBehavior::ensureGenerator([]);
    }

    public function testEnsureNull()
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('The required component is not specified.');
        RefreshAheadCacheBehavior::ensureGenerator(null);
    }

    /**
     * @dataProvider goodMutexLockTimeoutsProvider
     */
    public function testSetValidMutexLockTimeout($value, $expected)
    {
        $generator = $this->callableGenerator();

        $generator->setMutexLockTimeout($value);
        $this->assertEquals($expected, $generator->getMutexLockTimeout());
    }

    /**
     * @dataProvider goodMutexLockTimeoutsProvider
     */
    public function testAssignValidMutexLockTimeout($value, $expected)
    {
        $generator = $this->callableGenerator();

        $generator->mutexLockTimeout = $value;
        $this->assertEquals($expected, $generator->mutexLockTimeout);
    }

    /**
     * @dataProvider badMutexLockTimeoutsProvider
     */
    public function testSetInvalidMutexLockTimeout($value)
    {
        $generator = $this->callableGenerator();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('mutexLockTimeout must be an integer 0 or greater.');
        $generator->setMutexLockTimeout($value);
    }

    /**
     * @dataProvider badMutexLockTimeoutsProvider
     */
    public function testAssignInvalidMutexLockTimeout($value)
    {
        $generator = $this->callableGenerator();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('mutexLockTimeout must be an integer 0 or greater.');
        $generator->mutexLockTimeout = $value;
    }

    public function testSetRefreshNull()
    {
        $generator = $this->callableGenerator();
        $generator->setRefresh(null);
        $this->assertFalse($generator->refresh('cache'));
        $this->assertNull($this->sideEffect);
    }

    public function testSetRefreshBad()
    {
        $generator = $this->callableGenerator();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('refresh must be a callable.');
        $generator->setRefresh('not a callable');
    }

    public function testSetGenerateNull()
    {
        $generator = $this->callableGenerator();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('generate must be a callable.');
        $generator->setGenerate(null);
    }

    public function testInitNullGenerate()
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('You must specify a generate callable.');
        $generator = Yii::createObject([
            'class' => 'thamtech\caching\refreshAhead\CallableGenerator',
        ]);
    }

    public function testSetGenerateBad()
    {
        $generator = $this->callableGenerator();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('generate must be a callable.');
        $generator->setGenerate('not a callable');
    }

    public function testCallables()
    {
        $generator = RefreshAheadCacheBehavior::ensureGenerator([
            'refresh' => function ($cache) {
                $this->sideEffect = $cache;
                return true;
            },
            'generate' => function ($cache) {
                $this->sideEffect = $cache;
                return 'generated';
            },
        ]);

        $this->assertNull($this->sideEffect);
        $this->assertTrue($generator->refresh('cache'));
        $this->assertEquals('cache', $this->sideEffect);

        $this->sideEffect = null;

        $this->assertNull($this->sideEffect);
        $this->assertEquals('generated', $generator->generate('cache'));
        $this->assertEquals('cache', $this->sideEffect);
    }

    public function badMutexLockTimeoutsProvider()
    {
        return [
            [0.5],
            ['0.5'],
            ['a5'],
            ['5a'],
            ['timeout'],
            [-1],
            [-0.5],
        ];
    }

    public function goodMutexLockTimeoutsProvider()
    {
        return [
            [0, 0],
            ['0', 0],
            [10, 10],
            ['10', 10],
        ];
    }

    protected function callableGenerator()
    {
        $callable = function ($cache) {
            $this->sideEffect = $cache;
            return 'generated';
        };
        return RefreshAheadCacheBehavior::ensureGenerator($callable);
    }
}
