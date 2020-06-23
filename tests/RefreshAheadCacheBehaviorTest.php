<?php

namespace thamtechunit\caching\refreshAhead;

use thamtech\caching\refreshAhead\GeneratorInterface;
use thamtech\caching\refreshAhead\RefreshAheadCacheBehavior;
use thamtech\caching\refreshAhead\CallableGenerator;
use yii\base\InvalidConfigException;
use Yii;

class MockRefreshAheadCacheBehavior extends RefreshAheadCacheBehavior
{
    public function buildRefreshAheadKey($dataKey, $suffix)
    {
        return parent::buildRefreshAheadKey($dataKey, $suffix);
    }

    public function computeRefreshTimeoutDuration($dataDuration)
    {
        return parent::computeRefreshTimeoutDuration($dataDuration);
    }

    public function computeRefreshGeneratedDuration($dataDuration)
    {
        return parent::computeRefreshGeneratedDuration($dataDuration);
    }

    public function buildLockName($dataKey)
    {
        return parent::buildLockName($dataKey);
    }

    public function acquireLock(GeneratorInterface $generator, $dataKey, $timeout = null)
    {
        return parent::acquireLock($generator, $dataKey, $timeout);
    }

    public function releaseLock($dataKey)
    {
        return parent::releaseLock($dataKey);
    }
}

class MockBuildKeyCache extends \yii\caching\DummyCache
{
    public function buildKey($key)
    {
        return ['key' => $key];
    }

    public function set($key, $value, $duration = 0, $dependency = null)
    {
        return false;
    }
}

class MockJoinBuildKeyCache extends \yii\caching\DummyCache
{
    public function buildKey($key)
    {
        $key = (array)$key;
        return join("/", $key);
    }
}

class MockMutex extends \yii\mutex\Mutex
{
    public $acquireLockCalls = [];
    public $releaseLockCalls = [];

    public $acquireResponses = [];
    public $releaseResponses = [];

    public function resetMock()
    {
        foreach ($this->acquireLockCalls as $call) {
            $this->releaseResponses[$call['name']] = true;
            $this->release($call['name']);
        }

        foreach (['acquireLockCalls', 'releaseLockCalls', 'acquireResponses', 'releaseResponses'] as $prop) {
            $this->$prop = [];
        }
    }

    protected function acquireLock($name, $timeout = 0)
    {
        $this->acquireLockCalls[] = ['name' => $name, 'timeout' => $timeout];
        if (isset($this->acquireResponses[$name])) {
            if (is_array($this->acquireResponses[$name])) {
                return array_shift($this->acquireResponses[$name]);
            }
            return $this->acquireResponses[$name];
        }

        return false;
    }

    protected function releaseLock($name)
    {
        $this->releaseLockCalls[] = $name;
        if (isset($this->releaseResponses[$name])) {
            if (is_array($this->releaseResponses[$name])) {
                return array_shift($this->releaseResponses[$name]);
            }
            return $this->releaseResponses[$name];
        }
        return false;
    }
}

class MockLogger extends \yii\base\BaseObject
{
    public $logs = [];

    public function log($message, $level, $category)
    {
        $this->logs[] = [$message, $level, $category];
    }
}

class RefreshAheadCacheBehaviorTest extends \thamtechunit\caching\refreshAhead\TestCase
{
    /**
     * @dataProvider badInitProvider
     */
    public function testBadInit($config, $exception, $exceptionMessage)
    {
        $this->expectException($exception);
        $this->expectExceptionMessage($exceptionMessage);
        Yii::createObject($config);
    }

    public function testSetCache()
    {
        $cache = [
            'class' => 'yii\caching\DummyCache',
        ];

        $behavior = Yii::createObject(RefreshAheadCacheBehavior::class);
        $behavior->setCache($cache);

        $this->assertInstanceOf(\yii\caching\DummyCache::class, $behavior->getDataCache());
        $this->assertInstanceOf(\yii\caching\DummyCache::class, $behavior->getRefreshTimeoutCache());
        $this->assertSame($behavior->getDataCache(), $behavior->getRefreshTimeoutCache());
    }

    public function testEnsureCacheNull()
    {
        $behavior = Yii::createObject([
            'class' => MockRefreshAheadCacheBehavior::class,
            'cache' => null,
        ]);
        $behavior->setCache(null);
        $behavior->setCache(false);
        $cache = [
            'class' => 'yii\caching\DummyCache',
        ];
        $behavior->setCache($cache);

        $this->assertInstanceOf(\yii\caching\DummyCache::class, $behavior->getRefreshTimeoutCache());
        $this->assertInstanceOf(\yii\caching\DummyCache::class, $behavior->getDataCache());

    }

