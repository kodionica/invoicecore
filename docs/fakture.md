# Fakture

## Stranice i API

- `/dashboard/invoices` — lista i inline promena statusa, `resources/js/app/pages/Invoices.tsx`.
- `/dashboard/invoices/new` — kreiranje, `InvoiceForm.tsx`.
- `/dashboard/invoices/:id` — detalj, `InvoiceDetails.tsx`.
- `GET /api/invoices` — lista aktivne firme.
- `POST /api/invoices` — kreiranje.
- `GET|PUT|DELETE /api/invoices/{invoice}` — standardne operacije uz policy.
- `GET /api/invoices/{invoice}/pdf` — PDF.
- `POST /api/invoices/{invoice}/email` — slanje emaila.
- `GET /api/invoices/next-number` — predlog broja fakture.

Implementacija je u `InvoiceController`, `StoreInvoiceRequest`, `InvoicePolicy` i `Invoice` modelu.

## Kreiranje

`StoreInvoiceRequest` validira klijenta, datume, valutu, način plaćanja i stavke. `InvoiceController::store()` proverava pripadnost klijenta aktivnoj firmi, koristi invoice counter, računa stavke, PDV i RSD snapshot preko `ExchangeRateService`.

Na fakturi postoje originalni i RSD iznosi (`*_original`, `*_rsd`). Statusi su definisani kroz `App\Enums\InvoiceStatus`.

## KPO uključivanje

Faktura ima boolean `include_in_kpo`, dodat migracijom `2026_10_15_000003_add_include_in_kpo_to_invoices_table.php`.

- Podrazumevana vrednost je `true`.
- Checkbox/toggle se nalazi na `InvoiceForm.tsx`.
- Vrednost se šalje kao `include_in_kpo` kroz `AppContext`.
- KPO preview i generisanje ignorišu fakture sa `include_in_kpo=false`.
- Direktno serversko kreiranje KPO zapisa iz isključene fakture je odbijeno u `KpoEntryService`.

Isključivanje ne briše već postojeći KPO zapis.
