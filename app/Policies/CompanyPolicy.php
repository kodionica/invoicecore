<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy {
    public function view( User $user, Company $company ): bool {
        return $company->user_id === $user->id;
    }

    public function update( User $user, Company $company ): bool {
        return $company->user_id === $user->id;
    }

    public function delete( User $user, Company $company ): bool {
        return $company->user_id === $user->id;
    }
}
