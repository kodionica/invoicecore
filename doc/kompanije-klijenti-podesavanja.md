# Kompanije, klijenti i podešavanja

## React stranice

- `/dashboard/companies` — lista kompanija, `resources/js/app/pages/Companies.tsx`.
- `/dashboard/companies/new` — kreiranje, `CompanyForm.tsx`.
- `/dashboard/companies/:id` — detalji i izmene, `CompanyDetails.tsx`.
- `/dashboard/clients` — lista klijenata, `Clients.tsx`.
- `/dashboard/clients/new` i `/dashboard/clients/:id` — forma klijenta, `ClientForm.tsx`.
- `/dashboard/settings` — podešavanja, `Settings.tsx`.

## API

`routes/api.php` koristi `apiResource` za `companies` i `clients`, zatim dodatne rute za promenu aktivne firme, profil, lozinku i settings.

Relevantni kontroleri su `CompanyController`, `ClientController`, `CompanySwitchController` i kontroleri u `app/Http/Controllers/Account/`.

## Podaci firme

Firma čuva podatke kao što su naziv, PIB, matični broj, adresa, valuta, PDV podešavanje i rok plaćanja. Invoice controller koristi valutu, PDV i rok plaćanja aktivne firme pri kreiranju fakture.

Klijent pripada firmi preko `company_id`; faktura može biti kreirana samo za klijenta aktivne firme.
