<?php

namespace App\Policies;

use App\Models\KpoEntry;
use App\Models\User;

class KpoEntryPolicy
{
    public function view(User $user, KpoEntry $entry): bool
    {
        return $entry->company?->user_id === $user->id
            && $user->active_company_id === $entry->company_id;
    }

    public function update(User $user, KpoEntry $entry): bool
    {
        return $entry->company?->user_id === $user->id
            && $user->active_company_id === $entry->company_id;
    }

    public function delete(User $user, KpoEntry $entry): bool
    {
        return $entry->source_type === 'manual'
            && $entry->company?->user_id === $user->id
            && $user->active_company_id === $entry->company_id;
    }
}
