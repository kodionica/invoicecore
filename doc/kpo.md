# KPO evidencija

## Stranica

Stranica je `/dashboard/kpo`, implementirana u `resources/js/app/pages/Kpo.tsx`.

Podržava:

- pregled zapisa po godini i datumu;
- zbirne server-side iznose;
- detalj i dozvoljene ručne izmene;
- generisanje preview-a iz faktura;
- idempotentno generisanje zapisa;
- sortiranje kolona ASC → DESC → početni redosled;
- server-side PDF preuzimanje.

`Osveži` samo ponavlja GET zahtev; ne kreira zapise.

## API

| Metod | Ruta | Namena |
|---|---|---|
| GET | `/api/kpo?year=YYYY&from=...&to=...` | Lista i zbirni iznosi |
| POST | `/api/kpo` | Ručno kreiranje ili kreiranje iz fakture |
| GET | `/api/kpo/{id}` | Detalj |
| PUT | `/api/kpo/{id}` | Dozvoljene izmene |
| GET | `/api/kpo/generation-preview?year=YYYY&include_historical=0\|1` | Preview kandidata |
| POST | `/api/kpo/generate` | Kreiranje iz izabranih faktura |
| GET | `/api/kpo/pdf?...` | PDF |

## Pravila generisanja

- Kandidat mora pripadati aktivnoj firmi.
- `include_in_kpo` mora biti `true`.
- Faktura bez postojećeg KPO zapisa može biti ponuđena samo jednom.
- `include_historical` je opt-in za prikaz faktura iz drugih godina.
- Datum KPO evidencije se unosi eksplicitno; ne izvodi se automatski iz statusa ili datuma fakture.
- Datum mora pripadati izabranoj godini.
- Broj zapisa se dobija kroz `kpo_counters` po firmi i godini.
- Ponovljena obrada preskače postojeći zapis i čuva ručne korekcije.
- Invoice-sourced zapis podrazumevano dobija `income_type=usluga`.
- Ukupni iznos računa server iz `amount_rsd + adjustment_rsd`.

Glavna implementacija je u `KpoController`, `KpoEntryService`, `KpoEntry`, Form Request klasama i migracijama KPO tabela.

## Korišćenje

1. Na KPO stranici izaberite godinu.
2. Kliknite „Generiši KPO iz faktura“.
3. Pregledajte ponuđene fakture.
4. Unesite potvrđeni datum KPO evidencije za svaku fakturu.
5. Potvrdite generisanje.

Testne fakture se pre kreiranja označavaju isključenim na formi fakture. Naknadno osvežavanje KPO liste ne pokreće generisanje.
