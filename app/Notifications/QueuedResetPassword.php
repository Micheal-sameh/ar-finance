<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The standard reset-link email, sent from the queue so the forgot-password
 * request returns at once instead of waiting on the mail server (which is
 * what timed out). Failed sends are retried by the worker.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int> Seconds to wait before each retry.
     */
    public array $backoff = [30, 120];

    public int $timeout = 60;
}
