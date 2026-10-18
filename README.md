# InvoiceCore

InvoiceCore je lokalna Laravel/React aplikacija za fakture, PDF dokumente, slanje e-mailom i KPO evidenciju za više firmi po korisniku.

## Stack i zahtevi

- Laravel 12, PHP constraint 8.2 ili noviji
- MariaDB 11.8 kroz DDEV
- React 18, TypeScript, Vite 7, Tailwind CSS 4
- Laravel Sanctum, Eloquent, Pest/PHPUnit
- Spatie Laravel PDF, Browsershot/Puppeteer i Chromium u DDEV web kontejneru

## Lokalno pokretanje

Potrebni su macOS, Docker i DDEV. Iz root direktorijuma projekta:

    ddev start
    ddev exec composer install
    ddev exec npm install
    ddev exec php artisan key:generate

Web aplikacija je dostupna na DDEV adresi koju prijavi ddev describe. Za razvoj frontenda:

    ddev exec npm run dev

PHP, Composer, Artisan, Node, npm i npx komande pokretati kroz DDEV. Host skripte scripts/backup.sh i scripts/restore.sh su izuzetak i pokreću se na macOS hostu.

## Migracije, testovi i build

Migracije se izvršavaju samo uz eksplicitno odobrenje:

    ddev exec php artisan migrate
    ddev exec php artisan migrate:status

Testovi:

    ddev exec php artisan test tests/Feature/RelevantTest.php
    ddev exec php artisan test
    ddev exec npx tsc --noEmit --allowImportingTsExtensions
    ddev exec npm run build
    ddev exec npm run build:ssr
    git diff --check

Redovni PHP testovi koriste SQLite in-memory bazu iz phpunit.xml. MariaDB KPO concurrency testovi imaju zasebnu zaštitu i koriste samo namensku test bazu kada su eksplicitno uključeni.

## Struktura

- app/Models, app/Http/Controllers, app/Http/Requests, app/Policies — backend
- app/Services — kurs, KPO obračun/generisanje i prateća poslovna logika
- database/migrations — šema i naknadne izmene
- resources/js/app — React stranice, context, komponente i utility funkcije
- routes/api.php — API
- tests/Feature i tests/Unit — testovi
- scripts — lokalni backup/restore i SSR pomoćne skripte
- docs — detaljna projektna dokumentacija

## Implementirane oblasti

Trenutno postoje fakture i company-scoped numeracija, više firmi i izolacija aktivne firme, valute i FX snapshot, minimalna evidencija plaćanja, PDF verzije/retention, e-mail istorija i idempotency zaštita, KPO generisanje/regeneracija sa kategorijama i auditom, bankovni računi po valuti, dospele fakture i timezone firme.

Računovodstvena pravila koja nisu potvrđena kodom ostaju označena u docs/otvorena-pitanja.md.

## Dokumentacija

- docs/arhitektura.md — stack i struktura
- docs/fakture.md — fakture, iznosi, statusi, plaćanja i numeracija
- docs/dokumenti-i-pdf.md — PDF verzije, privatni storage i e-mail
- docs/kpo.md — KPO preview, generisanje, regeneracija i PDF
- docs/kompanije-klijenti-podesavanja.md — firme, timezone i bankovni računi
- docs/autentifikacija-i-tenant.md — Sanctum, active company i autorizacija
- docs/local-backup.md — lokalni backup/restore
- docs/testiranje-i-razvoj.md — DDEV komande i bezbedne provere
- docs/plan-razvoja.md — status i prioriteti

Ne čuvati tajne vrednosti ili lokalne apsolutne putanje u dokumentaciji.
