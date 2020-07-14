<?php

namespace thamtechunit\caching\refreshAhead\queue;

use thamtech\caching\refreshAhead\queue\ReferenceProvider;
use yii\base\InvalidConfigException;
use Yii;

class ReferenceProviderTest extends \thamtechunit\caching\refreshAhead\TestCase
{
    public function testProvidedContextObject()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'abc',
            'params' => false,
            'context' => $this,
        ]);

        $this->assertSame($this, $provider->asObject());

        $this->assertEquals([
            'class' => ReferenceProvider::class,
            'reference' => 'abc',
            'params' => false,
            'context' => null,
        ], $provider->asConfigArray());
    }

    public function testStaticClassNameReference()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'yii\caching\FileCache',
            'params' => false,
        ]);

        $this->assertEquals('yii\caching\FileCache', $provider->asObject());

        $this->assertEquals([
            'class' => ReferenceProvider::class,
            'reference' => 'yii\caching\FileCache',
            'params' => false,
            'context' => null,
        ], $provider->asConfigArray());
    }

    public function testNonStringReferenceError()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => [
                'class' => 'yii\caching\FileCache',
            ],
            'params' => false,
        ]);

        $this->assertEquals([
            'class' => ReferenceProvider::class,
            'reference' => [
                'class' => 'yii\caching\FileCache',
            ],
            'params' => false,
            'context' => null,
        ], $provider->asConfigArray());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Non-string reference cannot be returned as a static class name reference.');

        $provider->asObject();
    }

    public function testCreateObjectFromClassName()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'yii\caching\FileCache',
        ]);

        $obj = $provider->asObject();
        $this->assertInstanceOf('yii\caching\FileCache', $obj);
        $this->assertSame($obj, $provider->context);

        $this->assertEquals([
            'class' => ReferenceProvider::class,
            'reference' => 'yii\caching\FileCache',
            'params' => [],
            'context' => null,
        ], $provider->asConfigArray());
    }

    public function testCreateObjectWithParams()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => [
                'class' => 'yii\web\BadRequestHttpException',
            ],
            'params' => [
                'Abc 123',
                499,
            ],
        ]);

        $obj = $provider->asObject();
        $this->assertInstanceOf('yii\web\BadRequestHttpException', $obj);
        $this->assertSame($obj, $provider->context);
        $this->assertEquals('Abc 123', $obj->getMessage());
        $this->assertEquals(499, $obj->getCode());

        $this->assertEquals([
            'class' => ReferenceProvider::class,
            'reference' => [
                'class' => 'yii\web\BadRequestHttpException',
            ],
            'params' => [
                'Abc 123',
                499,
            ],
            'context' => null,
        ], $provider->asConfigArray());
    }

    public function testCreateObjectWithTransientParams()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => [
                'class' => 'yii\web\BadRequestHttpException',
            ],
            'params' => [
                'Def 456',
                388,
            ],
            'transientParams' => [
                'Abc 123',
                499,
            ],
        ]);

        $obj = $provider->asObject();
        $this->assertInstanceOf('yii\web\BadRequestHttpException', $obj);
        $this->assertSame($obj, $provider->context);
        $this->assertEquals('Abc 123', $obj->getMessage());
        $this->assertEquals(499, $obj->getCode());

        $this->assertEquals([
            'class' => ReferenceProvider::class,
            'reference' => [
                'class' => 'yii\web\BadRequestHttpException',
            ],
            'params' => [
                'Def 456',
                388,
            ],
            'context' => null,
        ], $provider->asConfigArray());
    }

    public function testNonScalarMethodReference()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => [
                'validate',
            ],
        ]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Non-string reference cannot be invoked as a method.');

        $provider->invokeAsMethod();
    }

    public function testInvalidParamsMethodReference()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'validate',
            'params' => 'non-array',
        ]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Params must be an array to invoke reference as a method.');

        $provider->invokeAsMethod();
    }

    public function testInvalidTransientParamsMethodReference()
    {
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'validate',
            'params' => ['a', 'b'],
            'transientParams' => 'non-array',
        ]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Transient Params must be an array to invoke reference as a method.');

        $provider->invokeAsMethod();
    }

    public function testMethodDefaultContext()
    {
        $context = Yii::createObject([
            'class' => 'yii\validators\StringValidator',
            'min' => 3,
        ]);
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'validate',
            'params' => [
                'abc123'
            ],
            'context' => $context,
        ]);

        $this->assertTrue($provider->invokeAsMethod());
    }

    public function testMethodDefaultContextTransientParams()
    {
        $context = Yii::createObject([
            'class' => 'yii\validators\StringValidator',
            'min' => 3,
        ]);
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'validate',
            'params' => [
                'abc123'
            ],
            'transientParams' => [
                'ab',
            ],
            'context' => $context,
        ]);

        $this->assertFalse($provider->invokeAsMethod());
    }

    public function testMethodProvidedContext()
    {
        $context = Yii::createObject([
            'class' => 'yii\validators\StringValidator',
            'min' => 3,
        ]);
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'validate',
            'params' => [
                'ab'
            ],
        ]);

        $this->assertFalse($provider->invokeAsMethod($context));
    }

    public function testMethodContextReference()
    {
        $context = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => [
                'class' => 'yii\validators\StringValidator',
                'min' => 3,
            ],
        ]);
        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'validate',
            'params' => [
                'ab'
            ],
        ]);

        $this->assertFalse($provider->invokeAsMethod($context));

        $provider = Yii::createObject([
            'class' => ReferenceProvider::class,
            'reference' => 'validate',
            'params' => [
                'abc123'
            ],
        ]);

        $this->assertTrue($provider->invokeAsMethod($context));
    }
}
