# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

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

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^1.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3311** | **6383** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/events

Synchronous event bus — dispatch, listen, and forget events within a single request.

---

## Source Structure

```
src/
├── EventInterface.php          — Marker interface; all dispatchable events must implement it
├── StoppableEventInterface.php — Extends EventInterface; events can stop propagation via isPropagationStopped()/stopPropagation()
├── ListenerInterface.php       — Contract for class-based listeners: handle(EventInterface): void
├── EventDispatcher.php         — Synchronous bus with priority, wildcard fnmatch patterns, propagation control, and async queue support
├── AsyncEventJob.php           — JobInterface wrapper that re-dispatches an event synchronously when the queue worker runs it
├── Event.php                   — Static façade backed by a managed EventDispatcher singleton
└── EventServiceProvider.php    — Binds EventDispatcher, wires static façade, auto-registers config/events.php listeners in boot()

tests/
├── TestCase.php                    — Base PHPUnit test case
├── EventTest.php                   — Covers Event façade: listen, dispatch, forget, instance management
├── EventDispatcherTest.php         — Covers EventDispatcher: listen, dispatch, forget, getListeners, closure listeners, priority, wildcard, propagation
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

The core bus. Holds `array<string, list<array{priority: int, listener: ...}>>` keyed by event class-string or glob pattern.

| Method | Behaviour |
|---|---|
| `listen(string, ListenerInterface\|Closure, int $priority = 0)` | Appends listener; re-sorts the list by priority descending; duplicate registrations accumulate |
| `dispatch(EventInterface, bool $async = false)` | When `$async` and a queue is set, pushes `AsyncEventJob`; otherwise invokes all matching listeners in priority order |
| `forget(string)` | Removes all listeners registered under that key (exact or pattern) |
| `getListeners(string)` | Returns the merged + sorted listener list for the given event class; used in tests |
| `setQueue(QueueInterface)` | Wires a queue driver for async dispatch |

**Priority** — higher integer = fires first. Listeners with equal priority fire in registration order.

**Wildcard patterns** — keys containing `*` or `?` match via `fnmatch()`. The bare `*` pattern matches any event. Anonymous classes (which contain null bytes in their generated name) are matched only against the `*` pattern.

**Propagation control** — dispatch stops early if: (a) a Closure listener returns `false`, or (b) the event implements `StoppableEventInterface` and `isPropagationStopped()` returns `true` after a listener call.

**Async dispatch** — when `$async = true` and a `QueueInterface` is set, the event is wrapped in `AsyncEventJob` and pushed onto the queue. `AsyncEventJob::handle()` re-dispatches synchronously (never passes `async: true`), preventing infinite recursion.

Listener types:
- `ListenerInterface` — calls `$listener->handle($event)`
- `Closure` — calls `$listener($event)` directly; returning `false` stops propagation

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
- **`boot()`** — Eagerly resolves `EventDispatcher` (wiring the static façade), then reads `config/events.php` and registers all declared listeners via the container. Non-existent class names and entries that don't implement `ListenerInterface` are silently skipped.

Config format (`config/events.php`):

```php
return [
    'listeners' => [
        UserCreated::class => [
            SendWelcomeEmail::class,
            LogUserCreation::class,
        ],
    ],
];
```

Additional listeners can still be registered imperatively in any service provider that boots after `EventServiceProvider`:

```php
Event::listen(OrderPlaced::class, new SendOrderConfirmation());
```

---

## Design Decisions and Constraints

- **Synchronous by default** — All listeners execute in the same PHP process before `dispatch()` returns. Async dispatch is opt-in via `dispatch($event, async: true)` and requires a `QueueInterface` to be configured on the dispatcher.
- **Propagation control via `StoppableEventInterface` or Closure return value** — Events that implement `StoppableEventInterface` can call `stopPropagation()` from within a listener. Closures can stop propagation by returning `false`. Class-based `ListenerInterface` listeners cannot stop propagation — they must either modify event state (for stoppable events) or use a different pattern.
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
| Async / queued event dispatch | `ez-php/queue` |
| Event sourcing / event store | Separate dedicated package |
| Application event classes (e.g. `UserCreated`) | Application code |
| Listener wiring for the application | `config/events.php` or an application-level service provider |
| Broadcast / WebSocket events | Infrastructure layer |
| Observer pattern on Eloquent-style models | `ez-php/orm` |