    public function testAcquireRelaseLock()
    {
        $mutex = Yii::createObject(MockMutex::class);
        $behavior = Yii::createObject([
            'class' => MockRefreshAheadCacheBehavior::class,
            'mutex' => $mutex,
            'dataCache' => MockJoinBuildKeyCache::class,
            'refreshTimeoutCache' => MockJoinBuildKeyCache::class,
        ]);
        $generator = RefreshAheadCacheBehavior::ensureGenerator([
            'generate' => function ($cache) {},
            'mutexLockTimeout' => 12,
        ]);

        $mutex->acquireResponses['test1/refresh-ahead-generate'] = true;
        $this->assertTrue($behavior->acquireLock($generator, 'test1'));
        $this->assertEquals([['name' => 'test1/refresh-ahead-generate', 'timeout' => 12]], $mutex->acquireLockCalls);

        $mutex->releaseResponses['test1/refresh-ahead-generate'] = true;
        $this->assertTrue($behavior->releaseLock('test1'));
        $this->assertEquals(['test1/refresh-ahead-generate'], $mutex->releaseLockCalls);
        $mutex->resetMock();

        $mutex->acquireResponses['test1/refresh-ahead-generate'] = false;
        $this->assertFalse($behavior->acquireLock($generator, 'test1'));
        $this->assertEquals([['name' => 'test1/refresh-ahead-generate', 'timeout' => 12]], $mutex->acquireLockCalls);
        $mutex->resetMock();
    }

    public function testAcquireReleaseLockNoMutex()
    {
        $behavior = Yii::createObject(MockRefreshAheadCacheBehavior::class);
        $generator = RefreshAheadCacheBehavior::ensureGenerator(function ($cache) {});

        $this->assertTrue($behavior->acquireLock($generator, 'test-key'));
        $this->assertTrue($behavior->releaseLock('test-key'));
    }

    /**
     * @dataProvider lockNameProvider
     */
    public function testBuildLockName($dataKey, $expected)
    {
        $behavior = Yii::createObject([
            'class' => MockRefreshAheadCacheBehavior::class,
            'refreshTimeoutCache' => MockBuildKeyCache::class,
        ]);
        $this->assertEquals($expected, $behavior->buildLockName($dataKey));
    }

    public function lockNameProvider()
    {
        $obj = Yii::createObject(\yii\caching\DummyCache::class);
        return [
            ['str', ['key' => 'str/refresh-ahead-generate']],
            [['abc', 123], ['key' => ['abc', '123', 'refresh-ahead-generate']]],
            [$obj, ['key' => ['refresh-ahead-generate', $obj]]],
        ];
    }

    /**
     * @dataProvider refreshAheadKeyProvider
     */
    public function testBuildRefreshAheadKey($dataKey, $expected)
    {
        $behavior = Yii::createObject([
            'class' => MockRefreshAheadCacheBehavior::class,
        ]);
        $this->assertEquals($expected, $behavior->buildRefreshAheadKey($dataKey, 'suffix-str'));
    }

    public function refreshAheadKeyProvider()
    {
        $obj = Yii::createObject(\yii\caching\DummyCache::class);
        return [
            ['str', 'str/suffix-str'],
            [['abc', 123], ['abc', '123', 'suffix-str']],
            [$obj, ['suffix-str', $obj]],
        ];
    }

    /**
     * @dataProvider computeRefreshTimeoutDurationProvider
     */
    public function testComputeRefreshTimeoutDuration($dataDuration, $refreshAheadFactor, $dataCache, $expected)
    {
        $behavior = Yii::createObject([
            'class' => MockRefreshAheadCacheBehavior::class,
            'dataCache' => $dataCache,
            'refreshAheadFactor' => $refreshAheadFactor,
        ]);
        $this->assertEquals($expected, $behavior->computeRefreshTimeoutDuration($dataDuration));
    }

