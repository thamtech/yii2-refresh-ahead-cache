<?php
/**
 * @copyright Copyright(c) 2020 Thamtech, LLC
 * @link https://github.com/thamtech/yii2-refresh-ahead-cache
 * @license https://opensource.org/licenses/BSD-3-Clause
**/

namespace thamtech\caching\refreshAhead;

use yii\base\Event;

/**
 * CacheEvent is an event triggered by a [[RefreshAheadCacheBehavior]] to
 * notify listeners that a value was retrieved from cache, had its refresh
 * triggered, was recently refreshed, or was generated.
 *
 * @author Tyler Ham <tyler@thamtech.com>
 */
class CacheEvent extends Event
{
    /**
     * @var mixed the cache key
     */
    public $key;

    /**
     * @var mixed the cached value
     */
    public $value;

    /**
     * @var float duration of event in seconds
     */
    public $duration;
}
