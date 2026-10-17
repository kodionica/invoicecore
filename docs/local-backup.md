# Lokalni backup i restore

Skripte koriste pokrenuti DDEV projekat, MariaDB dump i privatni direktorijum `storage/app/private`. Backup arhive se podrazumevano čuvaju u:

`~/Library/Mobile Documents/com~apple~CloudDocs/InvoiceCore/Backups`

## Backup

```bash
scripts/backup.sh
```

Skripta kreira `.tar.gz` arhivu, `manifest.txt` unutar arhive i zaseban `.sha256` fajl. Ne uključuje `.env`, lozinke, kod, Git, `vendor` ili `node_modules`. Prethodne arhive se ne brišu.

Druga destinacija:

```bash
BACKUP_DESTINATION="/putanja/do/Backups" scripts/backup.sh
```

Backup se smatra sinhronizovanim tek kada se arhiva i pripadajući `.sha256` fajl vide u iCloud Drive folderu i iCloud status više ne prikazuje čekanje sinhronizacije. Pre restore-a proverite da je fajl potpuno dostupan lokalno.

## Provera bez vraćanja podataka

```bash
shasum -a 256 -c invoicecore-YYYYmmdd-HHMMSS.tar.gz.sha256
tar -tzf invoicecore-YYYYmmdd-HHMMSS.tar.gz
tar -xOzf invoicecore-YYYYmmdd-HHMMSS.tar.gz manifest.txt
```

Ove komande samo čitaju backup.

## Restore

Restore zahteva pokrenut DDEV, proverava checksum i sadržaj arhive, prikazuje manifest i zahteva unos `RESTORE`:

```bash
scripts/restore.sh "/putanja/do/invoicecore-YYYYmmdd-HHMMSS.tar.gz"
```

Pre promene pravi novi sigurnosni backup trenutne baze i privatnog storage-a u `BACKUP_DESTINATION`. Ne menja `.env` niti aplikacioni kod. Ako restore baze ili storage-a ne uspe, operacija se prekida uz putanju sigurnosnog backupa za oporavak.

Restore nije pokretan automatski i ne treba ga izvršavati nad aktivnim podacima bez prethodne provere arhive.
