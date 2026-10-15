# KPO backend — status implementacije

## Završeno

- Dodata je tenant-scoped `KpoEntry` evidencija i godišnje numerisanje po kompaniji.
- Podržani su eksplicitni `manual` i `invoice` izvori.
- Invoice izvor preuzima postojeće `total_original`, `total_rsd` i `currency` snapshot vrednosti.
- Datum evidentiranja mora biti eksplicitan; status fakture se ne koristi kao trenutak prihoda.
- `adjustment_rsd` se čuva odvojeno i server računa `total_rsd`.
- Jedna faktura može biti izvor najviše jednog KPO zapisa.
- Dodate su policy provere i filtriranje po aktivnoj firmi.
- Detalj/izmena su ograničeni i na aktivnu firmu; datum ne može promeniti numeracijsku godinu.
- Dodate su API rute za listu, kreiranje, detalj i izmenu.
- Dodati su Feature testovi za validaciju, iznose, duplikate, korekcije i tenant izolaciju.

## Izmenjeni fajlovi

- `database/migrations/2026_10_15_000001_create_kpo_counters_table.php`
- `database/migrations/2026_10_15_000002_create_kpo_entries_table.php`
- `app/Models/KpoEntry.php`
- `app/Models/Company.php`
- `app/Models/Invoice.php`
- `app/Policies/KpoEntryPolicy.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Http/Requests/StoreKpoEntryRequest.php`
- `app/Http/Requests/UpdateKpoEntryRequest.php`
- `app/Services/KpoEntryService.php`
- `app/Http/Controllers/KpoController.php`
- `routes/api.php`
- `tests/Feature/KpoEntryTest.php`

## API

- `GET /api/kpo?year=YYYY&from=YYYY-MM-DD&to=YYYY-MM-DD`
- `POST /api/kpo`
- `GET /api/kpo/{kpo}`
- `PUT /api/kpo/{kpo}`

`POST /api/kpo` sa `source_type=invoice` zahteva `invoice_id` i `entry_date`. Sa `source_type=manual` zahteva eksplicitne `currency`, `amount_original` i `amount_rsd`.

## Provere

Provere nakon izmene:

```bash
php artisan test tests/Feature/KpoEntryTest.php
php artisan test
php artisan route:list --path=api --except-vendor
```

- `php artisan test tests/Feature/KpoEntryTest.php` — 6 testova prošlo, 22 asercije.
- `php artisan test` — 14 testova prošlo, 37 asercija.
- `vendor/bin/pint --test ...` — 9 novih fajlova prošlo proveru stila.
- `php artisan route:list --path=api --except-vendor` — potvrđene 33 API rute, uključujući 4 KPO rute.

## Nerešena pitanja

- Ne postoji potvrđen payment domen, pa nema automatskog evidentiranja na osnovu stvarne naplate.
- Nije potvrđeno da li KPO koristi datum prometa, izdavanja, naplate ili knjiženja; API zato zahteva eksplicitni `entry_date`.
- Nije potvrđen računovodstveni tretman PDV-a, storna, negativnih zapisa i deviznog kursa za KPO.
- `income_type` je slobodan string jer postojeći `InvoiceItem` nema potvrđenu klasifikaciju proizvoda/usluge.
- Nije uvedeno automatsko osvežavanje KPO zapisa kada se postojeća faktura naknadno promeni.
- React ekran i PDF izvoz nisu deo ove faze.
