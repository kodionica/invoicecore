<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyBankAccountRequest;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyBankAccountController extends Controller
{
    public function index(Company $company)
    {
        $this->authorize('view', $company);

        return response()->json($company->bankAccounts()->orderBy('currency')->orderByDesc('is_default')->orderBy('label')->get());
    }

    public function store(StoreCompanyBankAccountRequest $request, Company $company)
    {
        $this->authorize('update', $company);

        $account = DB::transaction(function () use ($request, $company): CompanyBankAccount {
            $data = $request->validated();
            $account = $company->bankAccounts()->create($data);
            $this->normalizeCurrencyDefaults($company, $account);

            return $account->fresh();
        });

        return response()->json($account, 201);
    }

    public function update(StoreCompanyBankAccountRequest $request, Company $company, CompanyBankAccount $bankAccount)
    {
        $this->authorize('update', $company);
        $this->assertAccountBelongsToCompany($company, $bankAccount);

        $account = DB::transaction(function () use ($request, $company, $bankAccount): CompanyBankAccount {
            $previousCurrency = $bankAccount->currency;
            $bankAccount->fill($request->validated());
            $bankAccount->save();
            $this->normalizeCurrencyDefaults($company, $bankAccount);
            if ($previousCurrency !== $bankAccount->currency) {
                $oldCurrencyAccount = $company->bankAccounts()->where('currency', $previousCurrency)->first();
                if ($oldCurrencyAccount) {
                    $this->normalizeCurrencyDefaults($company, $oldCurrencyAccount);
                }
            }

            return $bankAccount->fresh();
        });

        return response()->json($account);
    }

    public function setDefault(Request $request, Company $company, CompanyBankAccount $bankAccount)
    {
        $this->authorize('update', $company);
        $this->assertAccountBelongsToCompany($company, $bankAccount);

        if (! $bankAccount->is_active) {
            throw ValidationException::withMessages(['is_default' => ['Neaktivan račun ne može biti podrazumevani.']]);
        }

        $account = DB::transaction(function () use ($company, $bankAccount): CompanyBankAccount {
            $company->bankAccounts()->where('currency', $bankAccount->currency)->update(['is_default' => false]);
            $bankAccount->update(['is_default' => true]);

            return $bankAccount->fresh();
        });

        return response()->json($account);
    }

    public function destroy(Company $company, CompanyBankAccount $bankAccount)
    {
        $this->authorize('update', $company);
        $this->assertAccountBelongsToCompany($company, $bankAccount);

        DB::transaction(function () use ($company, $bankAccount): void {
            $bankAccount->update(['is_active' => false, 'is_default' => false]);
            $this->normalizeCurrencyDefaults($company, $bankAccount);
        });

        return response()->noContent();
    }

    private function assertAccountBelongsToCompany(Company $company, CompanyBankAccount $bankAccount): void
    {
        abort_unless((int) $bankAccount->company_id === (int) $company->id, 404);
    }

    private function normalizeCurrencyDefaults(Company $company, CompanyBankAccount $account): void
    {
        $accounts = $company->bankAccounts()->where('currency', $account->currency)->lockForUpdate()->get();
        $active = $accounts->where('is_active', true);
        if ($active->isEmpty()) {
            $accounts->each(fn (CompanyBankAccount $item) => $item->update(['is_default' => false]));
            return;
        }

        $requestedDefault = $active->firstWhere('id', $account->id)?->is_default === true;
        $currentDefault = $active->where('is_default', true)->first();
        $default = $requestedDefault ? $account : ($currentDefault ?: $active->first());

        $accounts->each(fn (CompanyBankAccount $item) => $item->update([
            'is_default' => (int) $item->id === (int) $default->id,
        ]));
    }
}
