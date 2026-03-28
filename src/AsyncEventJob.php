<?php

declare(strict_types=1);

namespace EzPhp\Events;

use EzPhp\Contracts\JobInterface;

/**
 * Class AsyncEventJob
 *
 * A queue job that wraps an event for asynchronous dispatch.
 *
 * When dispatched by the Worker, handle() re-dispatches the wrapped event
 * synchronously via the Event facade's current dispatcher. This avoids
 * infinite recursion — handle() never passes async: true.
 *
 * @package EzPhp\Events
 */
final class AsyncEventJob implements JobInterface
{
    /**
     * Number of times this job has been attempted.
     */
    private int $attempts = 0;

    /**
     * @param EventInterface $event The event to dispatch when the job runs.
     */
    public function __construct(private readonly EventInterface $event)
    {
    }

    /**
     * Execute the job: synchronously dispatch the wrapped event.
     *
     * @return void
     */
    public function handle(): void
    {
        Event::getDispatcher()->dispatch($this->event);
    }

    /**
     * Called when the job fails permanently. No-op for event jobs.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function fail(\Throwable $exception): void
    {
        // No-op: event dispatch failures are not persisted.
    }

    /**
     * Return the name of the queue this job should be pushed onto.
     *
     * @return string
     */
    public function getQueue(): string
    {
        return 'default';
    }

    /**
     * Return the number of seconds to wait before the job becomes available.
     *
     * @return int
     */
    public function getDelay(): int
    {
        return 0;
    }

    /**
     * Return the maximum number of times this job may be attempted.
     *
     * @return int
     */
    public function getMaxTries(): int
    {
        return 3;
    }

    /**
     * Return the number of times this job has already been attempted.
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Increment the attempt counter by one.
     *
     * @return void
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;
    }
}
