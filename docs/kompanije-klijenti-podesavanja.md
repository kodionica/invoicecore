# Kompanije, klijenti i podešavanja

## Stranice i API

- /dashboard/companies i /dashboard/companies/new/:id
- /dashboard/clients i /dashboard/clients/new/:id
- /dashboard/settings
- CompanyController, ClientController, CompanySwitchController i Account kontroleri
- API koristi company ownership i active-company autorizaciju

## Podaci firme

Company čuva identitet, adresu, kontakt, legacy bankarske kolone, valutu, PDV podešavanje, rok plaćanja, invoice prefix/counter početak, napomenu i other_settings.

Company.timezone je nullable IANA timezone. Nova firma dobija validnu timezone vrednost browsera kada je dostupna; backend dozvoljava samo DateTimeZone::listIdentifiers(). Stare firme bez vrednosti koriste Europe/Belgrade. Promena timezone ne menja ranije sačuvane DATE vrednosti.

## Više bankovnih računa

company_bank_accounts podržava više aktivnih/domestic/foreign računa, više računa iste valute, default po valuti, IBAN, SWIFT/BIC i instrukcije. UI podržava dodavanje, izmenu, kopiranje, default i deaktiviranje.

InvoiceController pri kreiranju proverava da račun pripada aktivnoj firmi i valuti fakture i čuva snapshot na fakturi. Ne postoji fallback na drugu valutu. Postojeće legacy bank_account, iban i swift kolone ostaju zbog kompatibilnosti.

## Podešavanja i profil

PUT /api/settings čuva user preference language i notifications po user_id. Company podešavanja ostaju na Company modelu i nisu mešana sa korisničkim settings zapisom.

Profil i password endpointi postoje. Email verification, reset lozinke e-mailom i session management nisu implementirani.
