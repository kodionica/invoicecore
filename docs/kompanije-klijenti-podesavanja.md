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

Kompanija ima `name`, tax ID, registration number, adresu, kontakt, bank account, IBAN, SWIFT, logo, prefix i početni broj fakture, valutu, PDV, podrazumevani procenat poreza, rok plaćanja, napomenu fakture i `other_settings`. Logo se čuva na public disku, a PDF koristi podatke firme.

## Profil i aplikaciona podešavanja

`PUT /api/profile` menja ime, prezime, telefon i username; e-mail je read-only u UI-ju. `PUT /api/password` zahteva trenutnu lozinku i novu lozinku sa potvrdom od najmanje 5 karaktera.

`GET|PUT /api/settings` čuva jezik i `notifications.invoices|clients` u `settings.value` kao JSON. **Poznato ograničenje:** zapis je globalan, nije vezan za korisnika ili firmu; nema vremenske zone, sesija, exporta, backup-a, brisanja naloga ili stvarnog sistema obaveštenja.

**Potvrđeno:** company CRUD, aktivna firma, klijenti, logo i podrazumevani podaci fakture. **Delimično:** profil, lozinka, jezik i preference UI/API. **Nije implementirano:** email verification, reset lozinke e-mailom, sesije, istorija podešavanja, backup/restore i brisanje naloga.
