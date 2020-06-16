<?php

namespace thamtechunit\caching\refreshAhead;

use thamtech\caching\refreshAhead\RefreshAheadCacheBehavior;
use thamtech\caching\refreshAhead\RefreshAheadConfig;
use Yii;

class RefreshAheadConfigTest extends \thamtechunit\caching\refreshAhead\TestCase
{
    public $sideEffect = null;

    protected function setUp(): void
    {
        $this->sideEffect = null;
    }

    public function testEnsureCallable()
    {
        $config = $this->callableConfig();

        $this->assertEquals(0, $config->getMutexLockTimeout());

        // calling refresh on config generated from a single callable should
        // return false and not call the callable
        $this->assertFalse($config->refresh('cache'));
        $this->assertNull($this->sideEffect);

        $this->assertEquals('generated', $config->generate('cache'));
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
        $config = $this->callableConfig();

        $config->setMutexLockTimeout($value);
        $this->assertEquals($expected, $config->getMutexLockTimeout());
    }

    /**
     * @dataProvider goodMutexLockTimeoutsProvider
     */
    public function testAssignValidMutexLockTimeout($value, $expected)
    {
        $config = $this->callableConfig();

        $config->mutexLockTimeout = $value;
        $this->assertEquals($expected, $config->mutexLockTimeout);
    }

    /**
     * @dataProvider badMutexLockTimeoutsProvider
     */
    public function testSetInvalidMutexLockTimeout($value)
    {
        $config = $this->callableConfig();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('mutexLockTimeout must be an integer 0 or greater.');
        $config->setMutexLockTimeout($value);
    }

    /**
     * @dataProvider badMutexLockTimeoutsProvider
     */
    public function testAssignInvalidMutexLockTimeout($value)
    {
        $config = $this->callableConfig();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('mutexLockTimeout must be an integer 0 or greater.');
        $config->mutexLockTimeout = $value;
    }

    public function testSetRefreshNull()
    {
        $config = $this->callableConfig();
        $config->setRefresh(null);
        $this->assertFalse($config->refresh('cache'));
        $this->assertNull($this->sideEffect);
    }

    public function testSetRefreshBad()
    {
        $config = $this->callableConfig();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('refresh must be a callable.');
        $config->setRefresh('not a callable');
    }

    public function testSetGenerateNull()
    {
        $config = $this->callableConfig();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('generate must be a callable.');
        $config->setGenerate(null);
    }

    public function testInitNullGenerate()
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('You must specify a generate callable.');
        $config = Yii::createObject([
            'class' => 'thamtech\caching\refreshAhead\RefreshAheadConfig',
        ]);
    }

    public function testSetGenerateBad()
    {
        $config = $this->callableConfig();

        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('generate must be a callable.');
        $config->setGenerate('not a callable');
    }

    public function testCallables()
    {
        $config = RefreshAheadCacheBehavior::ensureGenerator([
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
        $this->assertTrue($config->refresh('cache'));
        $this->assertEquals('cache', $this->sideEffect);

        $this->sideEffect = null;

        $this->assertNull($this->sideEffect);
        $this->assertEquals('generated', $config->generate('cache'));
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

    protected function callableConfig()
    {
        $callable = function ($cache) {
            $this->sideEffect = $cache;
            return 'generated';
        };
        return RefreshAheadCacheBehavior::ensureGenerator($callable);
    }
}
