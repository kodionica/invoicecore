# Otvorena pitanja i poznata ograničenja

## KPO i računovodstvo

- Da li KPO prihod treba tretirati po izdavanju ili naplati u svim scenarijima?
- Kako se tretiraju storno, otkazivanje i zaključavanje KPO godine?
- Koja su pravila za VAT osnovicu KPO-a i poreska izuzeća?
- Koji dodatni elementi zvaničnog KPO obrasca su potrebni za konkretan poreski slučaj?

## Fakture i banke

- Da li faktura bez matching bankovnog računa sme da se izda ili samo sačuva kao draft?
- Koja dodatna međunarodna polja traži konkretna banka?
- Kako postupati sa legacy fakturama bez bank account snapshot-a?
- Da li su postojeći legacy bank_account/iban/swift podaci dovoljno pouzdani za migraciju?

## Payment domen

Trenutno postoji samo status paid, paid_at i payment_note. Nema parcijalnih uplata, salda, refundacija ili Payment tabele. Ako je to potrebno, mora se posebno definisati valuta uplate, kurs, saldo i audit.

## Operativna ograničenja

- Lokalni backup/restore je host-skriptni workflow za DDEV i iCloud Drive, ne automatski servis.
- Email se šalje sinhrono; idempotency zaštita postoji, ali nema queue-based delivery sistema.
- MariaDB concurrency testovi zahtevaju namensku bazu i eksplicitno uključivanje.
- Ne tvrditi da su migracije, build ili spoljašnja MariaDB provera izvršeni bez konkretne komande.
