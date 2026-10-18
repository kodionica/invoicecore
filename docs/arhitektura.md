# Arhitektura i struktura

## Tehnologije

- Backend: PHP 8.2+ constraint, Laravel 12, Eloquent i Sanctum.
- Lokalno okruženje: DDEV, PHP 8.4, MariaDB 11.8, Chromium.
- Frontend: React 18, TypeScript, Vite 7, React Router 7, Axios i Tailwind CSS 4.
- Testovi: Pest/PHPUnit; frontend nema zaseban test runner u package.json.
- PDF: Spatie Laravel PDF, Browsershot/Puppeteer i React SSR.

## Tok

routes/web.php vraća SPA Blade view, React Router upravlja dashboard stranicama, a routes/api.php sadrži Sanctum API. AppContext centralizuje frontend API pozive i normalizaciju.

## Struktura

- app/Models — User, Company, Client, Invoice, InvoiceItem, KPO, PDF i audit modeli
- app/Http/Controllers i app/Http/Requests — API tokovi i validacija
- app/Policies — ownership i active-company autorizacija
- app/Services — FX, KPO obračun/generisanje i poslovni tokovi
- database/migrations — šema
- resources/js/app — React stranice, context, komponente i utilities
- resources/js/ssr — PDF dokument SSR
- scripts — host backup/restore i SSR pomoć
- tests/Feature, tests/Unit — testovi
- docs — projektna dokumentacija

## Konvencije

Pre izmene proveriti postojeći model, request, policy, servis, rutu i sličnu React stranicu. Serverski company scope i authorization su obavezni; frontend filter nije sigurnosna granica. Šema se menja samo novom migracijom.

## DDEV i komande

    ddev start
    ddev exec composer install
    ddev exec php artisan test
    ddev exec npx tsc --noEmit --allowImportingTsExtensions
    ddev exec npm run build
    ddev exec npm run build:ssr

Migracije nisu deo redovne verifikacije i zahtevaju eksplicitno odobrenje.
