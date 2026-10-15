# Autentifikacija, firme i tenant izolacija

## Autentifikacija

Login, registracija i logout su u `app/Http/Controllers/Api/AuthController.php`. API koristi Laravel Sanctum. Zaštićene rute u `routes/api.php` nalaze se u `Route::middleware('auth:sanctum')` grupi.

Frontend koristi Axios sa stateful SPA pristupom i pozivom CSRF cookie mehanizma u `AppContext.tsx`.

## Aktivna firma

Korisnik ima `active_company_id`. Aktivna firma se dobija kroz `User::activeCompany()`. Pre čitanja ili izmene podataka kontroler treba da:

1. proveri da korisnik ima aktivnu firmu;
2. filtrira zapis po `company_id` aktivne firme;
3. izvrši policy/autorizaciju na serveru.

Promena firme koristi `POST /api/companies/switch` i `CompanySwitchController`.

## Pravila izolacije

- Fakture koriste `Invoice::scopeForActiveCompany()` i proveru klijenta aktivne firme.
- KPO lista i generisanje koriste aktivnu firmu i policy proveru.
- Detalji i izmene koriste odgovarajuće policy klase.
- Frontend `activeCompanyId` služi za prikaz, ali nije sigurnosna granica.

Kod novog endpointa ne oslanjati se samo na ID koji šalje frontend. Svi povezani resursi moraju biti ponovo provereni na serveru.
