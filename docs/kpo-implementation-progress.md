# KPO backend — status implementacije

## Završeno

- Dodata je tenant-scoped `KpoEntry` evidencija i godišnje numerisanje po kompaniji.
- Podržani su eksplicitni `manual` i `invoice` izvori.
- Invoice izvor preuzima postojeće `total_original`, `total_rsd` i `currency` snapshot vrednosti.
- Datum evidentiranja mora biti eksplicitan; status fakture se ne koristi kao trenutak prihoda.
- `adjustment_rsd` se čuva odvojeno i server računa `total_rsd`.
- Jedna faktura može biti izvor najviše jednog KPO zapisa.
- Dodate su policy provere i filtriranje po aktivnoj firmi; lista, kreiranje, detalj, izmena i PDF eksplicitno proveravaju vlasništvo aktivne firme.
- Detalj/izmena su ograničeni i na aktivnu firmu; datum ne može promeniti numeracijsku godinu.
- Dodate su API rute za listu, kreiranje, detalj i izmenu.
- Dodati su Feature testovi za validaciju, iznose, duplikate, korekcije i tenant izolaciju.
- Dodat je React ekran za pregled KPO evidencije sa filterom godine i perioda.
- Dodat je detalj zapisa sa dozvoljenom izmenom datuma, tipa prihoda, korekcije i opisa.
- Zbirni i pojedinačni ukupni iznosi prikazuju se iz serverskog API odgovora; frontend ih ne računa kao autoritativne.
- Dodat je server-side PDF izvoz filtrirane KPO evidencije sa podacima iz baze, zbirnim iznosima, informacijama firme, prelomom dugih opisa i ponavljanjem zaglavlja tabele.
- PDF ruta koristi postojeći `spatie/laravel-pdf`/Browsershot obrazac, validira period, proverava aktivnu firmu i vraća stabilan naziv `kpo-evidencija-{period}.pdf`.
- Frontend KPO stranica sada poziva stvarnu PDF rutu i preuzima server-generated dokument za trenutno izabrane filtere.
- Dodat je opt-in preview i inicijalno generisanje KPO zapisa iz postojećih faktura, uključujući fakture nastale pre KPO funkcionalnosti.
- Generisanje je idempotentno, preskače fakture koje već imaju KPO zapis, čuva postojeće korekcije i prijavljuje kreirane, preskočene i neuspešne stavke.
- KPO stranica dinamički koristi tekuću kalendarsku godinu kao početni filter; generisanje ne koristi filter godinu kao računovodstveni datum.

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
- `app/Http/Requests/KpoPdfRequest.php`
- `app/Http/Requests/KpoGenerationPreviewRequest.php`
- `app/Http/Requests/GenerateKpoEntriesRequest.php`
- `app/Services/KpoEntryService.php`
- `app/Http/Controllers/KpoController.php`
- `routes/api.php`
- `tests/Feature/KpoEntryTest.php`
- `resources/js/app/pages/Kpo.tsx`
- `resources/js/app/context/AppContext.tsx`
- `resources/js/app/routes.ts`
- `resources/js/app/layout/DashboardLayout.tsx`

## API

- `GET /api/kpo?year=YYYY&from=YYYY-MM-DD&to=YYYY-MM-DD`
- `POST /api/kpo`
- `GET /api/kpo/{kpo}`
- `PUT /api/kpo/{kpo}`
- `GET /api/kpo/pdf?year=YYYY&from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/kpo/generation-preview?year=YYYY&include_historical=0|1`
- `POST /api/kpo/generate`

Frontend ruta: `/dashboard/kpo`.

`POST /api/kpo` sa `source_type=invoice` zahteva `invoice_id` i `entry_date`. Sa `source_type=manual` zahteva eksplicitne `currency`, `amount_original` i `amount_rsd`.

Generisanje prima `{year, entries: [{invoice_id, entry_date}]}`. Faktura mora pripadati aktivnoj firmi, ne sme već imati KPO zapis, a `entry_date` je obavezan i mora pripadati izabranoj godini. Podrazumevani preview koristi godinu izdavanja samo za izbor kandidata; to nije odluka o računovodstvenom datumu. `include_historical=1` prikazuje i fakture iz drugih godina za eksplicitnu procenu korisnika.

## Provere

Provere nakon izmene:

```bash
php artisan test tests/Feature/KpoEntryTest.php
php artisan test
php artisan route:list --path=api --except-vendor
```

