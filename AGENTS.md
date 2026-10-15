# InvoiceCore — Codex projektna pravila

Ovaj dokument opisuje provereno stanje repozitorijuma. Pre svake izmene proveriti da li se arhitektura u kodu promenila.

## Tehnologije i arhitektura

- Backend: PHP 8.2, Laravel 12, Eloquent, Laravel Sanctum, Pest/PHPUnit.
- Frontend: React 18, TypeScript, Vite, React Router 7, Axios, Tailwind CSS v4, SCSS.
- UI biblioteke: Recharts, Lucide React, Sonner, React Hook Form.
- PDF: `spatie/laravel-pdf`, Browsershot/Puppeteer i React SSR.
- Aplikacija je Laravel SPA: `routes/web.php` vraća `resources/views/app.blade.php`, a React Router upravlja dashboard stranicama.
- API je u `routes/api.php`; zaštićene rute koriste `auth:sanctum` i stateful SPA middleware definisan u `bootstrap/app.php`.

## Struktura i važne putanje

- `app/Models/` — `User`, `Company`, `Client`, `Invoice`, `InvoiceItem`, `Setting`.
- `app/Http/Controllers/` — REST kontroleri; API kontroleri su u `Api/`, account kontroleri u `Account/`.
- `app/Http/Requests/` — Form Request validacija.
- `app/Policies/` — autorizacija za kompanije, klijente i fakture.
- `app/Services/` — servisna logika; trenutno `ExchangeRateService`.
- `app/Enums/InvoiceStatus.php` — statusi fakture.
- `database/migrations/` — šema i naknadne izmene baze.
- `database/factories/` i `database/seeders/` — testni/početni podaci.
- `resources/js/app/` — React aplikacija, stranice, layout, komponente, context i utility funkcije.
- `resources/js/app/context/AppContext.tsx` — trenutno globalno frontend stanje i Axios API pozivi.
- `resources/js/app/routes.ts` — React Router rute.
- `resources/js/app/components/InvoiceDocument.tsx` — dokument fakture za ekran i SSR/PDF.
- `resources/js/ssr/` i `scripts/render-invoice-ssr.mjs` — SSR renderovanje dokumenata.
- `resources/css/` i `resources/js/styles/` — SCSS, Tailwind i tema.
- `tests/Feature/`, `tests/Unit/`, `tests/Pest.php` — testovi i zajednička konfiguracija.

## Backend pravila

- Pre izmene pronaći postojeći model, kontroler, request, policy, servis ili komponentu koji pokriva isti slučaj.
- Koristiti postojeći Eloquent stil i relacije. Trenutne ključne relacije su `User::companies()`, `User::activeCompany()`, `Company::clients()`, `Company::invoices()`, `Client::invoices()` i `Invoice::items()`.
- Validaciju staviti u postojeći Form Request kada je vezana za kreiranje/izmenu resursa; inline validacija se trenutno koristi za jednostavan update fakture.
- Status fakture mora koristiti `App\Enums\InvoiceStatus`, a ne proizvoljne stringove.
- Poslovnu logiku obračuna i FX snapshot-a držati na serveru. `InvoiceController::store()` trenutno računa stavke, porez i `*_original`/`*_rsd` iznose, uz `ExchangeRateService`.
- Ne izlagati nove podatke samo kroz frontend; svaki pristup mora biti zaštićen na serveru.
- Greške prijavljivati stvarnim Laravel HTTP statusom i postojećim JSON obrascem; ne gutati izuzetke bez razloga.
- Ne uvoditi servis, repository ili paket ako postojeći obrazac rešava zahtev.

## Autentifikacija, autorizacija i tenant izolacija

- Login/register/logout su u `app/Http/Controllers/Api/AuthController.php`.
- Frontend koristi Axios sa `withCredentials: true` i Sanctum CSRF cookie pozivom.
- Aktivna firma je `users.active_company_id`, a korisnik je vlasnik firmi preko `companies.user_id`.
- Liste klijenata i faktura moraju filtrirati aktivnu firmu (`forActiveCompany`).
- Kreiranje fakture mora proveriti da izabrani klijent pripada aktivnoj firmi.
- Detalji, izmene, brisanje i PDF moraju koristiti policy/autorizaciju; postojeće politike proveravaju vlasnika kompanije.
- Kod novog resursa sa `company_id` obavezno proveriti i pripadnost prijavljenom korisniku i pripadnost aktivnoj firmi gde je to deo UI konteksta.
- Uvek dodati test da korisnik druge firme ne može čitati ili menjati podatke.
- Ne oslanjati se samo na frontend filtere ili `activeCompanyId` iz React state-a.

## Baza i migracije

- Svaka promena šeme ide kroz novu migraciju u `database/migrations/`; ne menjati već primenjene migracije radi popravke produkcionih podataka.
- Ne koristiti destruktivne database komande i ne brisati postojeće podatke bez jasnog zahteva.
- Za tenant podatke koristiti foreign key ka `companies` i odgovarajuće indekse/unique constraint-e.
- Pre dodavanja numeracije ili duplikat zaštite proveriti postojeće constraint-e. Fakture trenutno imaju mesečne brojače po `company_id`, `year`, `month` u `invoice_counters`.
- Ne pretpostavljati da `paid` znači stvarnu naplatu: trenutno postoji samo `invoices.status` i `payment_method`; `Payment` tabela/model nisu potvrđeni kao postojeći.
- `InvoiceItem` nema potvrđeno polje za razlikovanje proizvoda i usluge.
- Migracije moraju čuvati postojeće vrednosti, imati bezbedan rollback gde je realno i biti proverene kroz `RefreshDatabase` testove.

