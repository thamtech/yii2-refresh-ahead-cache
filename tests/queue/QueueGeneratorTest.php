<?php

namespace thamtechunit\caching\refreshAhead\queue;

use thamtech\caching\refreshAhead\queue\QueueGenerator;
use thamtech\caching\refreshAhead\queue\ReferenceProvider;
use Yii;

class MockQueueGenerator extends QueueGenerator
{
    public function asConfigArray()
    {
        return parent::asConfigArray();
    }
}

class MockQueue extends \yii\queue\Queue
{
    static $testMessages = [];

    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        static::$testMessages[] = $message;
        return '1';
    }

    public function status($id)
    {
        return \yii\queue\Queue::STATUS_DONE;
    }
}

class MockGenerate
{
    static $values = [];

    public function genValue()
    {
        return array_shift(static::$values);
    }
}

class QueueGeneratorTest extends \thamtechunit\caching\refreshAhead\TestCase
{
    public function testInit()
    {
        $generator = Yii::createObject([
            'class' => MockQueueGenerator::class,
            'queue' => [
                'class' => MockQueue::class,
            ],
            'defaultContext' => [
                'reference' => 'yii\validators\StringValidator',
            ],
            'generateValue' => [
                'reference' => 'validate',
                'params' => [
                    'abc123',
                ],
            ],
        ]);

        $this->assertEquals([
            'class' => MockQueueGenerator::class,
            'queue' => null,
            'defaultContext' => [
                'class' => ReferenceProvider::class,
                'reference' => 'yii\validators\StringValidator',
                'params' => [],
                'context' => null,
            ],
            'refreshAhead' => false,
            'generateValue' => [
                'class' => ReferenceProvider::class,
                'reference' => 'validate',
                'params' => [
                    'abc123',
                ],
                'context' => null,
            ],
        ], $generator->asConfigArray());

        $this->assertInstanceOf(MockQueueGenerator::class, $generator);
    }

    public function testInitWithRefreshAhead()
    {
        $generator = Yii::createObject([
            'class' => MockQueueGenerator::class,
            'queue' => [
                'class' => MockQueue::class,
            ],
            'defaultContext' => [
                'reference' => 'yii\validators\StringValidator',
            ],
            'refreshAhead' => [
                'reference' => 'yii\validators\NumberValidator',
            ],
            'generateValue' => [
                'reference' => 'validate',
                'params' => [
                    'abc123',
                ],
            ],
        ]);

        $this->assertEquals([
            'class' => MockQueueGenerator::class,
            'queue' => null,
            'defaultContext' => [
                'class' => ReferenceProvider::class,
                'reference' => 'yii\validators\StringValidator',
                'params' => [],
                'context' => null,
            ],
            'refreshAhead' => [
                'class' => ReferenceProvider::class,
                'reference' => 'yii\validators\NumberValidator',
                'params' => [],
                'context' => null,
            ],
            'generateValue' => [
                'class' => ReferenceProvider::class,
                'reference' => 'validate',
                'params' => [
                    'abc123',
                ],
                'context' => null,
            ],
        ], $generator->asConfigArray());

        $this->assertInstanceOf(MockQueueGenerator::class, $generator);
    }

    public function testGetRefreshAheadDefault()
    {
        $generator = Yii::createObject([
            'class' => MockQueueGenerator::class,
            'queue' => [
                'class' => MockQueue::class,
            ],
            'defaultContext' => [
                'reference' => 'yii\validators\StringValidator',
            ],
            'generateValue' => [
                'reference' => 'validate',
                'params' => [
                    'abc123',
                ],
            ],
        ]);

        $this->assertEquals([
            'class' => MockQueueGenerator::class,
            'queue' => null,
            'defaultContext' => [
                'class' => ReferenceProvider::class,
                'reference' => 'yii\validators\StringValidator',
                'params' => [],
                'context' => null,
            ],
            'refreshAhead' => false,
            'generateValue' => [
                'class' => ReferenceProvider::class,
                'reference' => 'validate',
                'params' => [
                    'abc123',
                ],
                'context' => null,
            ],
        ], $generator->asConfigArray());

        $refreshAhead = $generator->getRefreshAhead();
        $this->assertInstanceOf('yii\validators\StringValidator', $refreshAhead);
        $this->assertSame($refreshAhead, $generator->getRefreshAhead());
    }

