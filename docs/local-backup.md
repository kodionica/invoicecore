# Lokalni backup i restore

Skripte se pokreću na macOS hostu iz root-a projekta, ne iz DDEV kontejnera. Koriste pokrenut DDEV i MariaDB 11.8.

## Backup

    ./scripts/backup.sh

Podrazumevana destinacija je iCloud Drive folder ~/Library/Mobile Documents/com~apple~CloudDocs/InvoiceCore/Backups. Može se promeniti:

    BACKUP_DESTINATION=/putanja/do/Backups ./scripts/backup.sh

Backup pravi timestamped tar.gz sa manifestom, MariaDB SQL dumpom, storage/app/private i SHA-256 fajlom. Ne uključuje .env, lozinke, vendor, node_modules ili Git fajlove. Prethodne arhive se ne brišu. Skripta proverava da je aktivni DDEV projekat baš InvoiceCore.

Pre restore-a u Finderu proveriti da su arhiva i checksum potpuno sinhronizovani u iCloud-u.

## Provera bez vraćanja podataka

    shasum -a 256 -c invoicecore-YYYYmmdd-HHMMSS.tar.gz.sha256
    tar -tzf invoicecore-YYYYmmdd-HHMMSS.tar.gz
    tar -xOzf invoicecore-YYYYmmdd-HHMMSS.tar.gz manifest.txt

Ove komande ne menjaju bazu ili storage.

## Restore

    ./scripts/restore.sh /putanja/do/invoicecore-YYYYmmdd-HHMMSS.tar.gz

Restore proverava checksum i bezbedne putanje, prikazuje manifest i zahteva unos RESTORE. Pre promene pravi sigurnosni backup trenutnog stanja. Ne menja .env ili kod. Ne pokretati restore bez eksplicitne provere arhive i odobrenja.
