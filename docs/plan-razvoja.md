# InvoiceCore — plan razvoja

Prioriteti: P0 blokatori integriteta i bezbednosti, P1 svakodnevno poslovanje, P2 produktivnost, P3 spoljne integracije.

## P0

### 1. Tenant i autorizacija

- Stanje: osnovna izolacija aktivne firme i policy zaštita su implementirane i testirane.
- Preostalo: email verification, reset lozinke i session management ako budu potrebni.
- Kriterijum: svaki direktni ID, dokument, PDF i audit endpoint proverava korisnika i aktivnu firmu.

### 2. Operativni recovery

- Stanje: lokalni backup/restore skripti postoje za MariaDB i private storage.
- Preostalo: potvrditi restore proceduru na čistoj lokalnoj kopiji i pravila odgovornosti/retention-a.
- Kriterijum: checksum, manifest, sigurnosni backup i dokumentovan restore bez menjanja koda ili .env-a.

## P1

### 3. Životni ciklus fakture i payment

- Stanje: statusi, paid_at, payment_note, PDF verzije, e-mail istorija i audit postoje.
- Preostalo: samo ako poslovno treba — parcijalne uplate, saldo, refundacije ili formalni Payment domen.
- Kriterijum: odluka pre širenja modela; ne izvlačiti računovodstvena pravila iz statusa paid.

### 4. Bankovni računi po valuti

- Stanje: implementirano više računa po firmi, valuta, default/active, izbor pri fakturi i immutable invoice snapshot.
- Preostalo: potvrditi posebne međunarodne instrukcije i cleanup legacy kolona.
- Kriterijum: račun druge valute/firme se odbija, istorijska faktura koristi snapshot.

### 5. KPO

- Stanje: kategorije proizvoda/usluga, obračun sa persisted snapshotima, preview/generisanje, regeneracija, soft-delete, audit i PDF postoje.
- Preostalo: potvrda računovodstvenih pravila za VAT, period lock, storno i pravnu usklađenost PDF-a.
- Kriterijum: nema duplog prihoda, ručne korekcije se čuvaju, stale preview se odbija.

### 6. Dnevni rad

- Stanje: overdue filter, timezone firme, numeracija po firmi i PDF/e-mail tokovi postoje.
- Preostalo: samo konkretni UX problemi potvrđeni testom; ne uvoditi generički refactor.

## P2

- Export faktura/KPO u dodatne formate ako postoji konkretna potreba.
- Ponude/predračuni i recurring invoices.
- Dodatno PDF brendiranje samo uz potvrđene poslovne zahteve.
- Naprednije korisničke preference i notifikacije.

## P3

- SEF/eFaktura i druge lokalne integracije.
- Queue-based e-mail delivery i automatizovani podsetnici.
- Napredni operativni dashboard i health alati.

## Pravilo prioritizacije

Ne opisivati buduću funkcionalnost kao implementiranu. Za accounting/regulatorne odluke potrebna je potvrda pre promene koda ili šeme.
