# Arhitektura i struktura

## Tehnologije

- Backend: PHP 8.2, Laravel 12, Eloquent ORM i Laravel Sanctum.
- Frontend: React 18, TypeScript, Vite i React Router 7.
- API klijent: Axios, centralizovan u `resources/js/app/context/AppContext.tsx`.
- UI: Tailwind CSS v4, SCSS, Lucide React i postojeće utility komponente.
- PDF: `spatie/laravel-pdf`, Browsershot/Puppeteer i postojeći SSR obrazac.
- Testovi: Pest/PHPUnit; formatiranje radi Laravel Pint.

## Tok aplikacije

`routes/web.php` vraća Laravel Blade view `resources/views/app.blade.php`. React Router zatim upravlja dashboard stranicama definisanim u `resources/js/app/routes.ts`.

API rute su u `routes/api.php`. Zaštićene rute su u `auth:sanctum` grupi. Poslovno stanje i API pozivi za frontend nalaze se u `AppContext`, dok stranice prikazuju i menjaju stanje kroz postojeće context metode.

## Važne putanje

| Oblast | Putanja |
|---|---|
| Modeli | `app/Models/` |
| Kontroleri | `app/Http/Controllers/` |
| Validacija | `app/Http/Requests/` |
| Autorizacija | `app/Policies/` |
| Servisi | `app/Services/` |
| Migracije | `database/migrations/` |
| React stranice | `resources/js/app/pages/` |
| React context/API | `resources/js/app/context/AppContext.tsx` |
| React rute | `resources/js/app/routes.ts` |
| API rute | `routes/api.php` |
| Testovi | `tests/Feature/`, `tests/Unit/` |

## Konvencije

Pre izmene proveriti postojeći model, request, kontroler, servis i sličnu React stranicu. Ne uvoditi novu biblioteku ili novi obrazac ako postojeći obrazac pokriva zahtev. Tenant filter i serverska autorizacija moraju postojati bez obzira na frontend filtere.
