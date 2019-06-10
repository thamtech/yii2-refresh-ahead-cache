<?php
/**
 * @copyright Copyright(c) 2019 Thamtech, LLC
 * @link https://github.com/thamtech/yii2-refresh-ahead-cache
 * @license https://opensource.org/licenses/BSD-3-Clause
**/

namespace thamtech\caching\refreshAhead;

use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\di\Instance;

/**
 * RefreshAheadConfig is a configuration object for a particular call
 * to [[RefreshAheadCacheBehavior::getRefreshOrSet()]]. This allows you
 * to define a `refresh` callable to asynchronously refresh a data value,
 * a `generate` callable to synchronously refresh a data value, and
 * optionally a mutex timeout.
 *
 * @property int $mutexLockTimeout How long to wait for another process to
 *     finish generating a data value before checking for it in cache and
 *     possibly initiating synchronous generation ourselves.
 *
 * @property-write callable|\Closure $refresh callable that will asynchronously
 *     refresh a data value (optional).
 *
 * @property-write callable|\Closure $generate callable that will synchronously
 *     refresh a data value.
 *
 * @author Tyler Ham <tyler@thamtech.com>
 */
class RefreshAheadConfig extends BaseObject
{
    /**
     * @var callable|\Closure asynchronously refresh a data value (optional)
     */
    private $_refreshCallable;

    /**
     * @var callable|\Closure synchronously refresh a data value
     */
    private $_generateCallable;

    /**
     * @var int time (in seconds) to wait for a lock to be released before
     *     considering invoking the `generate` callable.
     */
    private $_mutexLockTimeout = 0;

    /**
     * Resolves the specified reference into an actual [[RefreshAheadConfig]]
     * object.
     *
     * The reference may be specified as a configuration array for a
     * [[RefreshAheadConfig]] object. In this case, you must specify both
     * `refresh` and `generate` callables.
     *
     * The reference may be specified as a callable or \Closure, which will be
     * treated as as a synchronous `generate` callable (asynchronous refreshing
     * is not available in this case).
     *
     * The reference may also be a string or an Instance object. If the former,
     * it will be treated as an application component ID or a class name.
     *
     * @param  callable|\Closure|array|RefreshAheadConfig|string|Instance $reference an
     *     object or reference to the desired object.
     *
     *     You may specify a reference in terms of an application component ID,
     *     an Instance object, a RefreshAheadConfig object, or a configuration
     *     array for creating the object. If the "class" value is not specified
     *     in the configuration array, it will use the value of
     *     'thamtech\caching\refreshahead\RefreshAheadConfig'.
     *
     * @return RefreshAheadConfig the object instance
     * @throws InvalidConfigException if the reference is invalid
     */
    public static function ensure($reference)
    {
        if (is_callable($reference)) {
            $reference = [
                'generate' => $reference,
            ];
        }

        return Instance::ensure($reference, self::class);
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if (empty($this->_generateCallable)) {
            throw new InvalidConfigException('You must specify a generate callable.');
        }
    }

    /**
     * Sets the mutex lock timeout.
     *
     * A value of 0 means that we will not wait at all if another process has
     * acquired the lock. The result will be an immediate call to the
     * `generate` callable. This is the default behavior.
     *
     * To wait for another process holding a lock, set this timeout to a
     * positive value and make sure that a `mutex` component is configured in
     * the [[RefreshAheadCacheBehvaior]].
     *
     * @param int $timeout 0 or greater
     * @throws InvalidConfigException if the timeout is not an integer or if it is negative
     */
    public function setMutexLockTimeout($timeout)
    {
        if (!(is_int($timeout) || ctype_digit($timeout)) || (int)$timeout < 0) {
            throw new InvalidConfigException('mutexLockTimeout must be an integer 0 or greater.');
        }

        $this->_mutexLockTimeout = $timeout;
    }

    /**
     * Gets the mutex lock timeout (in seconds).
     *
     * @return int the mutex lock timeout
     */
    public function getMutexLockTimeout()
    {
        return $this->_mutexLockTimeout;
    }

    /**
     * Set the callable used to asynchronously refresh the data value.
     *
     * @param null|callable|\Closure $callable the callable or closure that will be
     *     used to mark the data value to be refreshed. This should queue
     *     a task to refresh the data value and return quickly.
     *
     *     The callable should return `true` if the refresh has been queued.
     *
     *     If the callable returns `false`, the refresh timeout key will be
     *     deleted and the next request for the data will trigger the refresh
     *     call again. This would mainly be used if there was some problem while
     *     trying to queue the refresh task (such as a lost database connection
     *     or some other temporary failure).
     *
     *     The callable should accept a single `$cache` parameter, which is the
     *     cache component used for storing the data value.
     *
     * @throws InvalidConfigException if the parameter is not callable
     */
    public function setRefresh($callable)
    {
        // we allow the refresh callable to be empty if only synchronous
        // data value generation is supported
        if (!empty($callable) && !is_callable($callable)) {
            throw new InvalidConfigException('refresh must be a callable.');
        }

        $this->_refreshCallable = $callable;
    }

    /**
     * Set the callable used to synchronously refresh the data value.
     *
     * @param callable|\Closure $callable the callable or closure that will be
     *     used to compute the new data value.
     *
     *     The callable should return the computed value.
     *
     *     If the callable returns `false`, the value will not be cached.
     *
     *     The callable should accept a single `$cache` parameter, which is the
     *     cache component used for storing the data value.
     *
     * @throws InvalidConfigException if the parameter is not callable
     */
    public function setGenerate($callable)
    {
        if (!is_callable($callable)) {
            throw new InvalidConfigException('generate must be a callable.');
        }

        $this->_generateCallable = $callable;
    }

    /**
     * Invoke the `refresh` callable to queue an asynchronous refresh of the data value.
     *
     * @param  yii\Caching\CacheInterface $cache the data cache component
     *
     * @return bool true if the refresh has been queued, false otherwise.
     */
    public function refresh($cache)
    {
        if ($this->_refreshCallable) {
            return call_user_func($this->_refreshCallable, $cache);
        }

        return false;
    }

    /**
     * Invoke the `generate` callable to compute the new data value.
     *
     * @param  yii\Caching\CacheInterface $cache the data cache component
     *
     * @return mixed the new data value.
     */
    public function generate($cache)
    {
        return call_user_func($this->_generateCallable, $cache);
    }
}