    public function computeRefreshTimeoutDurationProvider()
    {
        $defaultCache = Yii::createObject('\yii\caching\DummyCache');
        $tenCache = Yii::createObject([
            'class' => 'yii\caching\DummyCache',
            'defaultDuration' => 10,
        ]);

        return [
            [null, 0.5, $defaultCache, 0],
            [0, 0.5, $defaultCache, 0],
            [5, 0.5, $defaultCache, 3],
            [6, 0.5, $defaultCache, 3],
            [100, 0.75, $defaultCache, 75],
            [100, 1.5, $defaultCache, 100],

            [null, 0.5, $tenCache, 5],
            [null, 0.333, $tenCache, 4],
        ];
    }

    /**
     * @dataProvider computeRefreshGeneratedDurationProvider
     */
    public function testComputeRefreshGeneratedDuration($dataDuration, $refreshGeneratedFactor, $dataCache, $expected)
    {
        $behavior = Yii::createObject([
            'class' => MockRefreshAheadCacheBehavior::class,
            'dataCache' => $dataCache,
            'refreshGeneratedFactor' => $refreshGeneratedFactor,
        ]);
        $this->assertEquals($expected, $behavior->computeRefreshGeneratedDuration($dataDuration));
    }

    public function computeRefreshGeneratedDurationProvider()
    {
        $defaultCache = Yii::createObject('\yii\caching\DummyCache');
        $tenCache = Yii::createObject([
            'class' => 'yii\caching\DummyCache',
            'defaultDuration' => 10,
        ]);

        return [
            [null, null, $defaultCache, 0],
            [0, null, $defaultCache, 0],
            [5, null, $defaultCache, 1],
            [6, null, $defaultCache, 1],
            [100, null, $defaultCache, 25],

            [null, 0.5, $defaultCache, 0],
            [0, 0.5, $defaultCache, 0],
            [5, 0.5, $defaultCache, 2],
            [6, 0.5, $defaultCache, 3],
            [100, 0.2, $defaultCache, 20],
            [100, 0.4, $defaultCache, 40],

            [null, 0.5, $tenCache, 5],
            [null, 0.333, $tenCache, 3],
        ];
    }

    /**
     * @dataProvider badCacheProvider
     */
    public function testSetDataCacheBad($cache, $exceptionMessage)
    {
        $behavior = Yii::createObject(RefreshAheadCacheBehavior::class);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $behavior->setDataCache($cache);
    }

    public function badCacheProvider()
    {
        return [
            [
                'unknownComponentId',
                'Failed to instantiate component or class "unknownComponentId".',
            ],

            [
                [
                    'class' => 'yii\mutex\FileMutex',
                ],
                'Invalid data type: yii\mutex\FileMutex. \yii\caching\CacheInterface is expected.',
            ],
        ];
    }