    public function testGetRefreshAheadSpecified()
    {
        $generator = Yii::createObject([
            'class' => MockQueueGenerator::class,
            'queue' => [
                'class' => MockQueue::class,
            ],
            'defaultContext' => [
                'reference' => 'yii\validators\StringValidator',
            ],
            'refreshAhead' => [
                'reference' => 'createObject',
                'params' => [
                    'yii\validators\NumberValidator',
                ],
                'context' => Yii::createObject([
                    'class' => ReferenceProvider::class,
                    'reference' => Yii::class,
                ]),
            ],
            'generateValue' => [
                'reference' => 'validate',
                'params' => [
                    'abc123',
                ],
            ],
        ]);

        $this->assertEquals([
            'class' => MockQueueGenerator::class,
            'queue' => null,
            'defaultContext' => [
                'class' => ReferenceProvider::class,
                'reference' => 'yii\validators\StringValidator',
                'params' => [],
                'context' => null,
            ],
            'refreshAhead' => [
                'class' => ReferenceProvider::class,
                'reference' => 'createObject',
                'params' => [
                    'yii\validators\NumberValidator',
                ],
                'context' => [
                    'class' => ReferenceProvider::class,
                    'reference' => Yii::class,
                    'params' => [],
                    'context' => null,
                ],
            ],
            'generateValue' => [
                'class' => ReferenceProvider::class,
                'reference' => 'validate',
                'params' => [
                    'abc123',
                ],
                'context' => null,
            ],
        ], $generator->asConfigArray());

        $refreshAhead = $generator->getRefreshAhead();
        $this->assertInstanceOf('yii\validators\NumberValidator', $refreshAhead);
        $this->assertSame($refreshAhead, $generator->getRefreshAhead());
    }

    public function testRefresh()
    {
        $generator = Yii::createObject([
            'class' => MockQueueGenerator::class,
            'queue' => [
                'class' => MockQueue::class,
            ],
            'defaultContext' => [
                'reference' => MockGenerate::class,
            ],
            'generateValue' => [
                'reference' => 'genValue',
            ],
        ]);

        $this->assertTrue($generator->refresh(null, 'key', 10));
        $this->assertEquals(['O:46:"thamtech\caching\refreshAhead\queue\RefreshJob":4:{s:15:"generatorConfig";a:5:{s:5:"class";s:58:"thamtechunit\caching\refreshAhead\queue\MockQueueGenerator";s:5:"queue";N;s:14:"defaultContext";a:4:{s:5:"class";s:53:"thamtech\caching\refreshAhead\queue\ReferenceProvider";s:9:"reference";s:52:"thamtechunit\caching\refreshAhead\queue\MockGenerate";s:6:"params";a:0:{}s:7:"context";N;}s:12:"refreshAhead";b:0;s:13:"generateValue";a:4:{s:5:"class";s:53:"thamtech\caching\refreshAhead\queue\ReferenceProvider";s:9:"reference";s:8:"genValue";s:6:"params";a:0:{}s:7:"context";N;}}s:3:"key";s:3:"key";s:8:"duration";i:10;s:10:"dependency";N;}'], MockQueue::$testMessages);
    }

    public function testGenerate()
    {
        $generator = Yii::createObject([
            'class' => MockQueueGenerator::class,
            'queue' => [
                'class' => MockQueue::class,
            ],
            'defaultContext' => [
                'reference' => MockGenerate::class,
            ],
            'generateValue' => [
                'reference' => 'genValue',
            ],
        ]);

        MockGenerate::$values = ['abc123', 'def456'];
        $this->assertEquals('abc123', $generator->generate(null));
        $this->assertEquals(['def456'], MockGenerate::$values);
    }
}
