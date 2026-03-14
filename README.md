# ezphp/events

Event dispatcher module for the [ez-php framework](https://github.com/ezphp/framework) — lightweight publish/subscribe event system.

[![CI](https://github.com/ezphp/events/actions/workflows/ci.yml/badge.svg)](https://github.com/ezphp/events/actions/workflows/ci.yml)

## Requirements

- PHP 8.5+
- ezphp/framework ^1.0

## Installation

```bash
composer require ezphp/events
```

## Setup

Register the service provider:

```php
$app->register(\EzPhp\Events\EventServiceProvider::class);
```

## Usage

```php
$dispatcher = $app->make(\EzPhp\Events\EventDispatcher::class);

// Listen
$dispatcher->listen('user.registered', function (Event $event) {
    // handle event
});

// Dispatch
$dispatcher->dispatch(new UserRegistered($user));
```

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
