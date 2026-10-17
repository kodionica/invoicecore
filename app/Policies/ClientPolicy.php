<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy {
    public function view( User $user, Client $client ): bool {
        return $client->company?->user_id === $user->id
            && (int) $user->active_company_id === (int) $client->company_id;
    }

    public function update( User $user, Client $client ): bool {
        return $client->company?->user_id === $user->id
            && (int) $user->active_company_id === (int) $client->company_id;
    }

    public function delete( User $user, Client $client ): bool {
        return $client->company?->user_id === $user->id
            && (int) $user->active_company_id === (int) $client->company_id;
    }
}
