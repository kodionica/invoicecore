# InvoiceCore — plan razvoja

Plan je izveden iz koda i migracija na dan audita. Prioriteti: P0 — bezbednost/integritet/blokatori; P1 — pouzdano svakodnevno poslovanje; P2 — produktivnost; P3 — napredne funkcije.

## P0

### 1. Izolacija naloga i podešavanja

- **Stanje:** delimično implementirano. Sanctum, policies i active-company zaštita postoje; `app_settings` je globalan, username update nije unique-validiran, nema email verification/session management.
- **Razlog/obim:** vezati settings za user/company, dodati unique validation, verifikaciju e-maila, reset lozinke i revoke sesija.
- **Zavisnosti/rizici:** odluka da li preference pripadaju korisniku ili firmi; migracija bez gubitka JSON-a.
- **Prihvatanje/testovi:** A ne čita preference B; dupli username/e-mail se odbija; revoked session nema pristup; API/policy feature testovi.
- **Potvrda pravila:** da, scope preference i retention sesija.

### 2. Integritet podataka i operacije

- **Stanje:** delimično. Faktura/KPO kreiranje i counters su transakcijski, ali nema audit trail, soft delete, backup/restore; stari factory/seeder su neispravni.
- **Razlog/obim:** popraviti/ukloniti stale seed artefakte, definisati backup/restore, archive/soft delete i audit log.
- **Zavisnosti/rizici:** storage, retention i recovery ciljevi.
- **Prihvatanje/testovi:** restore u čistu bazu, FK/tenant integritet, konkurentna numeracija i audit kritičnih izmena.
- **Potvrda pravila:** da, za brisanje i retention.

## P1

### 3. Životni ciklus fakture

- **Stanje:** delimično. Postoje statusi i ručni update, ali nema state machine, automatskog overdue-a, storna ni istorije.
- **Razlog/obim:** state machine, događaji, audit, cancel/storno model i scheduler.
- **Zavisnosti/rizici:** računovodstvo i KPO kompatibilnost.
- **Prihvatanje/testovi:** dozvoljeni/odbijeni prelazi, idempotentni događaji, storno bez brisanja originala, timezone testovi.
- **Potvrda pravila:** da.

### 4. Stvarne uplate

- **Stanje:** nije implementirano; `payment_method` nije uplata, Payment domen ne postoji.
- **Razlog/obim:** Payment model/migracija, više i delimične uplate, refund/storno, saldo i API/UI.
- **Zavisnosti/rizici:** valuta, kurs, datum naplate, tenant i KPO.
- **Prihvatanje/testovi:** saldo, overpayment, više uplata, tenant izolacija i idempotentno knjiženje.
- **Potvrda pravila:** obavezno.

### 5. Klijent, dashboard i pokazatelji

- **Stanje:** delimično. Klijenti postoje; dashboard koristi fakture/status, ne stvarne uplate.
- **Razlog/obim:** client detail sa fakturama/uplatama, server-side KPI, period/valuta filteri i jasna definicija prometa.
- **Zavisnosti/rizici:** payment domen i definicija prihoda/KPO.
- **Prihvatanje/testovi:** KPI se slažu sa server agregatima i tenant filterom; prazna stanja i strane valute.
- **Potvrda pravila:** da.

### 6. Izvoz i lokalne integracije

- **Stanje:** delimično. PDF fakture/KPO postoji; CSV/XLSX i SEF nisu potvrđeni.
- **Razlog/obim:** izvoz faktura, klijenata, uplata i KPO; discovery za SEF/eFaktura.
- **Zavisnosti/rizici:** format, privatnost, sertifikati i statusi.
- **Prihvatanje/testovi:** veliki dataset, UTF-8/decimalni separator, filteri, authorization i reproducibilan KPO export.
- **Potvrda pravila:** da, format i SEF scope.

### 7. E-mail i podsetnici

