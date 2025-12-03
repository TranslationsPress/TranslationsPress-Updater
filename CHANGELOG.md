# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2024-XX-XX

### Added
- Complete rewrite with modern PHP 7.4+ features
- PSR-4 autoloading with `TranslationsPress` namespace
- Singleton pattern via `Updater::get_instance()`
- WordPress.org translation override with two modes:
  - `replace`: Block wp.org completely, only use T15S
  - `fallback`: Try T15S first, use wp.org as fallback
- Centralized API support for plugin ecosystems (`register_addons()`)
- Intelligent caching with cleanup protection (prevents rapid cache clearing)
- Static caching for expensive WordPress functions
- Optional logging support via `set_logger()`
- Standalone single-file version for non-Composer projects
- Comprehensive PHPUnit test suite
- Full WPCS 3.0 compliance

### Changed
- Minimum PHP version raised to 7.4 (from 7.1)
- Minimum WordPress version raised to 6.0
- Complete API redesign:
  - `add_project()` → `Updater::get_instance()->register()`
  - Uses object-oriented Singleton instead of functions
- Hooks now use named methods (removable) instead of closures

### Fixed
- Anonymous closures that couldn't be removed
- Duplicate hook registration without instance tracking
- Missing WordPress.org override capability
- No support for centralized translation servers
- Cache cleanup race conditions

## [1.0.0] - Original

Original functional implementation by TranslationsPress/Required.

### Features
- Basic plugin/theme translation registration
- Transient-based caching
- WordPress translations_api integration
