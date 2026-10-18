# KPO status implementacije

## Implementirano

- KPO lista, totals, filteri i sortiranje.
- Ručno kreiranje/izmena uz company scope.
- income_category za product/service/unclassified stavke.
- KpoAmountCalculationService koristi persisted item total i persisted FX snapshot.
- Preview i append-only generisanje su idempotentni.
- Mixed invoice može imati zasebne product/service redove.
- Unclassified, missing FX, VAT snapshot i reconciliation problemi se prikazuju kao blokade/upozorenja.
- Regeneration preview i eksplicitna regeneracija koriste fingerprint i manual-overwrite potvrdu.
- KPO soft-delete, audit revisions i kontrolisano uklanjanje postoje.
- KPO PDF je server-side i koristi pet fizičkih kolona.

## Zaštite

- Aktivna firma i policy se proveravaju na serveru.
- Counter i broj zapisa se zaključavaju u transakciji.
- Postojeći brojevi, adjustments i ručne izmene se ne prepisuju default generisanjem.
- Obrisani KPO redovi se ne vraćaju automatski.
- Preview ne rezerviše brojeve i stale preview se odbija.

## Relevantne putanje

- app/Services/KpoAmountCalculationService.php
- app/Services/KpoEntryService.php
- app/Http/Controllers/KpoController.php
- app/Models/KpoEntry.php
- resources/js/app/pages/Kpo.tsx
- tests/Feature/KpoEntryTest.php
- tests/Feature/KpoCategoryGenerationTest.php
- tests/Feature/KpoRegenerationTest.php
- tests/Feature/KpoSoftDeleteTest.php

## Provere

Redovne provere pokretati kroz DDEV. Fokusirane testove pokrenuti pre celog suite-a:

    ddev exec php artisan test tests/Feature/KpoEntryTest.php
    ddev exec php artisan test
    ddev exec npx tsc --noEmit --allowImportingTsExtensions
    ddev exec npm run build
    ddev exec npm run build:ssr
    git diff --check

MariaDB concurrency testovi nisu deo redovnog SQLite suite-a. Pokreću se samo uz RUN_KPO_MARIADB_CONCURRENCY=1 i namensku bazu invoicecore_kpo_concurrency_test_20261016.

## Otvorena pitanja

- Potvrditi računovodstveni datum, VAT osnovicu, storno i zaključavanje godine.
- Potvrditi pravnu usklađenost KPO PDF-a sa konkretnim poreskim zahtevima.
- Ne uvoditi automatsku reklasifikaciju istorijskih zapisa bez pouzdanog izvora.
