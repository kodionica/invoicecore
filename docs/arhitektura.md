# Arhitektura i struktura

## Status nalaza

- **Potvrđeno u kodu:** Laravel SPA; web ruta vraća `resources/views/app.blade.php`, React Router upravlja dashboard-om, a API je u `routes/api.php`.
- **Potvrđeno u manifestima:** PHP `^8.2`, Laravel Framework `v12.53.0` iz `composer.lock`, React `^18.3.1`, TypeScript `^5.9.2`, Vite `^7.0.7`.

## Tehnologije

- Backend: PHP 8.2, Laravel 12, Eloquent ORM i Laravel Sanctum.
- Frontend: React 18, TypeScript, Vite i React Router 7.
- API klijent: Axios, centralizovan u `resources/js/app/context/AppContext.tsx`.
- UI: Tailwind CSS v4, SCSS, Lucide React i postojeće utility komponente.
- PDF: `spatie/laravel-pdf`, Browsershot/Puppeteer i postojeći SSR obrazac.
- Testovi: Pest/PHPUnit; formatiranje radi Laravel Pint.
- Dodatno: TanStack Query za deo dokumenta fakture, Recharts za dashboard, React Hook Form za forme i Puppeteer/Browsershot za PDF.

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

## Pokretanje i migracije

- `composer run dev` pokreće Laravel server, queue listener, Pail i Vite.
- `composer run setup` instalira zavisnosti, generiše ključ, migrira i gradi frontend.
- `php artisan migrate` primenjuje migracije; `php artisan migrate:status` proverava stanje.
- `.env.example` koristi MySQL/MariaDB, dok Laravel konfiguracija ima SQLite fallback.
- Queue je podrazumevano database-backed, ali slanje fakture trenutno koristi sinhroni `Mail::send`.
