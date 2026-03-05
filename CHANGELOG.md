# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- IAP manager with driver pattern (Native, Null, Fake)
- Product, Purchase, and Entitlement DTOs with dual-case `fromArray()`
- Fluent builders: `PendingProducts`, `PendingPurchase`, `PendingRestore`
- Event classes for full purchase lifecycle
- FakeDriver with mock data setup and PHPUnit assertions
- iOS native code using StoreKit 2 with transaction observer
- Android native code using Google Play Billing 7.x
- JavaScript bridge for frontend integration
- Laravel Facade with static fake/clearFake support
- Config file for product registration
- Pest test suite (48 tests)
- GitHub Actions CI for PHP 8.2/8.3/8.4 and Laravel 11/12
