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
