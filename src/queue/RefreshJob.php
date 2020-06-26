<?php
/**
 * @copyright Copyright(c) 2020 Thamtech, LLC
 * @link https://github.com/thamtech/yii2-refresh-ahead-cache
 * @license https://opensource.org/licenses/BSD-3-Clause
**/

namespace thamtech\caching\refreshAhead\queue;

use yii\base\BaseObject;
use yii\queue\JobInterface;
use Yii;

/**
 * RefreshJob is a task for use in a [[Queue]] to refresh a data value managed by
 * a [[RefreshAheadCacheBehavior]] object.
 *
 * @author Tyler Ham <tyler@thamtech.com>
 */
class RefreshJob extends BaseObject implements JobInterface
{
    /**
     * @var array configuration array describing a [[GeneratorInterface]].
     */
    public $generatorConfig = [];

    /**
     * @var mixed a key identifying the data value to be refreshed. This can be
     *     a simple string or a complex data structure consisting of factors
     *     representing the key.
     */
    public $key;

    /**
     * @var int duration in seconds for which the generated data value should be
     *     cached.
     */
    public $duration;

    /**
     * @var Dependency dependency to be applied on the cached data value.
     */
    public $dependency;

    /**
     * {@inheritdoc}
     */
    public function execute($queue)
    {
        $generator = $this->getGenerator();
        $refreshAhead = $generator->getRefreshAhead();
        $refreshAhead->generateAndSet($this->key, $generator, $this->duration, $this->dependency);
    }

    /**
     * Get the generator
     *
     * @return GeneratorInterface
     */
    protected function getGenerator()
    {
        return Yii::createObject($this->generatorConfig);
    }
}
