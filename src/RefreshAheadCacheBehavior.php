<?php
/**
 * @copyright Copyright(c) 2019 Thamtech, LLC
 * @link https://github.com/thamtech/yii2-refresh-ahead-cache
 * @license https://opensource.org/licenses/BSD-3-Clause
**/

namespace thamtech\caching\refreshAhead;

use yii\base\Behavior;
use yii\base\InvalidConfigException;
use thamtech\di\Instance;
use Yii;

/**
 * RefreshAheadCacheBehavior is a cache or component decorator adding
 * refresh-ahead caching.
 *
 * Refresh-Ahead caching is a strategy where a cached value's refresh is
 * triggered before it expires from cache. By default, this refresh is triggered
 * once the cached value is half way to its expiration. This factor is called
 * the [[refreshAheadFactor]] and defaults to `0.5`.
 *
 * If supported by your application, you can provide a callable to
 * asynchronously refresh the data value. This way, even when a refresh is
 * triggered, the already cached value can continue to be served until the
 * refresh has actually been completed.
 *
 * If the data value has expired or is no longer present, it must be generated
 * synchronously. If supported by your application, you can specify a mutex
 * component that will be used to acquire a lock before considering whether to
 * synchronously generate a data value. If another process has a lock and is
 * already generating a new data value, we can just wait for the log to be
 * released and then re-check for the newly generated value in cache rather
 * than redundantly generating it ourselves.
 *
 * @property CacheInterface dataCache the data storage cache object, used for
 *     caching data values.
 *
 * @property CacheInterface refreshTimeoutCache the refresh timeout cache
 *     object, used for caching refresh timeout keys so we know when a data
 *     value should be refreshed.
 *
 * @author Tyler Ham <tyler@thamtech.com>
 */
class RefreshAheadCacheBehavior extends Behavior
{
    /**
     * @var float the fraction of a data value's duration that should pass
     *     before a refresh is triggered.
     *
     *     This should be a value from 0.0 to 1.0. A value of 0 will force an
     *     asynchronous refresh on every request. A value of 1.0 or greater
     *     effectively disables refreshing-ahead of time since the data value
     *     would expire before the refresh timeout could trigger a refresh
     *     ahead of time.
     */
    public $refreshAheadFactor = 0.5;

    /**
     * @var string a suffix that will be appended to the data value's key to
     *     form the refresh timeout key, which is used to track when it is time
     *     to refresh-ahead the data value.
     *
     *     We want the refresh timeout key to be different from the data key in
     *     case the same cache component is used for both data and refresh
     *     timeout keys.
     *
     *     When the data value's key is an array, this suffix will be appended
     *     as a new element at the end of the array. When the data value's key
     *     is a string, this suffix will be concatenated at the end. Otherwise,
     *     the data value's key will be serialized using the
     *     [[refreshTimeoutCache]]'s [[yii\caching\Cache::buildKey()]] method
     *     and this suffix will be concatenated at the end.
     *
     *     This suffix must be a non-empty string.
     */
    public $refreshTimeoutKeySuffix = 'refresh-ahead-timeout';

    /**
     * @var string a suffix that will be appended to the data value's key to
     *     form the mutex lock name when synchronously generating a data value.
     */
    public $mutexLockKeySuffix = 'refresh-ahead-generate';

    /**
     * @var Mutex|string|array a mutex component, configuration array, or ID of
     *     an application mutex component. (optional)
     */
    public $mutex;

    /**
     * @var CacheInterface the data storage cache object, used for caching data
     *     values.
     *
     *     By default (or if null), this behavior's [[owner]] is expected to
     *     implement [[CacheInterface]] and will be used.
     */
    private $_dataCache;

    /**
     * @var CacheInterface the refresh timeout cache object, used for caching
     *     refresh timeout keys so we know when a data value should be
     *     refreshed.
     *
     *     By default (or if null), this behavior's [[owner]] is expected to
     *     implement [[CacheInterface]] and will be used.
     */
    private $_refreshTimeoutCache;

