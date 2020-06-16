<?php
/**
 * @copyright Copyright(c) 2020 Thamtech, LLC
 * @link https://github.com/thamtech/yii2-refresh-ahead-cache
 * @license https://opensource.org/licenses/BSD-3-Clause
**/

namespace thamtech\caching\refreshAhead;

use yii\base\BaseObject;
use yii\base\InvalidConfigException;

/**
 * BaseGenerator is a base class for implementations of [[GeneratorInterface]].
 *
 * @property int $mutexLockTimeout How long to wait for another process to
 *     finish generating a data value before checking for it in cache and
 *     possibly initiating synchronous generation ourselves.
 *
 * @author Tyler Ham <tyler@thamtech.com>
 */
abstract class BaseGenerator extends BaseObject implements GeneratorInterface
{
    /**
     * @var int time (in seconds) to wait for a lock to be released before
     *     considering invoking the `generate` callable.
     */
    private $_mutexLockTimeout = 0;

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
     * {@inheritdoc}
     */
    public function getMutexLockTimeout()
    {
        return $this->_mutexLockTimeout;
    }
}
