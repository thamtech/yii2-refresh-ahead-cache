<?php
/**
 * @copyright Copyright(c) 2019-2020 Thamtech, LLC
 * @link https://github.com/thamtech/yii2-refresh-ahead-cache
 * @license https://opensource.org/licenses/BSD-3-Clause
**/

namespace thamtech\caching\refreshAhead;

/**
 * GeneratorInterface is implemented by cache value generators and refreshers.
 *
 * @author Tyler Ham <tyler@thamtech.com>
 */
interface GeneratorInterface
{
    /**
     * Gets the mutex lock timeout (in seconds).
     *
     * A value of 0 means that we should not wait at all if another process
     * has acquired the lock. The result will be an immediate call to the
     * `generate` method.
     *
     * If this timeout is positive and a `mutex` component is configured in the
     * [[RefreshAheadCacheBehavior]], then we should wait this number of seconds
     * for another process to release the lock before calling `generate`
     * ourselves.
     *
     * @return int the mutex lock timeout
     */
    public function getMutexLockTimeout();

    /**
     * Trigger or queue an asynchronous refresh of the data value.
     *
     * @param  yii\Caching\CacheInterface $cache the data cache component
     *
     * @param mixed $key a key identifying the value to be cached. This can be
     *     a simple string or a complex data structure consisting of factors
     *     representing the key.
     *
     * @param int $duration default duration in seconds to cache the refreshed
     *     value.
     *
     * @param Dependency $dependency dependency of the cached item. If the
     *     dependency changes, the corresponding value in the cache will be
     *     invalidated when it is fetched via [[get()]]. This parameter is
     *     ignored if [[serializer]] in the
     *     [[RefreshAheadCacheBehavior::dataCache]] component is `false`.
     *
     * @return bool true if the refresh has been queued, false otherwise.
     */
    public function refresh($cache, $key, $duration, $dependency = null);

    /**
     * Compute and return the new data value.
     *
     * @param  yii\Caching\CacheInterface $cache the data cache component
     *
     * @return mixed the new data value.
     */
    public function generate($cache);
}
