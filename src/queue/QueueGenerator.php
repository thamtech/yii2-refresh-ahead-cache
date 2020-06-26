<?php
/**
 * @copyright Copyright(c) 2020 Thamtech, LLC
 * @link https://github.com/thamtech/yii2-refresh-ahead-cache
 * @license https://opensource.org/licenses/BSD-3-Clause
**/

namespace thamtech\caching\refreshAhead\queue;

use thamtech\caching\refreshAhead\BaseGenerator;
use yii\caching\Dependency;
use yii\di\Instance;
use yii\queue\Queue;
use Yii;

/**
 * QueueGenerator is a GeneraterInterface that supports queueing
 * refresh/generate tasks as jobs in a [[Queue]].
 *
 * This generator requires that you set it up using serializable
 * [[ReferenceProvider]] configurations so that it can instantiate the
 * [[RefreshAheadCacheBehavior]] object or its behavior owner, and invoke a
 * callable to generate the new data value.
 *
 * Ensure that the [[queue]] is set to an application [[Queue]] component.
 *
 * Set the [[defaultContext]] to a [[ReferenceProvider]] configuration array
 * that describes something [[Yii::createObject()]] can instantiate. This could
 * be the [[RefreshAheadCacheBehavior]] object (or its behavior owner)
 * itself. If so, then leave [[refreshAhead]] set to `false`. For example,
 *
 * ```php
 * [
 *     'defaultContext' => [
 *         'reference' => 'some\package\MyRefreshAheadComponent',
 *         'params' => [
 *             // contstructor params can go here
 *         ]
 *     ],
 * ],
 * ```
 *
 * If the context object is not the [[RefreshAheadCacheBehavior]] object (or
 * its behavior owner), but is instead a provider via a method:
 *
 * ```php
 * [
 *     'defaultContext' => [
 *         'reference' => 'some\package\MyRefreshAheadProvider',
 *         'params' => [
 *             // contstructor params can go here
 *         ]
 *     ],
 *     'refreshAhead' => [
 *         'reference' => 'getRefreshAheadByName', // method of MyRefreshAheadProvider
 *         'params' => [
 *             'treeRefreshAhead', // example parameter to the getRefreshAheadByName() method
 *         ],
 *     ],
 * ],
 * ```
 *
 * Set [[generateValue]] to a [[ReferenceProvider]] configuration array that
 * describes a method on the [[defaultContext]] object that should be called
 * to generate a particular data value:
 *
 * ```php
 * [
 *     'generateValue' => [
 *         'reference' => 'generateDataValue', // method of MyRefreshAheadComponent
 *         'params' => [
 *             // method params describing the value to generate, for example:
 *             23, // example $id parameter
 *         ],
 *     ],
 * ],
 * ```
 *
 * @author Tyler Ham <tyler@thamtech.com>
 */
class QueueGenerator extends BaseGenerator
{
    /**
     * @var Queue
     */
    public $queue = 'queue';

    /**
     * @var string refresh job class name
     */
    public $refreshJobClass = RefreshJob::class;

    /**
     * @var ReferenceProvider reference to a class or object against which
     *    other methods will be called.
     */
    public $defaultContext;

    /**
     * @var false|ReferenceProvider reference to a method that can provide the
     * [[RefreshAheadCacheBehavior]] object or its behavior owner. Leave false
     * if the [[defaultContext]] is already a reference to the
     * [[RefreshAheadCacheBehavior]] or its behavior owner.
     */
    public $refreshAhead = false;

    /**
     * @var ReferenceProvider reference to the method to generate a new
     *     data value.
     */
    public $generateValue;

    /**
     * @var mixed|RefreshAheadCacheBehavior the RefreshAheadCacheBehavior or its
     *     behavior owner.
     */
    private $_refreshAheadObj;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if ($this->queue) {
            $this->queue = Instance::ensure($this->queue, Queue::class);
        }

        $this->defaultContext = Instance::ensure($this->defaultContext, ReferenceProvider::class);
        $this->generateValue = Instance::ensure($this->generateValue, ReferenceProvider::class);

        if ($this->refreshAhead !== false) {
            $this->refreshAhead = Instance::ensure($this->refreshAhead, ReferenceProvider::class);
        }
    }

    /**
     * Get the refresh ahead object or its behavior owner
     *
     * @return RefreshAheadCacheBehavior
     */
    public function getRefreshAhead()
    {
        if (isset($this->_refreshAheadObj)) {
            return $this->_refreshAheadObj;
        }

        if ($this->refreshAhead === false) {
            $this->_refreshAheadObj = $this->defaultContext->asObject();
        } else {
            $this->_refreshAheadObj = $this->refreshAhead->invokeAsMethod($this->defaultContext);
        }

        return $this->_refreshAheadObj;
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($cache, $key, $duration, $dependency = null)
    {
        $job = $this->buildRefreshJob($cache, $key, $duration, $dependency);

        return $this->queue->push($job) ? true : false;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($cache)
    {
        return $this->generateValue->invokeAsMethod($this->defaultContext);
    }

    /**
     * Builds a refresh job
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
     * @return JobInterface
     */
    protected function buildRefreshJob($cache, $key, $duration, $dependency)
    {
        return Yii::createObject([
            'class' => $this->refreshJobClass,
            'generatorConfig' => $this->asConfigArray(),
            'key' => $key,
            'duration' => $duration,
            'dependency' => $dependency,
        ]);
    }

    /**
     * Get a configuration array representing this generator except for the
     * queue component.
     *
     * @return array
     */
    protected function asConfigArray()
    {
        return [
            'class' => static::class,
            'queue' => null, // queue is not needed when invoked as a [[RefreshJob]]
            'defaultContext' => $this->defaultContext->asConfigArray(),
            'refreshAhead' => $this->refreshAhead instanceof ReferenceProvider ? $this->refreshAhead->asConfigArray() : $this->refreshAhead,
            'generateValue' => $this->generateValue instanceof ReferenceProvider ? $this->generateValue->asConfigArray() : $this->generateValue,
        ];
    }
}
