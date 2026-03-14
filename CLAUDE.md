# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

When creating a new module or `CLAUDE.md` anywhere in this repository:

**CLAUDE.md structure:**
- Start with the full content of `CODING_GUIDELINES.md`, verbatim
- Then add `---` followed by `# Package: ezphp/<name>` (or `# Directory: <name>`)
- Module-specific section must cover:
  - Source structure (file tree with one-line descriptions per file)
  - Key classes and their responsibilities
  - Design decisions and constraints
  - Testing approach and any infrastructure requirements (e.g. needs MySQL, Redis)
  - What does **not** belong in this module

**Each module needs its own:**
`composer.json` · `phpstan.neon` · `phpunit.xml` · `.php-cs-fixer.php` · `.gitignore` · `.github/workflows/ci.yml` · `README.md` · `tests/TestCase.php`

**Docker setup:** copy `docker-compose.yml`, `docker/`, `.env.example` and `start.sh` from the repository root and adapt them for the module (service names, ports, required services). Use a unique `DB_PORT` in `.env.example` that is not used by any other package — increment by one per package starting with `3306` (root).
---

# Package: ezphp/events

Synchronous event bus — dispatch, listen, and forget events within a single request.

---

## Source Structure

```
src/
├── EventInterface.php      — Marker interface; all dispatchable events must implement it
├── ListenerInterface.php   — Contract for class-based listeners: handle(EventInterface): void
├── EventDispatcher.php     — Synchronous bus; stores listeners by event class-string, dispatches in order
├── Event.php               — Static façade backed by a managed EventDispatcher singleton
└── EventServiceProvider.php — Binds EventDispatcher, wires static façade, eager-resolves in boot()

tests/
├── TestCase.php                    — Base PHPUnit test case
├── EventTest.php                   — Covers Event façade: listen, dispatch, forget, instance management
├── EventDispatcherTest.php         — Covers EventDispatcher: listen, dispatch, forget, getListeners, closure listeners
└── EventServiceProviderTest.php    — Covers provider registration and eager resolution
```

---

## Key Classes and Responsibilities

### EventInterface (`src/EventInterface.php`)

Empty marker interface. Every application event class must implement it. Carries the event payload as public readonly properties.

```php
class UserCreated implements EventInterface
{
    public function __construct(public readonly int $userId) {}
}
```

---

### ListenerInterface (`src/ListenerInterface.php`)

Contract for object listeners. The single method receives the event; the concrete listener casts to the expected type.

```php
class SendWelcomeEmail implements ListenerInterface
{
    public function handle(EventInterface $event): void
    {
        assert($event instanceof UserCreated);
        // ...
    }
}
```

---

### EventDispatcher (`src/EventDispatcher.php`)

The core bus. Holds `array<class-string<EventInterface>, list<ListenerInterface|Closure>>`.

| Method | Behaviour |
|---|---|
| `listen(class-string, ListenerInterface\|Closure)` | Appends listener to the list for that event class; duplicate registrations accumulate |
| `dispatch(EventInterface)` | Calls all listeners for `$event::class` in registration order; no-op if none registered |
| `forget(class-string)` | Removes all listeners for that event class |
| `getListeners(class-string)` | Returns the listener list (empty array if none); used in tests |

Dispatch is **synchronous and in-process**. Listeners run before `dispatch()` returns. Exceptions thrown by a listener propagate to the caller.

Listener types:
- `ListenerInterface` — calls `$listener->handle($event)`
- `Closure` — calls `$listener($event)` directly

---

### Event (`src/Event.php`)

Static façade. Delegates all calls to the managed `EventDispatcher` singleton.

| Method | Delegates to |
|---|---|
| `Event::listen($class, $listener)` | `EventDispatcher::listen()` |
| `Event::dispatch($event)` | `EventDispatcher::dispatch()` |
| `Event::forget($class)` | `EventDispatcher::forget()` |
| `Event::setDispatcher($dispatcher)` | Replaces the singleton (used by `EventServiceProvider` and tests) |
| `Event::getDispatcher()` | Returns singleton; creates a new `EventDispatcher` lazily if none set |
| `Event::resetDispatcher()` | Sets singleton to `null` (tests must call in `setUp`/`tearDown`) |

**Global state is intentional and documented** — the static façade allows `Event::dispatch()` from anywhere without container access.

---

### EventServiceProvider (`src/EventServiceProvider.php`)

- **`register()`** — Binds `EventDispatcher::class` as a factory; calls `Event::setDispatcher()` when resolved.
- **`boot()`** — Eagerly calls `$this->app->make(EventDispatcher::class)` to ensure the static façade is wired **before** any other provider's `boot()` calls `Event::listen()`. Without this, listener registration in other providers would silently target an unwired façade.

Application listeners must be registered in the `boot()` method of a dedicated application-level service provider that runs after `EventServiceProvider`.

---

## Design Decisions and Constraints

- **Synchronous only** — All listeners execute in the same PHP process before `dispatch()` returns. Async/queued events are out of scope; use `ezphp/queue` for deferred work.
- **No event stopping / propagation control** — Listeners cannot stop further listeners from running. If this is needed, use a different pattern (e.g. a `Throwable`, a shared mutable context object, or a dedicated return-value convention).
- **Listeners accumulate on repeated `listen()` calls** — There is no deduplication. Registering the same listener twice means it fires twice. This is intentional; deduplication is the caller's responsibility.
- **`EventServiceProvider` resolves eagerly in `boot()`** — This is a deliberate exception to lazy resolution. The static façade must be wired before other providers' `boot()` methods run, otherwise `Event::listen()` calls in those providers would silently create a throwaway dispatcher that gets replaced when the container later resolves `EventDispatcher::class`.
- **`EventInterface` is a marker** — It carries no methods. Payload is carried by concrete event class properties (preferably `public readonly`). A heavier base class would force all events to extend it, breaking composition.
- **Closures are first-class listeners** — Accepting `Closure` alongside `ListenerInterface` avoids boilerplate for simple one-off listeners in tests or small applications.

---

## Testing Approach

- **No external infrastructure required** — All tests are purely in-process.
- **Always call `Event::resetDispatcher()`** in `setUp()` and `tearDown()` of any test that touches the `Event` façade. Omitting this leaks listener state between test methods.
- **Test `EventDispatcher` directly** when testing dispatch logic — avoids the static layer and keeps tests deterministic.
- **Inline anonymous classes** for `EventInterface` and `ListenerInterface` implementations — keeps tests self-contained without extra fixture files.
- **`#[UsesClass]` required** — PHPUnit is configured with `beStrictAboutCoverageMetadata=true`. Declare indirectly used classes with `#[UsesClass]`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Async / queued event dispatch | `ezphp/queue` |
| Event sourcing / event store | Separate dedicated package |
| Application event classes (e.g. `UserCreated`) | Application code |
| Listener wiring for the application | Application-level service provider (`boot()`) |
| Broadcast / WebSocket events | Infrastructure layer |
| Observer pattern on Eloquent-style models | `ezphp/orm` |