    /**
     * Resolves the specified reference into a [[GernatorInterface]]
     * object.
     *
     * The reference may be specified as a configuration array for a
     * [[GernatorInterface]] object. A [[CallableGenerator]] is assumed by
     * default if the `class` is not specified. In this case, you must specify
     * both `refresh` and `generate` callables.
     *
     * The reference may be specified as a callable or \Closure, which will be
     * treated as as a synchronous `generate` callable (asynchronous refreshing
     * is not available in this case). The result will be a
     * [[CallableGenerator]] with only the `generate` callable implemented.
     *
     * The reference may also be a string or an Instance object. If the former,
     * it will be treated as an application component ID or a class name.
     *
     * @param  callable|\Closure|array|GernatorInterface|string|Instance $reference an
     *     object or reference to the desired object.
     *
     *     You may specify a reference in terms of an application component ID,
     *     an Instance object, a GernatorInterface object, or a configuration
     *     array for creating the object. If the "class" value is not specified
     *     in the configuration array, it will use the value of
     *     'thamtech\caching\refreshAhead\CallableGenerator'.
     *
     * @return GernatorInterface the object instance
     * @throws InvalidConfigException if the reference is invalid
     */
    public static function ensureGenerator($reference)
    {
        if (is_callable($reference)) {
            $reference = [
                'generate' => $reference,
            ];
        }

        return Instance::ensureAny($reference, [
            CallableGenerator::class,
            GeneratorInterface::class,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if (!is_numeric($this->refreshAheadFactor) || $this->refreshAheadFactor < 0) {
            throw new InvalidConfigException('The refreshAheadFactor must be a non-negative float. Typically, it should be between 0 and 1.');
        }

        if (empty($this->refreshTimeoutKeySuffix)) {
            throw new InvalidConfigException('refreshTimeoutKeySuffix is required.');
        }

        if (!empty($this->mutex)) {
            $this->mutex = Instance::ensure($this->mutex, 'yii\mutex\Mutex');
        }
    }

    /**
     * Sets both [[dataCache]] and [[refreshTimeoutCache]] components.
     *
     * @param array|string|CacheInterface|Instance $cache the cache component or
     *     reference to the desired cache component.
     *
     *     The cache may be specified as a configuration array for a
     *     [[CacheInterface]], the string name of an application component ID,
     *     an Instance object, or a [[CacheInterface]] object.
     *
     * @see setDataCache()
     * @see setRefreshTimeoutCache()
     */
    public function setCache($cache)
    {
        $cache = $this->ensureCache($cache, false);
        $this->setDataCache($cache);
        $this->setRefreshTimeoutCache($cache);
    }

    /**
     * Sets the cache component to use for storing data values.
     *
     * @param array|string|CacheInterface|Instance $cache the cache component.
     *     Please refer to [[setCache()]] on how to specify this parameter.
     *
     * @see setCache()
     * @see setRefreshTimeoutCache()
     */
    public function setDataCache($cache)
    {
        $this->_dataCache = $this->ensureCache($cache, false);
    }

    /**
     * Gets the data cache component
     *
     * @return CacheInterface|null the data cache component
     */
    public function getDataCache()
    {
        return $this->ensureCache($this->_dataCache);
    }

    /**
     * Sets the cache component to use for storing refresh timeout keys so we
     * know when a data value should be refreshed.
     *
     * @param array|string|CacheInterface|Instance $cache the cache component.
     *     Please refer to [[setCache()]] on how to specify this parameter.
     *
     * @see setCache()
     * @see setDataCache()
     */
    public function setRefreshTimeoutCache($cache)
    {
        $this->_refreshTimeoutCache = $this->ensureCache($cache, false);
    }

    /**
     * Gets the refresh timeout cache component.
     *
     * @return CacheInterface|null the data cache component
     */
    public function getRefreshTimeoutCache()
    {
        return $this->ensureCache($this->_refreshTimeoutCache);
    }

    /**
     * Combination of [[CacheInterface::set()]] and [[CacheInterface::get()]]
     * and a refresh-ahead strategy.
     *
     * @param mixed $key a key identifying the value to be cached. This can be
     *     a simple string or a complex data structure consisting of factors
     *     representing the key.
     *
     * @param callable|\Closure|GeneratorInterface|array|Instance $generator
     *     A GeneratorInterface object, configuration array, or application
     *     component ID, or a callable that will be used to synchronously
     *     generate and refresh the data value to be cached.
     *     In case the callable returns `false`, the value will not be cached.
     *
     * @param int $duration default duration in seconds before the cache will
     *     expire. If not set, [[defaultDuration]] value from the [[dataCache]]
     *     component will be used. The [[refreshAheadFactor]] will be used
     *     with the $duration to determine how long before the data value
     *     should be refreshed.
     *
     * @param Dependency $dependency dependency of the cached item. If the
     *     dependency changes, the corresponding value in the cache will be
     *     invalidated when it is fetched via [[get()]]. This parameter is
     *     ignored if [[serializer]] in the [[dataCache]] component is `false`.
     *
     * @return mixed generated data value
     */
    public function getRefreshOrSet($key, $generator, $duration = null, $dependency = null)
    {
        $refreshTimeoutKey = $this->buildRefreshAheadKey($key, $this->refreshTimeoutKeySuffix);
        $refreshTimeoutDuration = $this->computeRefreshTimeoutDuration($duration);


        // This 'add' operation on the refreshTimeoutCache is both
        // 1. setting the refresh timeout key that will expire when it is time
        //    to initiate the next refresh, and
        // 2. determine if the key was not set (or had expired), indicating that
        //    a refresh is due
        //
        // We want 1. to happen regardless of whether the data value is
        // currently available in cache to be asynchronously refreshed, since
        // we're about to generate and add it if it isn't. Therefere, we execute
        // this here rather than nested in the if ($value !== false) branch
        // below.
        $needsRefresh = $this->getRefreshTimeoutCache()
            ->add($refreshTimeoutKey, true, $refreshTimeoutDuration, $dependency);

        $value = $this->getDataCache()->get($key);

        if ($value !== false) {
            if ($needsRefresh) {
                $generator = $this->ensureGenerator($generator);
                if (!$generator->refresh($this->getDataCache())) {
                    // refresh was not queued for some reason; unset the
                    // refreshTimeout key so that a subsequent request will try
                    // again to trigger the refresh
                    $this->getRefreshTimeoutCache()->delete($refreshTimeoutKey);
                }
            }

            // since the data value from cache still exists and is unexpired,
            // we can go ahead and return it to the user now and not worry about
            // having to generate it synchronously.
            return $value;
        }

        return $this->generateAndSet(
            $key,
            $generator,
            $duration,
            $dependency
        );
    }

    /**
     * Combination of [[CacheInterface::set()]] and [[CacheInterface::get()]]
     * with support for a mutex lock on the generation of the data value.
     *
     * The expectation is that this method is not called unless
     * we've already checked for and not found the value in the data cache. This
     * method attempts to acquire a mutex lock (if a mutex component is
     * configured) while generating the data value, and we wouldn't want the
     * overhead of acquiring a lock everytime the value needed to be fetched
     * from cache.
     *
     * First, this method attempts to acquire a lock. Another process that may
     * have held the lock previously may have generated the value and set it in
     * cache. If we acquire the lock (whether immediately or after a delay until
     * another process released it), we immediately check to see if the value is
     * now in the data cache. If so, we releas the lock and return the cached
     * value.
     *
     * Otherwise, we generate the value, set it in cache, release the
     * lock, and return the value.
     *
     *
     * @param mixed $key a key identifying the value to be cached. This can be
     *     a simple string or a complex data structure consisting of factors
     *     representing the key.
     *
     * @param callable|\Closure|GeneratorInterface|array|Instance $generator
     *     A GeneratorInterface object, configuration array, or application
     *     component ID, or a callable that will be used to synchronously
     *     generate the data value to be cached.
     *     In case the callable returns `false`, the value will not be cached.
     *
     * @param int $duration default duration in seconds before the cache will
     *     expire. If not set, [[defaultDuration]] value from the [[dataCache]]
     *     component will be used.
     *
     * @param Dependency $dependency dependency of the cached item. If the
     *     dependency changes, the corresponding value in the cache will be
     *     invalidated when it is fetched via [[get()]]. This parameter is
     *     ignored if [[serializer]] in the [[dataCache]] component is `false`.
     *
     * @return mixed generated data value
     */
    public function generateAndSet($key, $generator, $duration = null, $dependency = null)
    {
        $generator = $this->ensureGenerator($generator);

        // The value needs to be generated, but it is possible another process
        // has already started generating it but it was not yet in cache when
        // we just checked.
        //
        // We will acquire a lock (if a mutex component was specified) before
        // attempting to generate.
        if ($this->acquireLock($generator, $key)) {
            // lock was acquired, possibly after waiting for another process to
            // finish. Let's check cache once more:
            if (($value = $this->getDataCache()->get($key)) !== false) {
                $this->releaseLock($key);
                return $value;
            }
        }

        // Generate the value synchronously
        $value = $generator->generate($this->getDataCache());

        // we promised not to set the value in cache if the generator returned `false`
        if ($value !== false) {
            $setCacheResult = $this->getDataCache()->set($key, $value, $duration, $dependency);
            if (!$setCacheResult) {
                Yii::warning('Failed to set cache value for key ' . serialize($key), __METHOD__);
            }
        }

        $this->releaseLock($key);
        return $value;
    }

    /**
     * Acquires a lock by data key.
     *
     * A lock is not acquired (and this method returns false) if a mutex
     * component is not configured.
     *
     * @param  GeneratorInterface $generator the generator
     *     which specifies the acquire timeout.
     *
     * @param  mixed $dataKey the key used to store the data value
     *
     * @return bool lock acquiring result
     */
    protected function acquireLock(GeneratorInterface $generator, $dataKey)
    {
        if (empty($this->mutex)) {
            return false;
        }

        $lockName = $this->buildLockName($dataKey);
        $timeout = $generator->getMutexLockTimeout();
        return $this->mutex->acquire($lockName, $timeout);
    }

    /**
     * Releases acquired lock by data key.
     *
     * @param  mixed $dataKey the key used to store the data value
     *
     * @return bool lock release result
     */
    protected function releaseLock($dataKey)
    {
        if (empty($this->mutex)) {
            return false;
        }

        $lockName = $this->buildLockName($dataKey);
        return $this->mutex->release($lockName);
    }

    /**
     * Build a lock name from the data key.
     *
     * @param  mixed $dataKey the key used to store the data value
     *
     * @return string mutex lock name
     */
    protected function buildLockName($dataKey)
    {
        $lockKey = $this->buildRefreshAheadKey($dataKey, $this->mutexLockKeySuffix);
        return $this->getRefreshTimeoutCache()->buildKey($lockKey);
    }

    /**
     * Computes the refresh timeout duration for the given data duration.
     *
     * The refresh timeout duration is a fraction of the data duration. The
     * fraction is specified in the [[refreshAheadFactor]].
     *
     * @param  int|null $dataDuration the duration that the data value will be
     *     cached for.
     *
     * @return int the duration of the refresh timeout key, which will mark when
     *     the data value should be refreshed.
     */
    protected function computeRefreshTimeoutDuration($dataDuration)
    {
        if ($dataDuration === null) {
            $dataDuration = $this->getDataCache()->defaultDuration;
        }

        if ($dataDuration) {
            return ceil($dataDuration * $this->refreshAheadFactor);
        }

        return 0;
    }

    /**
     * Build a refresh ahead key from the given data value key.
     *
     * If $dataKey is an array, we append the $suffix to
     * the end of the array. If $dataKey is a scalar, we concatenate
     * the $suffix to the end with a '/' separator.
     * Otherwise, we form a new array with the $suffix as
     * the first element and the original $dataKey as the second element.
     *
     * @param  mixed $dataKey the key used to store the data value
     *
     * @return array a key for storing the refresh timeout
     */
    protected function buildRefreshAheadKey($dataKey, $suffix)
    {
        if (is_array($dataKey)) {
            return array_merge($dataKey, [$suffix]);
        }

        if (is_scalar($dataKey)) {
            return $dataKey . '/' . $suffix;
        }

        return [$suffix, $dataKey];
    }

    /**
     * Ensure that the given parameter is a [[CacheInterface]].
     *
     * If $cache is null and this behavior has an [[owner]], then the owner is
     * ensured to be a [[CacheInterface]] and returned.
     *
     * If $cache is null and there is no [[owner]], then null is returned.
     *
     * @param array|string|CacheInterface|Instance $cache the cache component.
     *     Please refer to [[setCache()]] on how to specify this parameter.
     *
     * @param bool $required whether to require a cache object or allow null
     *     to be returned.
     *
     * @return CacheInterface|null the cache object. Null is returned if
     *     $required is false and both $cache and this behavior's owner are
     *     empty.
     *
     * @throws InvalidConfigException if a provided non-empty $cache is not a
     *     [[CacheInterface]] or if $cache is empty and the owner is non-empty
     *     and also not a [[CacheInterface]].
     */
    private function ensureCache($cache, $required = true)
    {
        if (!empty($cache)) {
            return Instance::ensure($cache, '\yii\caching\CacheInterface');
        }

        if ($required || !empty($this->owner)) {
            return Instance::ensure($this->owner, '\yii\caching\CacheInterface');
        }

        return null;
    }
}
