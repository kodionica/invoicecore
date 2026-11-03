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

## Profili klijenata

Podržani tipovi su `company`, `entrepreneur` i `individual`. Novi klijent dobija `RS` kada država nije prosleđena. Migracija čuva originalni `country` i popunjava `country_code` samo za prepoznate kodove i aliase. Legacy tipovi `b2b` i `b2c` se mapiraju, a konflikt identifikatora zaustavlja migraciju pre izmene šeme.

PIB, registracioni broj i kontakt podaci su opcioni. Za Srbiju API i CSV proveravaju kontrolnu cifru PIB-a i osmocifreni format matičnog broja. Strani identifikatori ostaju stringovi sa vodećim nulama. Jedinstvenost popunjenih identifikatora važi po firmi i poznatoj državi, pojedinačno po polju. CSV prima stare kolone i tipove i prijavljuje konflikte. [Pravilnik o poreskom identifikacionom broju](https://www.paragraf.rs/propisi/pravilnik_o_poreskom_identifikacionom_broju.html)

Za novu fakturu važi redosled eksplicitni izbor, preferencija klijenta, postojeća podrazumevana vrednost. Za dokument je podržan `sr-Latn`. Izdavanje čuva tip, državu, poštanski broj i identifikatore kupca u snapshot-u. Automatski IPS QR za nove tipove zahteva definisan osnov plaćanja i odgovarajuću šifru. Postojeće sačuvane QR i PDF verzije ostaju dostupne. [NBS IPS preporuke](https://ips.nbs.rs/PDF/pdfPreporukeNovoLat.pdf)
