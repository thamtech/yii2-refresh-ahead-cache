<?php
/**
 * @copyright Copyright(c) 2020 Thamtech, LLC
 * @link https://github.com/thamtech/yii2-refresh-ahead-cache
 * @license https://opensource.org/licenses/BSD-3-Clause
**/

namespace thamtech\caching\refreshAhead\queue;

use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use Yii;

/**
 * ReferenceProvider is a helper to represent references to objects and
 * callable methods.
 *
 * Inflate an object reference by calling [[asObject()]]. Invoke a method
 * reference by calling [[invokeAsMethod()]].
 *
 * To reference a class against static methods can be invoked, set [[reference]]
 * to the class name and set [[params]] to `false`. For example,
 *
 * ```php
 * [
 *     'reference' => Yii::class,
 *     'params' => false,
 * ],
 * ```
 *
 * To reference an object against which methods can be invoked, set [[reference]]
 * to any value that can be passed as the `$type` parameter to [[Yii::createObject]].
 * Set [[params]] to an array of parameters that will be passed as the second
 * argument to [[Yii::createObject]]. For example,
 *
 * ```php
 * [
 *     'reference' => 'yii\web\BadRequestHttpException',
 *     'params' => [
 *         'My Message',
 *         400,
 *     ],
 * ],
 * ```
 *
 * To reference a method, set [[reference]] to the method name and set
 * [[params]] to an array of parameters that will be passed to the method.
 * The method will be invoked against the [[context]] object by default if
 * not null. Otherwise, it will be invoked against the `$context` parameter
 * passed to [[invokeAsMethod()]]. For example,
 *
 * ```php
 * $validatorProvider = Instance::ensure([
 *     'reference' => 'validate',
 *     'params' => [
 *         'abc123',
 *     ],
 *     'context' => Instance::ensure([
 *             'reference' => [
 *                 'class' => 'yii\validators\StringValidator',
 *                 'min' => 3,
 *             ],
 *         ], ReferenceProvider::class)
 * ], ReferenceProvider::class);
 *
 * // validate 'abc123'
 * if ($validatorProvider->invokeAsMethod()) {
 *     echo "string is valid!";
 * }
 * ```
 *
 * @author Tyler Ham <tyler@thamtech.com>
 */
class ReferenceProvider extends BaseObject
{
    /**
     * @var string|array class name, method name, or object configuration array
     */
    public $reference = '';

    /**
     * @var array method or constructor parameters. These WILL be included
     * in the array provided [[asConfigArray()]]. See also [[transientParams]].
     */
    public $params = [];

    /**
     * @var array method or constructor parameters. These WILL NOT be included
     * in the array provided by [[asConfigArray()]]. If provided these will be
     * preferred over [[params]] for method or construct params.
     *
     * Transient Parameters are useful when a method or constructor can accept
     * either identifiers or the objects they identify. The identifiers can be
     * set in [[params]] for possible serialization, and the objects they
     * represent can be set in [[transientParams]]. Then object or method
     * calls that occur in the same process can use the existing objects in
     * [[transientParams]], saving a potentially costly lookup using the
     * identifiers in [[params]].
     */
    public $transientParams;

    /**
     * @var null|string|ReferenceProvider|mixed class name, object, or a
     * ReferenceProvider that provides an object. Used as the context when
     * invoking the [[reference]] as a method.
     *
     * The context is also the value returned by [[asObject()]] when it is not
     * null. This allows [[reference]] and [[params]] to describe how to
     * generate an object in future, but also allow an existing instance to
     * be returned in the meantime.
     */
    public $context = null;

    /**
     * Instantiates and returns the [[reference]] as an object. If [[context]]
     * is set, it will be returned instead.
     *
     * @return mixed
     */
    public function asObject()
    {
        if ($this->context !== null) {
            return $this->context;
        }

        if ($this->params === false) {
            if (!is_scalar($this->reference)) {
                throw new InvalidConfigException('Non-string reference cannot be returned as a static class name reference.');
            }
            // return class name only
            return $this->reference;
        }

        $params = $this->params;
        // use transient params instead if provided
        if (isset($this->transientParams)) {
            $params = $this->transientParams;
        }

        $this->context = Yii::createObject($this->reference, $params);
        return $this->context;
    }

    /**
     * Invoke reference as a method name on the context.
     *
     * @param mixed $context context in which to call the method if [[context]]
     *     property isn't set.
     *
     * @return mixed the result of invoking the reference as a method
     */
    public function invokeAsMethod($context = null)
    {
        if (!is_scalar($this->reference)) {
            throw new InvalidConfigException('Non-string reference cannot be invoked as a method.');
        }

        if (!is_array($this->params)) {
            throw new InvalidConfigException('Params must be an array to invoke reference as a method.');
        }

        $params = $this->params;

        if (isset($this->transientParams)) {
            if (!is_array($this->transientParams)) {
                throw new InvalidConfigException('Transient Params must be an array to invoke reference as a method.');
            }

            // use transient params instead if provided
            $params = $this->transientParams;
        }

        $invokeContext = $this->context;
        if ($invokeContext === null) {
            $invokeContext = $context;
        }

        if ($invokeContext instanceof self) {
            $invokeContext = $invokeContext->asObject();
        }

        $callable = [$invokeContext, $this->reference];
        return call_user_func_array($callable, $params);
    }

    /**
     * Get a configuration array representing this ReferenceProvider.
     *
     * @return array
     */
    public function asConfigArray()
    {
        $context = $this->context instanceof self ? $this->context->asConfigArray() : $this->context;
        return [
            'class' => static::class,
            'reference' => $this->reference,
            'params' => $this->params,
            'context' => is_array($context) ? $context : null,
        ];
    }
}
