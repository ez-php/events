# Changelog

All notable changes to `ez-php/events` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `EventDispatcher` — synchronous publish/subscribe event bus; dispatches named events to all registered listeners in registration order
- Closure listeners — register anonymous functions directly as event listeners
- Object listeners — register listener classes with a typed `handle()` method; resolved via the container
- `Event` static facade — `listen()`, `dispatch()`, and `forget()` accessible globally after bootstrapping
- `EventServiceProvider` — binds the dispatcher and registers the `Event` facade alias
- `EventException` for listener resolution and dispatch failures
