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

Kompanija ima `name`, tax ID, registration number, adresu, kontakt, `bank_account`, `iban`, `swift`, logo, prefix i početni broj fakture, valutu, PDV, podrazumevani procenat poreza, rok plaćanja, napomenu fakture i `other_settings`. Trenutni bankarski podaci su jedan skup kolona na `companies`, bez valute računa, naziva banke, dodatnih instrukcija ili redosleda računa. `CompanyController` ih čuva/menja kroz company CRUD, a `InvoiceController` ih koristi kao podatke firme pri generisanju dokumenta.

## Više bankovnih računa i instrukcije po valuti — trenutno stanje

- **Potvrđeno u kodu:** postoji najviše jedan `bank_account`/`iban`/`swift` skup po firmi; nema `BankAccount` modela, migracije, API resursa ili UI liste računa.
- **Potvrđeno u kodu:** faktura trenutno ne čuva izbor računa niti kopiju instrukcija; PDF payload uzima podatke kompanije pri generisanju.
- **Potvrđeno u kodu:** frontend forma traži broj računa pri kreiranju/izmeni firme, dok backend `StoreCompanyRequest`/`UpdateCompanyRequest` prihvata bankarska polja kao obične stringove bez validacije valute ili strukture.
- **Poznato ograničenje:** promena podataka firme može promeniti kasniju regeneraciju PDF-a, a jedan račun ne može pouzdano predstavljati EUR, USD i druge valute.

Predlog za buduću implementaciju je `company_bank_accounts` sa `company_id`, opcionom ISO valutom (`currency`, `null` za opšti račun), `bank_name`, `account_number`, `iban`, `swift_bic`, slobodnim `payment_instructions`, opcionim `additional_fields` JSON-om za banke sa posebnim zahtevima, `is_default`, `is_active` i `display_order`. `Company::bankAccounts()` bi bila `hasMany`, a račun bi pripadao samo jednoj firmi. Na fakturi bi postojao opcion `bank_account_id` za trag izbora i immutable `bank_instructions_snapshot` JSON/text kopija prikazanih podataka; snapshot je autoritativan za istorijski PDF, ne naknadni join ka trenutnom računu.

Valuta računa ne treba automatski da se pretpostavi iz IBAN-a ili SWIFT-a. `currency=null` može označavati opšti račun samo ako se to poslovno potvrdi. Polja ne treba sva učiniti obaveznim: validirati da postoji bar jedan upotrebljiv identifikator računa ili IBAN, a `bank_name`, SWIFT/BIC i dodatne instrukcije učiniti obaveznim samo za scenarije/valute kojima su potrebni.

## Profil i aplikaciona podešavanja

`PUT /api/profile` menja ime, prezime, telefon i username; e-mail je read-only u UI-ju. `PUT /api/password` zahteva trenutnu lozinku i novu lozinku sa potvrdom od najmanje 5 karaktera.

`GET|PUT /api/settings` čuva jezik i `notifications.invoices|clients` u `settings.value` kao JSON. **Poznato ograničenje:** zapis je globalan, nije vezan za korisnika ili firmu; nema vremenske zone, sesija, exporta, backup-a, brisanja naloga ili stvarnog sistema obaveštenja.

**Potvrđeno:** company CRUD, aktivna firma, klijenti, logo i podrazumevani podaci fakture. **Delimično:** profil, lozinka, jezik i preference UI/API. **Nije implementirano:** email verification, reset lozinke e-mailom, sesije, istorija podešavanja, backup/restore i brisanje naloga.
