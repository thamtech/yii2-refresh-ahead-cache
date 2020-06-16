Yii2 Refresh-Ahead Cache
========================

Yii2 Refresh-Ahead Cache can decorate Yii2 cache components or other components
to implement a refresh-ahead cache strategy.

The Refresh-Ahead cache strategy (also called Read-Ahead) is used to refresh
cached data before it expires. By refreshing cached data before it expires
(and doing it asynchronously), end-users never have to suffer the delay of
the refresh. Furthermore, it can also help avoid a
[Cache Stampede](https://en.wikipedia.org/wiki/Cache_stampede).

For license information check the [LICENSE](LICENSE.md)-file.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

```
php composer.phar require --prefer-dist thamtech/yii2-read-ahead-cache
```

or add

```
"thamtech/yii2-read-ahead-cache": "*"
```

to the `require` section of your `composer.json` file.

Usage
-----

### Decorate Cache Component

You can add Refresh-Ahead capability to your application's cache component
by attaching the `RefreshAheadCacheBehavior`. For example, in your application
configuration:

```php
<?php
[
    'components' => [
        'cache' => [
            'class' => 'yii\redis\Cache',
            'as refreshAhead' => 'thamtech\caching\refreshahead\RefreshAheadCacheBehavior',
        ],
    ],
];
```

There are a number of parameters you can configure if you declare the behavior
as a configuration array:

```php
<?php
[
    'components' => [
        'redisCache' => [
            'class' => 'yii\redis\Cache',
        ],
        'appMutex' => [
            'class' => 'yii\redis\Mutex',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
            'as refreshAhead' => [
                'class' => 'thamtech\caching\refreshahead\RefreshAheadCacheBehavior',
                'refreshTimeoutCache' => 'redisCache',
                'refreshAheadFactor' => 0.5,
                'refreshTimeoutKeySuffix' => 'refresh-ahead-timeout',
                'mutex' => 'appMutex',
            ],
        ],
    ],
];
```

### Decorate any Component

By default, `RefreshAheadCacheBehavior` assumes that its owner (the component
it is attached to as a
[behavior](https://www.yiiframework.com/doc/guide/2.0/en/concept-behaviors))
is a cache component that will be used for both storage of the cached data
as well as the storage of the refresh timeout key. However, you can specify
which cache components to use in these cases, so you do not have to attach
RefreshAheadCacheBehavior to a Cache component. You can attach it to any Yii
[Component](https://www.yiiframework.com/doc/guide/2.0/en/concept-components)
provided that you specify the cache component(s) to use in the behavior's
configuration.

```php
<?php
$dataManager = Yii::createObject([
    'class' => DataManager::class,
    'as refreshAhead' => [
        'class' => 'thamtech\caching\refreshahead\RefreshAheadCacheBehavior',
        
        // use application 'cache' component for data storage
        'dataCache' => 'cache',
        
        // use application 'cache' component for refresh timeout key storage
        'refreshTimeoutCache' => 'cache',
    ],
]);
```

If both data values and refresh timeout keys will be stored in the same cache
component, you can set the single `cache` property as a shortcut for setting
both `dataCache` and `refreshTimeoutCache`. The following configuration is
equivalent to the one above:

```php
<?php
$dataManager = Yii::createObject([
    'class' => DataManager::class,
    'as refreshAhead' => [
        'class' => 'thamtech\caching\refreshahead\RefreshAheadCacheBehavior',
        
        // use application 'cache' component for both data storage and refresh
        // timeout key storage
        'cache' => 'cache',
    ],
]);
```

### Drop-In Replacement for getOrSet

RefreshAheadCacheBehavior adds a `getRefreshOrSet()` method to the cache or
any other component it decorates. This method has the same signature as
[getOrSet()](https://www.yiiframework.com/doc/api/2.0/yii-caching-cache#getOrSet%28%29-detail),
so you can perform a drop-in replacement where you currently use `getOrSet()`.
For example,

```php
<?php
$data = $cache->getOrSet($key, function ($cache) {
    return $this->calculateSomething();
});

// drop-in replacement:
$data = $cache->getRefreshOrSet($key, function ($cache) {
    return $this->calculateSomething();
});


// with specified $duration and $dependency
$data = $cache->getOrSet($key, function ($cache) {
    return $this->calculateSomething();
}, $duration, $dependency);

// drop-in replacement
$data = $cache->getRefreshOrSet($key, function ($cache) {
    return $this->calculateSomething();
}, $duration, $dependency);
```

The Refresh Ahead strategy attempts to
[add()](https://www.yiiframework.com/doc/api/2.0/yii-caching-cache#add%28%29-detail)
a refresh timeout key in the refresh timeout cache component with a duration
shorter than the requested `$duration` (half of `$duration` by default). If the
add is successful, it means any previous refresh timeout key had expired and the
cached data is due for a refresh.

When `getRefreshOrSet()` is called with a single callable parameter like the
examples above, the Refresh Ahead strategy calls the callable, stores the
returned value into the data cache component using the specified `$duration`,
and returns that value (into the `$data` variable in the examples above).

On the other hand, if the attempt to add the refresh timeout key was not
successful, it means the key already exists and is not expired, and therefore,
no refresh is currently called for. The Refresh Ahead strategy uses `$key` to
look up the cached value in the data cache component and returns it if it finds
it. If it doesn't find it in the data cache (perhaps the cache was flushed
or the key was evicted), then the callable is invoked to calculate the new
value. The new value is set in the data cache component using the specified
`$duration` and the value is returned.

### Typical Usage

The usage can be further improved if you can support asynchronous refreshing.
In order to do this, we must provide the Refresh Ahead strategy with *two*
callables: one to trigger a refresh asynchronously and one that will refresh
the data synchronously and return the result.

The usage is similar to the examples above, except that the second parameter
to `getRefreshOrSet()` will be a `GeneratorInterface` object or configuration
array instead of a single callable. For example,

```php
<?php
$data = $cache->getRefreshOrSet($key, [
    // called by Refresh Ahead strategy if the data is still cached, but it is
    // time to refresh it
    'refresh' => function ($cache) {
        // queue the refresh task to be run at a later time
        // return `true` when task is queued, `false` if the task was not queued
        // (in which case the `refresh` callable will be called again in a
        // subsequent request)
        return $this->taskQueue->append('calculateSomething');
    },
    
    // called by Refresh Ahead strategy if the data is not in cache (it may
    // have expired before it could be refreshed, or it could have been
    // flushed or evicted, etc.)
    'generate' => function ($cache) {
        return $this->calculateSomething();
    }
], $duration, $dependency);
```

If you've configured a `mutex` component in the `RefreshAheadCacheBehavior`,
you can specify a timeout for acquiring a lock using the `mutexLockTimeout`
property:

```php
<?php
$generator = [
    'refresh' => function ($cache) {
        return $this->taskQueue->append('calculateSomething');
    },
    'generate' => function ($cache) {
        return $this->calculateSomething();
    },
    // Attempt to acquire a mutex lock for 12 seconds before invoking
    // the 'generate' callback. If the lock is acquired, Refresh Ahead will
    // check to see if the value is in the data cache once more before invoking
    // 'generate', in case another process was generating and caching the value
    // already.
    'mutexLockTimeout' => 12,
];

$data = $cache->getRefreshOrSet($key, $generator, $duration, $dependency);
```

By configuring a `mutex` component on the behavior and setting the
`mutexLockTimeout` as a property on the generator, the Refresh
Ahead strategy will attempt to acquire a lock to invoke the `generate` callable.
This way, if multiple requests come in around the same time when the value
has expired (a [Cache Stampede](https://en.wikipedia.org/wiki/Cache_stampede)),
the process that first acquires the lock will compute the value and store it in
cache. The other processes wait for the lock to be released. Once the first
process releases the lock, the value has been computed and is in cache, so the
other processes will check for it in cache, find it, and return it without
having to invoke the `generate` callable.

If your task queue can run asynchronously, such as in a cron task, you can
use the same `$generator` in a call to `generateAndSet()` to complete
the refresh process and update the cache value in the background. For example,

```php
<?php
// using the same parameters defined in the previous example:
$data = $cache->generateAndSet($key, $generator, $duration, $dependency);
```

This will invoke the `generate` callable (if the item hasn't already been cached
by another invocation of `generate` at the same time), and sets the result in
the cache before returning it.


See Also
--------

* [Cache Stampede](https://en.wikipedia.org/wiki/Cache_stampede)

* [Cache Concurrency Control - Case Study](https://www.braze.com/perspectives/article/cache-concurrency-control)

* [All things caching](https://medium.com/datadriveninvestor/all-things-caching-use-cases-benefits-strategies-choosing-a-caching-technology-exploring-fa6c1f2e93aa)
