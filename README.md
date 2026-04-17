# ez-php/events

Event dispatcher module for the [ez-php framework](https://github.com/ez-php/framework) — synchronous event bus with listener priority, wildcard patterns, propagation control, and async dispatch via queue.

[![CI](https://github.com/ez-php/events/actions/workflows/ci.yml/badge.svg)](https://github.com/ez-php/events/actions/workflows/ci.yml)

## Requirements

- PHP 8.5+
- ez-php/framework 0.*

## Installation

```bash
composer require ez-php/events
```

## Setup

Register the service provider:

```php
$app->register(\EzPhp\Events\EventServiceProvider::class);
```

## Usage

### Defining an event

```php
use EzPhp\Events\EventInterface;

class UserCreated implements EventInterface
{
    public function __construct(public readonly int $userId) {}
}
```

### Listening and dispatching

```php
use EzPhp\Events\Event;

// Register a closure listener
Event::listen(UserCreated::class, function (UserCreated $event): void {
    // send welcome email
});

// Register a class-based listener
Event::listen(UserCreated::class, new SendWelcomeEmail());

// Dispatch
Event::dispatch(new UserCreated(42));
```

### Listener priority

Higher priority fires first. Listeners with equal priority fire in registration order.

```php
Event::listen(UserCreated::class, new LogUserCreation(), priority: 10);
Event::listen(UserCreated::class, new SendWelcomeEmail(), priority: 5);
// LogUserCreation fires before SendWelcomeEmail
```

### Wildcard patterns

Use `*` and `?` (fnmatch-style) to listen to multiple event classes at once:

```php
Event::listen('App\Events\Order*', function (EventInterface $event): void {
    // fires for OrderPlaced, OrderShipped, OrderDelivered, ...
});
```

### Stopping propagation

Implement `StoppableEventInterface` to stop further listeners mid-dispatch:

```php
use EzPhp\Events\StoppableEventInterface;

class UserCreated implements StoppableEventInterface
{
    private bool $stopped = false;

    public function isPropagationStopped(): bool { return $this->stopped; }
    public function stopPropagation(): void { $this->stopped = true; }
}
```

A Closure listener can also stop propagation by returning `false`.

### Async dispatch

Pass `async: true` to push the event onto a queue instead of running listeners immediately (requires a `QueueInterface` configured on the dispatcher):

```php
Event::dispatch(new UserCreated(42), async: true);
```

When no queue is configured, `async: true` falls through to synchronous dispatch.

## Config-based listener registration

Declare listeners in `config/events.php` — `EventServiceProvider` registers them automatically during `boot()`:

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

Each listener class is resolved through the container (supports autowiring). Additional listeners can still be registered imperatively in any service provider that boots after `EventServiceProvider`:

```php
Event::listen(OrderPlaced::class, new SendOrderConfirmation());
```

## Class-based listeners

```php
use EzPhp\Events\ListenerInterface;

class SendWelcomeEmail implements ListenerInterface
{
    public function handle(EventInterface $event): void
    {
        assert($event instanceof UserCreated);
        // send the email
    }
}
```

## Classes

| Class | Description |
|---|---|
| `EventInterface` | Marker interface all dispatchable events must implement |
| `ListenerInterface` | Contract for class-based listeners: `handle(EventInterface): void` |
| `StoppableEventInterface` | Extends `EventInterface`; events can call `stopPropagation()` to halt the dispatch chain |
| `EventDispatcher` | Synchronous bus with priority ordering, wildcard matching, propagation control, and async queue support |
| `Event` | Static façade backed by a managed `EventDispatcher` singleton |
| `AsyncEventJob` | `JobInterface` implementation that wraps an event for deferred queue-based dispatch |
| `EventServiceProvider` | Binds `EventDispatcher`, wires static façade, auto-registers `config/events.php` listeners |

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