- **Stanje:** delimično. Jednokratno slanje PDF-a postoji; nema istorije, template-a, retry-ja ni automatskih podsetnika.
- **Razlog/obim:** template-i, queue, delivery log, retry, opt-in, due/overdue reminderi i ručno slanje.
- **Zavisnosti/rizici:** provider, GDPR, timezone i lifecycle.
- **Prihvatanje/testovi:** preview, idempotentan job, log uspeha/neuspeha, zabrana bez e-maila i opt-out.
- **Potvrda pravila:** da, učestalost i sadržaj.

## P2

### 8. Podrazumevane vrednosti i PDF brendiranje

- **Stanje:** delimično. Firma ima logo, valutu, PDV, rok i napomenu; nema potpunu konfiguraciju izgleda.
- **Razlog/obim:** footer, uslovi, bankovni blok, numeracija/prefix, preview i validacija logotipa.
- **Zavisnosti/rizici:** pravno obavezni elementi dokumenta.
- **Prihvatanje/testovi:** PDF payload/snapshot, fallback vrednosti, company isolation i očuvanje starih podataka.
- **Potvrda pravila:** da.

### 9. Ponude/predračuni

- **Stanje:** nije implementirano.
- **Razlog/obim:** quote model/statusi, numeracija, PDF, prihvatanje/odbijanje i konverzija u fakturu.
- **Zavisnosti/rizici:** poreski status i pravni naziv dokumenta.
- **Prihvatanje/testovi:** idempotentna konverzija, istorijski quote, tenant/policy testovi.
- **Potvrda pravila:** da.

### 10. Ponavljajuće fakture

- **Stanje:** nije implementirano.
- **Razlog/obim:** template, schedule, pause/cancel, preview, job i zaštita od duplikata.
- **Zavisnosti/rizici:** queue, timezone, numeracija i neuspešno kreiranje.
- **Prihvatanje/testovi:** deterministički next run, idempotency key i retry testovi.
- **Potvrda pravila:** da.

### 11. KPO workflow

- **Stanje:** tehnički implementirano; računovodstvena pravila nisu potvrđena. Postoje preview/generate, `include_in_kpo`, korekcije i PDF.
- **Razlog/obim:** potvrditi datum/prihod/storno/classification, zaključavanje godine, audit i export.
- **Zavisnosti/rizici:** payment, PDV i SEF.
- **Prihvatanje/testovi:** historical opt-in, duplicate prevention, locked period i reconciliation.
- **Potvrda pravila:** obavezno.

## P3

### 12. Obaveštenja i preference

- **Stanje:** delimično, kao JSON preference bez dispatch-a.
- **Razlog/obim:** per-user/per-company kanali, in-app/email, digest i audit preference.
- **Zavisnosti/rizici:** scope globalnih settings i queue.
- **Prihvatanje/testovi:** opt-in/out, izolacija, retry i timezone.
- **Potvrda pravila:** da.

### 13. SEF i druge lokalne integracije

- **Stanje:** zahteva proveru; nema potvrđenih klijenta/ruta.
- **Razlog/obim:** discovery, sertifikati, slanje/statusi, error mapping i reconciliation.
- **Zavisnosti/rizici:** regulatorni zahtevi i zaštita tajni.
- **Prihvatanje/testovi:** sandbox contract tests, retry i ručna korekcija.
- **Potvrda pravila:** da, uz knjigovođu/poreskog savetnika.

### 14. Operativni alati

- **Stanje:** nije implementirano.
- **Razlog/obim:** backup dashboard, restore procedura, health checks, failed jobs, storage cleanup i archive export.
- **Zavisnosti/rizici:** infrastruktura i retention.
- **Prihvatanje/testovi:** restore drill, alerting i permission testovi.
- **Potvrda pravila:** da.

## Preporučeni redosled

1. P0 tenant/settings i stale factory/seeder problem.
2. P0 backup/restore i audit/brisanje politike.
3. P1 payment domen i invoice lifecycle.
4. P1 client/dashboard/export/e-mail reminders.
5. P1 KPO pravila i zaključavanje.
6. P2 quote/recurring/brendiranje.
7. P3 SEF, napredne notifikacije i operativni alati.
