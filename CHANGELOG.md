Change Log
==========

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/)
and this project adheres to [Semantic Versioning](https://semver.org).


[unreleased]
------------

### Changed
- Refactor: Extract Interface `GeneratorInterface` from `RefreshAheadConfig`
- Move function `RefreshAheadConfig::ensure()` to `RefreshAheadCacheBehavior::ensureGenerator()`


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
