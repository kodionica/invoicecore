# InvoiceCore dokumentacija

Ovaj folder je trajna referenca zasnovana na proveri koda, migracija, ruta i testova. Oznake u dokumentima znače: **Potvrđeno u kodu**, **Potvrđeno testovima**, **Poznato ograničenje**, **Otvoreno poslovno pitanje** i **Predlog buduće funkcionalnosti**.

## Dokumenti

- [Arhitektura i struktura](./arhitektura.md)
- [Autentifikacija, firme i tenant izolacija](./autentifikacija-i-tenant.md)
- [Kompanije, klijenti i podešavanja](./kompanije-klijenti-podesavanja.md)
- [Fakture](./fakture.md)
- [KPO evidencija](./kpo.md)
- [Frontend i korisničke stranice](./frontend.md)
- [Dokumenti i PDF](./dokumenti-i-pdf.md)
- [Testiranje i razvojne komande](./testiranje-i-razvoj.md)
- [Otvorena poslovna pitanja i poznata ograničenja](./otvorena-pitanja.md)
- [Plan razvoja](./plan-razvoja.md)

## Namena dokumenata

- `arhitektura.md` — tehnologije, tokovi i struktura.
- `autentifikacija-i-tenant.md` — prijava, aktivna firma i izolacija podataka.
- `kompanije-klijenti-podesavanja.md` — firma, klijenti i nalog/podešavanja.
- `fakture.md` — fakture, iznosi, statusi, e-mail i numeracija.
- `kpo.md` — KPO API, generisanje i ograničenja.
- `dokumenti-i-pdf.md` — PDF tokovi i runtime zahtevi.
- `testiranje-i-razvoj.md` — komande i rezultati provera.
- `otvorena-pitanja.md` — nerešena pravila i rizici.
- `plan-razvoja.md` — prioritizovan backlog i kriterijumi prihvatanja.

## Pravilo održavanja

Kada se promeni ruta, model, migracija, API ugovor, poslovno pravilo ili korisnički tok, ažurirati odgovarajući dokument i plan razvoja. Ne označavati otvoreno poslovno pitanje kao rešeno bez potvrde.
