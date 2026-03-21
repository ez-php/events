<?php

declare(strict_types=1);

namespace Tests\Events;

use EzPhp\Events\AsyncEventJob;
use EzPhp\Events\Event;
use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class AsyncEventJobTest
 *
 * @package Tests\Events
 */
#[CoversClass(AsyncEventJob::class)]
#[UsesClass(Event::class)]
#[UsesClass(EventDispatcher::class)]
final class AsyncEventJobTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Event::resetDispatcher();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Event::resetDispatcher();
        parent::tearDown();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return EventInterface
     */
    private function makeEvent(): EventInterface
    {
        return new class () implements EventInterface {
        };
    }

    // ─── handle() ─────────────────────────────────────────────────────────────

    /**
     * handle() dispatches the wrapped event via the Event facade's dispatcher.
     *
     * @return void
     */
    public function test_handle_dispatches_event_via_facade_dispatcher(): void
    {
        $event = $this->makeEvent();
        $received = null;

        Event::listen($event::class, function (EventInterface $e) use (&$received): void {
            $received = $e;
        });

        $job = new AsyncEventJob($event);
        $job->handle();

        $this->assertSame($event, $received);
    }

    // ─── JobInterface contract ────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_queue_returns_default(): void
    {
        $job = new AsyncEventJob($this->makeEvent());

        $this->assertSame('default', $job->getQueue());
    }

    /**
     * @return void
     */
    public function test_get_delay_returns_zero(): void
    {
        $job = new AsyncEventJob($this->makeEvent());

        $this->assertSame(0, $job->getDelay());
    }

    /**
     * @return void
     */
    public function test_get_max_tries_returns_three(): void
    {
        $job = new AsyncEventJob($this->makeEvent());

        $this->assertSame(3, $job->getMaxTries());
    }

    /**
     * @return void
     */
    public function test_increment_attempts_increments_counter(): void
    {
        $job = new AsyncEventJob($this->makeEvent());

        $this->assertSame(0, $job->getAttempts());

        $job->incrementAttempts();
        $this->assertSame(1, $job->getAttempts());

        $job->incrementAttempts();
        $this->assertSame(2, $job->getAttempts());
    }

    /**
     * fail() is a no-op and does not throw.
     *
     * @return void
     */
    public function test_fail_is_a_noop(): void
    {
        $this->expectNotToPerformAssertions();

        $job = new AsyncEventJob($this->makeEvent());
        $job->fail(new \RuntimeException('some error'));
    }
}
