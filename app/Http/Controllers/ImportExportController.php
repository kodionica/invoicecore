<?php

namespace App\Http\Controllers;

use App\Http\Requests\CsvImportRequest;
use App\Services\CsvImportExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImportExportController extends Controller
{
    public function preview(CsvImportRequest $request, string $type, CsvImportExportService $service)
    {
        $type = $this->validatedType($type);
        $company = $request->user()->activeCompany;
        abort_unless($company, 422, 'Korisnik nema aktivnu firmu.');
        $mapping = $this->decodeMapping($request->input('mapping'));
        return response()->json($service->preview($request->file('file'), $type, $company, is_array($mapping) ? $mapping : []));
    }

    public function import(CsvImportRequest $request, string $type, CsvImportExportService $service)
    {
        $type = $this->validatedType($type);
        abort_unless($request->boolean('confirmed'), 422, 'Import zahteva eksplicitnu potvrdu.');
        $mapping = $this->decodeMapping($request->input('mapping', '{}'));
        $company = $request->user()->activeCompany;
        abort_unless($company, 422, 'Korisnik nema aktivnu firmu.');
        return response()->json($service->import($request->file('file'), $type, is_array($mapping) ? $mapping : [], $company));
    }

    public function export(Request $request, string $type, CsvImportExportService $service)
    {
        $type = $this->validatedType($type);
        $company = $request->user()->activeCompany;
        abort_unless($company, 422, 'Korisnik nema aktivnu firmu.');
        return $service->export($type, $company);
    }

    private function validatedType(string $type): string
    {
        abort_unless(in_array($type, ['clients', 'invoices'], true), 404);
        return $type;
    }

    private function decodeMapping(?string $value): array
    {
        if (!$value) return [];
        try {
            $mapping = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['mapping' => ['Mapiranje kolona nije ispravno.']]);
        }
        return is_array($mapping) ? $mapping : [];
    }
}
