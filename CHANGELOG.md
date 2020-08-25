Change Log
==========

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/)
and this project adheres to [Semantic Versioning](https://semver.org).


[v0.3.6]
--------

### Changed
- Relax dependency version requirement on `thamtech/yii2-di` library


[v0.3.5]
--------

### Added
- Add optional `$transientParams` to ReferenceProvider

### Changed
- Relax instanceof checks to `self` instead of `static`


[v0.3.4]
--------

### Added
- Expire (cancel) refresh jobs that aren't executed before the data value's
  expected expiration


[v0.3.3]
--------

### Added
- Track and report duration of events


[v0.3.2]
--------

### Added
- Ability to specify RefreshJob options


[v0.3.1]
--------

### Added
- Ability to specify a RefreshJob class name
- CacheEvents that are fired on cache hit, refresh requested, recently refreshed,
  or value generated.


[v0.3.0]
--------

### Added
- Support for any generator implementing `GeneratorInterface`
- Add `$key`, `$duration`, and `$dependency` parameters to `refresh()` signature
- QueueGenerator

### Changed
- Refactor: Extract Interface `GeneratorInterface` from `RefreshAheadConfig`
- Move function `RefreshAheadConfig::ensure()` to `RefreshAheadCacheBehavior::ensureGenerator()`
- Rename `RefreshAheadConfig` to `CallableGenerator`
- Refactor: Extract Superclass `BaseGenerator` from `CallableGenerator`

### Fixed
- Incorrect concurrent generation logic preventing refresh generation


[v0.2.0]
--------

### Changed
- Increase visibility of `generateAndSet()` to public

### Fixed
- Do not set `valse` value returned by generator into cache


[v0.1.0]
--------

### Added
- Initial implementation
