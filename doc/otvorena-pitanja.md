# Otvorena pitanja i poznata ograničenja

Ovo nisu pretpostavljena pravila. Potrebna je potvrda pre promene poslovne logike.

## KPO

- Da li se KPO prihod evidentira po izdavanju fakture, naplati ili drugom događaju?
- Koji datum treba koristiti kada se datum izdavanja i datum naplate razlikuju?
- Da li status `paid` ima računovodstveno značenje ili je samo aplikativni status? U repozitorijumu nije potvrđen poseban Payment domen.
- Kako se klasifikuju proizvodi i usluge? `InvoiceItem` nema potvrđeno polje za tu klasifikaciju; trenutno generisani invoice-sourced KPO dobija podrazumevani `usluga`.
- Kako treba tretirati storno, otkazivanje, izmene izvornog dokumenta i više uplata?
- Kada se KPO godina zaključava i da li su izmene posle zaključavanja dozvoljene?
- Da li je trenutni PDF pravno usaglašen obrazac? To nije potvrđeno kodom.

## Fakture

- Da li isključenje `include_in_kpo` treba da bude dozvoljeno i za fakturu koja već ima KPO zapis? Trenutna implementacija ne briše i ne menja postojeći zapis.
- Da li treba omogućiti naknadnu izmenu ovog polja na detalju fakture? Trenutni zahtev pokriva kreiranje fakture.

## Operativna ograničenja

- Status migracija zavisi od dostupnosti konfigurisanog MariaDB hosta.
- Nema zasebne frontend test skripte u `package.json`.
- Ne tvrditi da su build ili type-check prošli bez pokretanja konkretne komande.
