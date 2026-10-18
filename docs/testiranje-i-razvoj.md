# Testiranje i razvoj

## DDEV komande

PHP, Composer, Artisan, Node, npm i npx pokretati kroz DDEV:

    ddev exec php artisan test tests/Feature/CompanyTimezoneTest.php
    ddev exec php artisan test
    ddev exec npx tsc --noEmit --allowImportingTsExtensions
    ddev exec npm run build
    ddev exec npm run build:ssr
    git diff --check

Redovni PHP testovi koriste SQLite in-memory bazu iz phpunit.xml. Fokusirane testove pokrenuti pre celog suite-a.

MariaDB KPO concurrency testovi se pokreću samo eksplicitno sa RUN_KPO_MARIADB_CONCURRENCY=1 i bazom invoicecore_kpo_concurrency_test_20261016. Njihova konfiguracija proverava stvarni SELECT DATABASE() i odbija razvojnu bazu db.

## Migracije

Promene šeme idu kroz novu migraciju. Ne pokretati migrate, migrate:fresh, rollback ili druge komande koje menjaju razvojnu bazu bez eksplicitnog odobrenja. Testovi sa RefreshDatabase koriste izolovanu test bazu prema phpunit.xml.

## Frontend build

npm run build i npm run build:ssr pokretati kroz DDEV. Host Rollup optional dependency greška nije dovoljna da se zaključi da je aplikacioni build neispravan; proveriti DDEV rezultat.

## Provera izmena

Pre izmene:

    git status --short
    git diff --stat

Posle izmene pregledati diff, API/frontend ugovor i:

    git diff --check

Ne prijavljivati komandu kao uspešnu ako nije pokrenuta.