    public function badInitProvider()
    {
        return [
            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshAheadFactor' => 'abc',
                ],
                InvalidConfigException::class,
                'The refreshAheadFactor must be a non-negative float. Typically, it should be between 0 and 1.',
            ],

            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshAheadFactor' => -1,
                ],
                InvalidConfigException::class,
                'The refreshAheadFactor must be a non-negative float. Typically, it should be between 0 and 1.',
            ],

            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshAheadFactor' => '-1',
                ],
                InvalidConfigException::class,
                'The refreshAheadFactor must be a non-negative float. Typically, it should be between 0 and 1.',
            ],

            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshGeneratedFactor' => 'abc',
                ],
                InvalidConfigException::class,
                'The refreshGeneratedFactor must be a non-negative float. Typically, it should be between 0 and 1 and less than (1.0 - refreshAheadFactor).',
            ],

            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshGeneratedFactor' => -1,
                ],
                InvalidConfigException::class,
                'The refreshGeneratedFactor must be a non-negative float. Typically, it should be between 0 and 1 and less than (1.0 - refreshAheadFactor).',
            ],

            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshGeneratedFactor' => '-1',
                ],
                InvalidConfigException::class,
                'The refreshGeneratedFactor must be a non-negative float. Typically, it should be between 0 and 1 and less than (1.0 - refreshAheadFactor).',
            ],

            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshGeneratedFactor' => 0.6,
                ],
                InvalidConfigException::class,
                'The refreshGeneratedFactor must be a non-negative float. Typically, it should be between 0 and 1 and less than (1.0 - refreshAheadFactor).',
            ],


            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshTimeoutKeySuffix' => null,
                ],
                InvalidConfigException::class,
                'refreshTimeoutKeySuffix is required.',
            ],

            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshTimeoutKeySuffix' => false,
                ],
                InvalidConfigException::class,
                'refreshTimeoutKeySuffix is required.',
            ],

            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'refreshTimeoutKeySuffix' => '',
                ],
                InvalidConfigException::class,
                'refreshTimeoutKeySuffix is required.',
            ],


            [
                [
                    'class' => RefreshAheadCacheBehavior::class,
                    'mutex' => 'mutex',
                ],
                InvalidConfigException::class,
                'Failed to instantiate component or class "mutex".',
            ],
        ];
    }

    public function testGenerateAndSetFailure()
    {
        $logger = Yii::createObject(MockLogger::class);
        Yii::setLogger($logger);

        $dataCache = Yii::createObject([
            'class' => MockBuildKeyCache::class, // returns false on `set()`
            'serializer' => false,
            'as refreshAhead' => [
                'class' => MockRefreshAheadCacheBehavior::class,
                'refreshTimeoutCache' => MockJoinBuildKeyCache::class,
                'mutex' => MockMutex::class,
            ],
        ]);

        $refreshCalled = false;
        $refreshResponse = true;
        $generateCalled = false;
        $generateResponse = 'test value';

        $generator = [
            'refresh' => function ($cache) use (&$refreshCalled, &$refreshResponse) {
                $refreshCalled = true;
                return $refreshResponse;
            },
            'generate' => function ($cache) use (&$generateCalled, &$generateResponse) {
                $generateCalled = true;
                return $generateResponse;
            },
            'mutexLockTimeout' => 2,
        ];

        $lockName = $dataCache->getRefreshTimeoutCache()->buildKey('test1/refresh-ahead-generate');
        $dataCache->mutex->acquireResponses[$lockName] = true;
        $dataCache->mutex->releaseResponses[$lockName] = true;
        $result = $dataCache->generateAndSet('test1', RefreshAheadCacheBehavior::ensureGenerator($generator), 10);
        $this->assertEquals([['name' => $lockName, 'timeout' => 0]], $dataCache->mutex->acquireLockCalls);
        $this->assertEquals([$lockName], $dataCache->mutex->releaseLockCalls);
        $this->assertFalse($refreshCalled);
        $this->assertTrue($generateCalled);
        $this->assertEquals($result, 'test value');
        $this->assertEquals([['Failed to set cache value for key s:5:"test1";', 2, 'thamtech\caching\refreshAhead\RefreshAheadCacheBehavior::generateAndSet']], $logger->logs);
    }

    public function testGetRefreshOrSet()
    {
        $refreshTimeoutCache = Yii::createObject([
            'class' => 'yii\caching\ArrayCache',
            'serializer' => false,
        ]);
        $dataCache = Yii::createObject([
            'class' => 'yii\caching\ArrayCache',
            'serializer' => false,
            'as refreshAhead' => [
                'class' => MockRefreshAheadCacheBehavior::class,
                'refreshTimeoutCache' => $refreshTimeoutCache,
            ],
        ]);

        $refreshCalled = false;
        $refreshResponse = true;
        $generateCalled = false;
        $generateResponse = 'test value';

        $generator = [
            'refresh' => function ($cache) use (&$refreshCalled, &$refreshResponse) {
                $refreshCalled = true;
                return $refreshResponse;
            },
            'generate' => function ($cache) use (&$generateCalled, &$generateResponse) {
                $generateCalled = true;
                return $generateResponse;
            },
            'mutexLockTimeout' => 2,
        ];

        $result = $dataCache->getRefreshOrSet('test1', $generator, 10);
        $this->assertFalse($refreshCalled);
        $this->assertTrue($generateCalled);
        $this->assertEquals($result, 'test value');

        // now that 'test value' is cached, let's change the generate
        // response and ask again and make sure we get the
        // cached 'test value' back
        $generateCalled = false;
        $generateResponse = 'test value 2';
        $result = $dataCache->getRefreshOrSet('test1', $generator, 10);
        $this->assertFalse($refreshCalled);
        $this->assertFalse($generateCalled);
        $this->assertEquals($result, 'test value');

        // by manually clearing the refreshTimeoutCache, we can test
        // the refresh operation
        $refreshTimeoutCache->flush();
        $result = $dataCache->getRefreshOrSet('test1', $generator, 10);
        $this->assertTrue($refreshCalled);
        $this->assertFalse($generateCalled);
        $this->assertEquals($result, 'test value');

        // since refresh was just called, it shouldn't be called
        // immediately after: ensure that refresh() is not called
        // this time
        $refreshCalled = false;
        $result = $dataCache->getRefreshOrSet('test1', $generator, 10);
        $this->assertFalse($refreshCalled);
        $this->assertFalse($generateCalled);
        $this->assertEquals($result, 'test value');

        // by manually clearing the dataCache, we can test the
        // generate operation even when refresh is not needed
        $dataCache->flush();
        $result = $dataCache->getRefreshOrSet('test1', $generator, 10);
        $this->assertFalse($refreshCalled);
        $this->assertTrue($generateCalled);
        $this->assertEquals($result, 'test value 2');

        // now that 'test value 2' is cached, let's change the generate
        // response and ask again and make sure we get the
        // cached 'test value 2' back
        $generateCalled = false;
        $generateResponse = 'test value 3';
        $result = $dataCache->getRefreshOrSet('test1', $generator, 10);
        $this->assertFalse($refreshCalled);
        $this->assertFalse($generateCalled);
        $this->assertEquals($result, 'test value 2');

        // by manually clearing the refreshTimeoutCache, we can test
        // the refresh operation; this time we want `refresh` to return
        // false to ensure that the behavior deletes the refresh timeout key
        $key = $refreshTimeoutCache->buildKey('test1/refresh-ahead-timeout');
        $this->assertTrue($refreshTimeoutCache->exists($key));
        $this->assertTrue($refreshTimeoutCache->get($key));
        $refreshTimeoutCache->flush();
        $refreshResponse = false;
        $result = $dataCache->getRefreshOrSet('test1', $generator, 10);
        $this->assertTrue($refreshCalled);
        $this->assertFalse($generateCalled);
        $this->assertEquals($result, 'test value 2');
        $this->assertFalse($refreshTimeoutCache->exists($key));
        $this->assertFalse($refreshTimeoutCache->get($key));
        $refreshCalled = false;

        // by manually clearing the dataCache, we can test the
        // generateAndSet operation with a mutex
        $dataCache->mutex = Yii::createObject(MockMutex::class);
        $lockName = $dataCache->buildKey('test1/refresh-ahead-generate');
        $dataCache->mutex->acquireResponses[$lockName] = false;
        $dataCache->mutex->releaseResponses[$lockName] = true;
        $result = $dataCache->generateAndSet('test1', RefreshAheadCacheBehavior::ensureGenerator($generator), 10);
        $this->assertEquals([['name' => $lockName, 'timeout' => 0], ['name' => $lockName, 'timeout' => 2]], $dataCache->mutex->acquireLockCalls);
        $this->assertEquals([$lockName], $dataCache->mutex->releaseLockCalls);
        $this->assertFalse($refreshCalled);
        $this->assertTrue($generateCalled);
        $this->assertEquals($result, 'test value 3');
        $generateCalled = false;

        // by manually clearing the dataCache, we can test the
        // generateAndSet operation with a mutex
        $dataCache->mutex = Yii::createObject(MockMutex::class);
        $lockName = $dataCache->buildKey('test1/refresh-ahead-generate');
        $dataCache->mutex->acquireResponses[$lockName] = true;
        $dataCache->mutex->releaseResponses[$lockName] = true;
        $result = $dataCache->generateAndSet('test1', RefreshAheadCacheBehavior::ensureGenerator($generator), 10);
        $this->assertEquals([['name' => $lockName, 'timeout' => 0]], $dataCache->mutex->acquireLockCalls);
        $this->assertEquals([$lockName], $dataCache->mutex->releaseLockCalls);
        $this->assertFalse($refreshCalled);
        $this->assertTrue($generateCalled);
        $this->assertEquals($result, 'test value 3');
        $generateCalled = false;

        // test that the recently cached data value is returned rather than
        // calling generate
        $dataCache->mutex = Yii::createObject(MockMutex::class);
        $lockName = $dataCache->buildKey('test1/refresh-ahead-generate');
        $dataCache->mutex->acquireResponses[$lockName] = [false, true];
        $dataCache->mutex->releaseResponses[$lockName] = true;
        $result = $dataCache->generateAndSet('test1', RefreshAheadCacheBehavior::ensureGenerator($generator), 10);
        $this->assertEquals([['name' => $lockName, 'timeout' => 0], ['name' => $lockName, 'timeout' => 2]], $dataCache->mutex->acquireLockCalls);
        $this->assertEquals([$lockName], $dataCache->mutex->releaseLockCalls);
        $this->assertFalse($refreshCalled);
        $this->assertFalse($generateCalled);
        $this->assertEquals($result, 'test value 3');
    }
}
