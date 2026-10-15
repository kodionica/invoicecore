<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Policies\ClientPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\KpoEntryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider {
    protected $policies = [
        Company::class => CompanyPolicy::class,
        Client::class  => ClientPolicy::class,
        Invoice::class => InvoicePolicy::class,
        KpoEntry::class => KpoEntryPolicy::class,
    ];

    public function boot(): void {
        $this->registerPolicies();
    }
}
