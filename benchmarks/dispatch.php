<?php

declare(strict_types=1);

/**
 * Performance benchmark for EzPhp\Events\EventDispatcher.
 *
 * Measures the overhead of dispatching events to registered listeners,
 * including both closure listeners and class listeners.
 *
 * Exits with code 1 if the per-dispatch time exceeds the defined threshold,
 * allowing CI to detect performance regressions automatically.
 *
 * Usage:
 *   php benchmarks/dispatch.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Events\ListenerInterface;

const ITERATIONS = 5000;
const THRESHOLD_MS = 1.0; // per-dispatch upper bound in milliseconds

// ── Sample event and listeners ───────────────────────────────────────────────

final class UserRegistered implements EventInterface
{
    public function __construct(public readonly string $email)
    {
    }
}

final class SendWelcomeEmail implements ListenerInterface
{
    public function handle(object $event): void
    {
        // no-op
    }
}

// ── Benchmark ─────────────────────────────────────────────────────────────────

$dispatcher = new EventDispatcher();
$dispatcher->listen(UserRegistered::class, new SendWelcomeEmail());
$dispatcher->listen(UserRegistered::class, static function (UserRegistered $event): void {
    // no-op closure listener
});

$event = new UserRegistered('user@example.com');

// Warm-up
$dispatcher->dispatch($event);

$start = hrtime(true);

for ($i = 0; $i < ITERATIONS; $i++) {
    $dispatcher->dispatch(new UserRegistered('user@example.com'));
}

$end = hrtime(true);

$totalMs = ($end - $start) / 1_000_000;
$perDispatch = $totalMs / ITERATIONS;

echo sprintf(
    "Event Dispatcher Benchmark\n" .
    "  Listeners per event  : 2 (class + closure)\n" .
    "  Iterations           : %d\n" .
    "  Total time           : %.2f ms\n" .
    "  Per dispatch         : %.3f ms\n" .
    "  Threshold            : %.1f ms\n",
    ITERATIONS,
    $totalMs,
    $perDispatch,
    THRESHOLD_MS,
);

if ($perDispatch > THRESHOLD_MS) {
    echo sprintf(
        "FAIL: %.3f ms exceeds threshold of %.1f ms\n",
        $perDispatch,
        THRESHOLD_MS,
    );
    exit(1);
}

echo "PASS\n";
exit(0);
