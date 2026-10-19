# Changelog

## 0.1.0 — 2026-10-19

Initial standalone release of `wtroiano/bounded-json`, supporting PHP 8.5 and Illuminate 13.34 or later within their respective major versions.

### Added

- Deterministic JSON encoding and SHA-256 hashing, preserving object/list identity and rejecting duplicate keys, unsafe integers, invalid Unicode, and non-finite numbers.
- Bounded schema definition and value validation with closed objects, explicit array/string bounds, nullable types, and canonical enum equality.
- A broader schema subset evaluator with local references, alternatives, patterns, and additional numeric and collection assertions.
- Immutable JSON and schema limit options, with Laravel auto-discovery, container injection, and publishable configuration.
- Strict typed JSON access helpers and heuristic payload redaction for display and logs.
- Full API, configuration, schema, integration, development, and release documentation.
- Laravel Boost guidelines and skills for integration and schema validation.

### Fixed

- Prevent long JSON strings from bypassing duplicate-key checks when PCRE exhausts its JIT stack, and reject input if token validation cannot complete.
