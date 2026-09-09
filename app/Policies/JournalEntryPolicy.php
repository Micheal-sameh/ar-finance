<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('journals.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->can('journals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('journals.create');
    }
}
