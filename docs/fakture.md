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

`StoreInvoiceRequest` zahteva najmanje jednu stavku, validira količinu/cenu, sprečava rok pre datuma izdavanja i trenutno ne prihvata datum izdavanja pre današnjeg dana. Kreiranje je u DB transakciji i koristi mesečni `invoice_counters` po firmi/godini/mesecu uz zaključavanje.

## Statusi, izmene i slanje

Statusi su `draft`, `sent`, `paid`, `overdue` i `cancelled`. `InvoiceController::update()` prihvata bilo koji status; nema serverski definisanih prelaza, automatskog overdue-a, storna kao posebnog događaja ili istorije izmena. DELETE trajno briše fakturu i stavke kroz cascade.

`POST /api/invoices/{invoice}/email` generiše/reupotrebljava PDF, šalje `InvoiceMail` klijentu i postavlja status na `sent`. **Delimično:** nema istorije slanja, retry-ja ili potvrde isporuke; `.env.example` koristi `MAIL_MAILER=log`.

## Naplata

`payment_method` je samo string na fakturi. Nema `Payment` modela, migracije, API-ja za uplate, delimičnih uplata, duga ili povezivanja sa KPO. Stari `database/factories/InvoiceFactory.php` i `database/seeders/InvoiceSeeder.php` referenciraju nepostojeći `App\Models\Payment` i zastarela polja, pa nisu dokaz postojeće payment funkcionalnosti.

## Valute i PDV

Server snapshot-uje kurs, provajdera i datum kroz `ExchangeRateService`; RSD koristi kurs 1, a strane valute podatke sa `kurs.resenje.org`. PDV se računa samo kada je `Company::vat_enabled=true`. `total_original` i `total_rsd` su relevantni snapshot iznosi za nove fakture.

## KPO uključivanje

Faktura ima boolean `include_in_kpo`, dodat migracijom `2026_10_15_000003_add_include_in_kpo_to_invoices_table.php`.

- Podrazumevana vrednost je `true`.
- Checkbox/toggle se nalazi na `InvoiceForm.tsx`.
- Vrednost se šalje kao `include_in_kpo` kroz `AppContext`.
- KPO preview i generisanje ignorišu fakture sa `include_in_kpo=false`.
- Direktno serversko kreiranje KPO zapisa iz isključene fakture je odbijeno u `KpoEntryService`.

Isključivanje ne briše već postojeći KPO zapis.
