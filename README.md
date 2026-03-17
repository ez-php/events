# ez-php/events

Event dispatcher module for the [ez-php framework](https://github.com/ez-php/framework) — lightweight publish/subscribe event system.

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
