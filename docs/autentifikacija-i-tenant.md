# Autentifikacija, firme i tenant izolacija

## Autentifikacija

Login, registracija i logout su u app/Http/Controllers/Api/AuthController.php. API koristi Laravel Sanctum i zaštićene rute u routes/api.php koriste auth:sanctum. Frontend koristi stateful Axios i CSRF cookie.

## Aktivna firma

Aktivna firma je users.active_company_id, a User::activeCompany() vraća firmu. Promena se radi preko POST /api/companies/switch. Liste i mutacije nad fakturama, klijentima, KPO zapisima i bankovnim računima proveravaju aktivnu firmu na serveru.

## Potvrđeno

- Fakture koriste company_id scope i InvoicePolicy.
- Klijenti koriste company_id scope i ClientPolicy.
- KPO, PDF, PDF verzije, istorija, e-mail istorija i company resursi imaju serversku autorizaciju.
- Direktan ID druge firme ne prebacuje aktivnu firmu i ne vraća podatke resursa.
- Korisnici mogu imati više firmi; promena firme osvežava frontend kontekst.

## Podešavanja

Korisničke preference app_settings su izolovane po user_id u settings tabeli. Legacy globalni zapis bez user_id ostaje sačuvan, ali se ne koristi kao korisničko podešavanje. Globalni helper setting() ima odvojenu semantiku i ne sme se koristiti za user preference bez odgovarajućeg scope-a.

## Vremenska zona

Company ima nullable timezone. Company::effectiveTimezone() koristi sačuvani validni IANA identifikator ili Europe/Belgrade kao fallback. Sistemski timestampovi ostaju UTC; poslovni datum aktivne firme koristi njenu efektivnu zonu.

## Ograničenja

Email verification, reset lozinke e-mailom i upravljanje sesijama nisu deo potvrđenog scope-a. Ne tretirati frontend activeCompanyId kao sigurnosnu granicu.