- `php artisan test tests/Feature/KpoEntryTest.php` — 13 testova prošlo, 62 asercije; uključeni su preview, generisanje, idempotencija, istorijske fakture, tenant izolacija i validacija datuma.
- `php artisan test` — 21 test prošao, 77 asercija.
- `vendor/bin/pint --test app/Http/Controllers/KpoController.php app/Http/Requests/KpoGenerationPreviewRequest.php app/Http/Requests/GenerateKpoEntriesRequest.php app/Services/KpoEntryService.php tests/Feature/KpoEntryTest.php` — prošlo.
- `git diff --check` — prošlo.
- `php artisan route:list --path=api --except-vendor` — potvrđene 36 API rute, uključujući preview i generate rute.
- `npx tsc --noEmit --allowImportingTsExtensions` — prošlo; KPO frontend ne dodaje TypeScript greške.
- `npx tsc --noEmit` — i dalje pada na ranije poznatoj grešci u `resources/js/main.tsx` zbog `.tsx` ekstenzije importa.
- `npm run build` i `npm run build:ssr` — nisu prošli zbog nedostajućeg postojećeg optional Rollup paketa `@rollup/rollup-darwin-arm64`; paketi nisu instalirani.
- `ddev exec php artisan migrate:status` — obe KPO migracije su potvrđene kao `Ran` u stvarnoj DDEV bazi; lokalni host CLI bez DDEV-a i dalje nema dostupan `db:3306`.

## Dijagnoza prazne KPO stranice

- U stvarnoj DDEV bazi provereno je: 1 aktivna kompanija, 9 faktura, 0 `kpo_entries` i 0 `kpo_counters`.
- Obe KPO migracije su izvršene: `2026_10_15_000001_create_kpo_counters_table` i `2026_10_15_000002_create_kpo_entries_table`.
- KPO stranica pri otvaranju i na „Osveži“ poziva `GET /api/kpo` sa filterom `year=2026`; `KpoController::index()` vraća praznu listu i nule zato što nema zapisa.
- „Osveži“ samo ponavlja GET zahtev. Ne poziva `KpoEntryService::create()` i ne generiše KPO zapise.
- Fakture su validan tehnički izvor za `POST /api/kpo` sa `source_type=invoice`, ali zahtevaju `invoice_id` i eksplicitan `entry_date`; nijedna postojeća faktura nije automatski obrađena.
- Nije uveden automatski backfill jer nije potvrđeno da li se za KPO koristi datum izdavanja, prometa, naplate ili knjiženja. Automatsko korišćenje `issue_date` bi bilo novo računovodstveno pravilo.
- Inicijalno popunjavanje je sada dostupno kroz preview/generisanje iz postojećih faktura: `GET /api/kpo/generation-preview` i `POST /api/kpo/generate`; datum KPO evidencije se unosi eksplicitno po fakturi.
- `include_historical` preview parametar prihvata frontend query-string format (`0/1` i `true/false`) i normalizuje se na serveru.

## Nerešena pitanja

- Ne postoji potvrđen payment domen, pa nema automatskog evidentiranja na osnovu stvarne naplate.
- Nije potvrđeno da li KPO koristi datum prometa, izdavanja, naplate ili knjiženja; API zato zahteva eksplicitni `entry_date`.
- Nije potvrđen računovodstveni tretman PDV-a, storna, negativnih zapisa i deviznog kursa za KPO.
- `income_type` je slobodan string jer postojeći `InvoiceItem` nema potvrđenu klasifikaciju proizvoda/usluge.
- Nije uvedeno automatsko osvežavanje KPO zapisa kada se postojeća faktura naknadno promeni.
- Nije izvršena vizuelna provera stvarno renderovanog PDF-a jer lokalni Puppeteer nema Chrome `148.0.7778.97`; test je zato izolovan mockom PDF buildera, dok produkcioni runtime i dalje zahteva dostupnu Browsershot/Chrome instalaciju.
- Nije dodata posebna forma za potpuno ručne nove KPO zapise; ekran sada pokriva pregled, inicijalno generisanje iz faktura, detalj i dozvoljene izmene postojećih zapisa.
- `income_type` ostaje slobodan string na backendu, pa se kolone proizvoda/usluga popunjavaju samo za prepoznate vrednosti tipa prihoda.
- Nisu potvrđeni stvarni produkcioni migracioni status i vizuelni PDF rendering u ovom okruženju.
