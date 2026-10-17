# Testiranje i razvoj

## Backend

```bash
php artisan test
php artisan test tests/Feature/KpoEntryTest.php
composer test
vendor/bin/pint --test
php artisan route:list --path=api --except-vendor
git diff --check
```

KPO testovi su u `tests/Feature/KpoEntryTest.php`. Pokrivaju validaciju, server-side iznose, duplikate, ručne korekcije, tenant izolaciju, generisanje, PDF i istorijske datume.

Izolovani MariaDB testovi za KPO konkurentnost koriste isključivo bazu
`invoicecore_kpo_concurrency_test_20261016`. Testovi imaju server-side proveru
konfigurisanog i stvarno povezanog naziva baze; razvojna baza `db` se odbija.

Pokretanje u DDEV-u:

```bash
ddev exec env APP_ENV=testing DB_CONNECTION=mariadb DB_HOST=db DB_PORT=3306 DB_DATABASE=invoicecore_kpo_concurrency_test_20261016 DB_USERNAME=db DB_PASSWORD=db RUN_KPO_MARIADB_CONCURRENCY=1 KPO_MARIADB_TEST_DATABASE=invoicecore_kpo_concurrency_test_20261016 php artisan test tests/Feature/KpoMariaDbConcurrencyTest.php
```

Redovni `php artisan test` ostaje na izolovanoj SQLite in-memory bazi preko
`phpunit.xml`; MariaDB testovi se preskaču ako `RUN_KPO_MARIADB_CONCURRENCY` nije
eksplicitno postavljen na `1`.

## Frontend

```bash
npx tsc --noEmit --allowImportingTsExtensions
npm run build
npm run build:ssr
```

`package.json` trenutno nema zasebnu npm lint skriptu. Standardni `npx tsc --noEmit` može prijaviti postojeći TS5097 zbog `.tsx` importa u `resources/js/main.tsx`; proveriti stvarni rezultat pre prijavljivanja.

## Migracije

```bash
php artisan migrate
php artisan migrate:status
```

Promene šeme idu kroz novu migraciju. Ne menjati već primenjenu migraciju da bi se promenila produkciona struktura i ne koristiti destruktivne database komande.

## Pre izmene

```bash
git status --short
git diff --stat
```

Posle izmene pregledati diff, proveriti import-e i API/frontend ugovor, zatim prijaviti samo stvarno pokrenute provere.

## Dokumentaciona provera

- `php artisan route:list --path=api --except-vendor` — pokrenuto tokom audita; potvrđeno 36 API ruta.
- `git diff --check` — pokrenuti nakon svake dokumentacione izmene.
- Testovi, build i type-check nisu pokretani tokom ovog audita osim eksplicitno navedenih provera.
