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

## Profil, podešavanja i operativni rizici

- Da li su `app_settings` preference korisničke ili firmine? Pre produkcije obavezno ukloniti globalni scope ili potvrditi da je globalno ponašanje namerno.
- Da li se e-mail šalje sinhrono ili kroz queue, i koji rezultat slanja treba trajno čuvati?
- Da li brisanje firme/fakture sme da bude trajno cascade brisanje ili je potreban archive/soft delete?
- Koji backup/restore cilj, retention i odgovornost važe za bazu, logo fajlove i PDF fajlove?
- Da li aplikacija mora imati verifikaciju e-maila, reset lozinke, revizijski trag i upravljanje sesijama?

## Integracije i produkcija

- Da li je potrebna SEF/eFaktura integracija, u kom obimu i uz koje sertifikate/statusne povratne informacije?
- Koji su podržani izvori kursa, fallback i tolerancija kada eksterni servis nije dostupan?
- Stari `InvoiceFactory`/`InvoiceSeeder` nisu usklađeni sa aktuelnom šemom i referenciraju nepostojeći Payment model; odlučiti da li ih popraviti ili ukloniti kao razvojni dug.

## Operativna ograničenja

- Status migracija zavisi od dostupnosti konfigurisanog MariaDB hosta.
- Nema zasebne frontend test skripte u `package.json`.
- Ne tvrditi da su build ili type-check prošli bez pokretanja konkretne komande.
