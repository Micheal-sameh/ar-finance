<?php

namespace App\Policies;

use App\Models\ReceivedPayment;
use App\Models\User;

/**
 * Received payments are part of sales, so they share the invoice permissions.
 */
class ReceivedPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, ReceivedPayment $receivedPayment): bool
    {
        return $user->can('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.manage');
    }
}