## API i frontend pravila

- Postojeće API rute proveriti u `routes/api.php`; ne izmišljati route name, endpoint ili HTTP metod.
- React API pozive dodavati kroz obrazac u `AppContext.tsx` i normalizovati server payload pre čuvanja u state-u.
- Postojeće stranice koriste `DashboardLayout`, React Router, Tailwind klase i Sonner toast poruke.
- Forme pratiti obrasce iz `InvoiceForm.tsx`, `ClientForm.tsx` i `CompanyForm.tsx`; koristiti postojeću validaciju i prikaz grešaka.
- Za tabele, filtere i dashboard prikaze prvo ponovo koristiti postojeće komponente i obrasce iz `Invoices.tsx`, `Turnover.tsx` i `components/invoices/`.
- Ne uvoditi novu state biblioteku. React Query se trenutno koristi u `InvoiceDocument`, dok se poslovno stanje vodi kroz `AppContext`.
- Ne menjati postojeći UI sistem bez potrebe: Tailwind v4, SCSS, Inter font, indigo primarna boja, postojeći spacing i card/table obrasci.

## Fakture, valute i PDF

- Faktura je povezana sa kompanijom, klijentom i stavkama preko `company_id`, `client_id` i `invoice_id`.
- Iznosi postoje u originalnoj valuti i, kroz FX snapshot, u RSD (`total_original`, `total_rsd` itd.). Kurs dobavlja `ExchangeRateService`.
- PDF endpoint je `GET /api/invoices/{invoice}/pdf`; proverava policy pre preuzimanja.
- Generisanje PDF-a koristi SSR bundle `bootstrap/ssr/renderInvoiceDocument.js` i Vite manifest `public/build/manifest.json`.
- Za novi dokument prvo ponovo koristiti postojeći SSR/PDF obrazac i proveriti da su bundle i manifest dostupni.

## Testiranje i provere

Relevantne komande:

```bash
php artisan test
composer test
php artisan route:list --path=api --except-vendor
npx tsc --noEmit
npm run build
npm run build:ssr
```

- `php artisan test` pokreće Pest testove uz SQLite in-memory bazu iz `phpunit.xml`.
- Nema zasebne lint npm skripte. Laravel Pint je instaliran kao dev dependency, ali nije definisana skripta.
- Ne tvrditi da je provera uspešna ako nije pokrenuta.
- Pri prijavi rezultata navesti tačnu komandu, rezultat i poznate neuspehe.
- Trenutno potvrđena TypeScript napomena: `npx tsc --noEmit` prijavljuje TS5097 zbog `.tsx` ekstenzije u importu `resources/js/main.tsx`; ne popravljati to u okviru nepovezanog zadatka.

## KPO i nepotvrđene poslovne činjenice

- KPO backend model, migracije, servis, kontroler, API rute, React stranica i server-side PDF izvoz sada postoje; payment domen nije potvrđen kao postojeći.
- Pre KPO implementacije potvrditi da li se evidentiraju izdati ili naplaćeni prihodi, koji datum i iznos se koriste, kako se razlikuju proizvodi/usluge, kako rade ručne korekcije, storno, zaključavanje i numeracija po firmi/godini.
- Ne donositi računovodstvene odluke na osnovu naziva `paid`, `Turnover` ili postojećeg UI-ja bez potvrđenog poslovnog pravila.
- Ako zahtev zavisi od naplate, prvo proveriti da li je uveden stvarni payment domen; trenutno nije potvrđen.

## Opšta pravila rada

- Uvek prvo pokrenuti `git status --short` i pregledati relevantni diff.
- Pre izmene pročitati postojeći kod, proveriti import-e, pakete, rute, tipove i relacije.
- Pre početka navesti plan i tačne dozvoljene fajlove; menjati samo potrebne fajlove.
- Sačuvati korisničke izmene i ne koristiti `git reset --hard`, `git checkout --`, masovno brisanje ili druge destruktivne komande bez jasnog zahteva.
- Ne instalirati pakete bez jasne potrebe i odobrenja.
- Veće funkcionalnosti raditi u nezavisnim, proverljivim fazama; posle svake faze pokrenuti relevantne provere.
- Pregledati kompletan diff, proveriti API/frontend ugovore i navesti sve preostale probleme.
- Ne implementirati KPO u okviru dokumentacionog zadatka.
- Pre izmene postojeće funkcionalnosti pročitati relevantni dokument iz `docs/`.
- Kada se promene API ugovor, model, migracija, poslovno pravilo ili korisnički tok, ažurirati odgovarajući dokument i `docs/plan-razvoja.md` ako se stanje ili prioritet promenio.
- Otvorena poslovna ili računovodstvena pitanja ne označavati kao rešena bez potvrde.
- Nikada ne tvrditi da je nešto testirano ako komanda nije stvarno pokrenuta.
- Za dokumentacione izmene koristiti `git diff --check`, proveriti Markdown linkove i ne menjati aplikacioni kod, šemu ili podatke bez zasebnog zahteva.
