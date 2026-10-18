# Fakture

## Stranice i API

- /dashboard/invoices — lista, filteri, dospele fakture i statusi
- /dashboard/invoices/new — kreiranje
- /dashboard/invoices/:id — detalj, plaćanje, PDF, e-mail i istorija
- GET|POST|PUT|DELETE /api/invoices — company-scoped CRUD
- GET /api/invoices/{invoice}/pdf — privatni PDF
- POST /api/invoices/{invoice}/email — idempotentno slanje
- GET /api/invoices/next-number — predlog broja

Implementacija je u InvoiceController, Form Request klasama, Invoice modelu, policies i AppContext/React stranicama.

## Iznosi, valute i PDV

Server čuva subtotal, porez i total u originalnoj valuti i RSD, uz fx_rate_to_rsd, fx_provider i fx_date snapshot. Kurs dobavlja ExchangeRateService; nova faktura ne zavisi od kasnije promene kursa.

vat_enabled_at_issue čuva poreski snapshot. KPO eligibility koristi snapshot, a ne trenutni Company.vat_enabled. Istorijske fakture sa NULL snapshotom mogu biti blokirane dok se status eksplicitno ne razreši.

## Numeracija i izmene

Brojevi faktura su jedinstveni po firmi kroz bazu i serversku validaciju. Counter je po firmi, godini i mesecu. Izmena bez promene broja je dozvoljena; konflikt sa drugim brojem vraća validation grešku.

Izmena fakture može zahtevati KPO confirmation. Potvrđene strukturne promene se izvršavaju transakcijski: zadržani KPO zapisi čuvaju ID/broj, obsolete generated zapisi se soft-delete-uju, a audit ostaje sačuvan.

## Statusi i plaćanje

Statusi su draft, sent, paid, overdue i cancelled. Minimalna evidencija plaćanja koristi paid_at i payment_note na fakturi; nema parcijalnih uplata, salda ili Payment tabele. paid zahteva datum uplate za nove promene. Vraćanje iz paid čisti aktivne payment vrednosti uz invoice revision audit.

Dospelost se računa kada due_date postoji, prethodi današnjem datumu firme i status nije paid, cancelled ili draft. Filter se izvršava na backendu pre paginacije.

## Bankovni račun fakture

Faktura čuva bank_account_id i snapshot bankarskih instrukcija. Pri kreiranju se bira aktivan račun odgovarajuće valute; kasnija promena/deaktiviranje računa ne menja već sačuvanu fakturu ili PDF. Stare fakture bez snapshot-a koriste legacy payment polja.

## KPO

include_in_kpo kontroliše da li se faktura nudi za novo KPO generisanje. KPO datum može koristiti paid_at kao predlog, ali korisnik potvrđuje konačan datum. Promena statusa plaćanja ne menja postojeće KPO zapise.

## Otvorena pitanja

Računovodstveno značenje KPO prihoda, storno i zaključavanje perioda dokumentovani su u otvorena-pitanja.md.
