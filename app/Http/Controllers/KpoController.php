<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKpoEntryRequest;
use App\Http\Requests\UpdateKpoEntryRequest;
use App\Models\KpoEntry;
use App\Services\KpoEntryService;
use Illuminate\Http\Request;

class KpoController extends Controller
{
    public function index(Request $request)
    {
        $company = auth()->user()->activeCompany;
        if (! $company) {
            return response()->json(['message' => 'Korisnik nema aktivnu firmu.'], 422);
        }

        $query = KpoEntry::query()
            ->forCompany($company->id)
            ->with('sourceInvoice')
            ->orderByDesc('entry_date')
            ->orderByDesc('entry_number');

        if ($request->filled('year')) {
            $query->forYear((int) $request->input('year'));
        }
        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->input('to'));
        }

        $entries = $query->get();

        return response()->json([
            'entries' => $entries,
            'totals' => [
                'amount_rsd' => round((float) $entries->sum('amount_rsd'), 2),
                'adjustment_rsd' => round((float) $entries->sum('adjustment_rsd'), 2),
                'total_rsd' => round((float) $entries->sum('total_rsd'), 2),
            ],
        ]);
    }

    public function store(StoreKpoEntryRequest $request, KpoEntryService $service)
    {
        $company = auth()->user()->activeCompany;
        if (! $company) {
            return response()->json(['message' => 'Korisnik nema aktivnu firmu.'], 422);
        }

        $entry = $service->create($company, $request->validated());

        return response()->json($entry->load('sourceInvoice'), 201);
    }

    public function show(KpoEntry $kpo)
    {
        $this->authorize('view', $kpo);

        return response()->json($kpo->load('sourceInvoice'));
    }

    public function update(UpdateKpoEntryRequest $request, KpoEntry $kpo, KpoEntryService $service)
    {
        $this->authorize('update', $kpo);
        $entry = $service->update($kpo, $request->validated());

        return response()->json($entry->load('sourceInvoice'));
    }
}
