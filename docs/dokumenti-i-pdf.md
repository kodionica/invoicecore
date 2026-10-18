# Dokumenti i PDF

## Invoice PDF

InvoiceController generiše PDF preko Spatie Laravel PDF, Browsershot/Puppeteer i SSR React dokumenta. DDEV web image sadrži Chromium, a Vite manifest i SSR bundle moraju postojati.

PDF verzije su u invoice_pdf_versions, sa verzijom, immutable storage putanjom, protected_at/sent_at i current PDF vezom preko fakture. Zaštićene i aktivne verzije nisu predmet retention brisanja. Retention radi staging kopiju i prijavljuje filesystem/DB grešku bez lažnog uspeha.

Ponovljeni download bez promene koristi postojeću verziju. Izmena poslate fakture pravi novu trenutnu verziju i čuva prethodnu zaštićenu verziju. E-mail beleži tačnu PDF verziju.

PDF fajlovi su u privatnom storage-u i ne treba ih izlagati javnim URL-ovima. Preuzimanje i istorija proveravaju policy i aktivnu firmu.

## E-mail

InvoiceController::sendEmail koristi invoice_email_sends i idempotency ključ. Isti ključ se ne šalje ponovo; nova namerna operacija dobija novi ključ. Uspešno slanje štiti korišćenu PDF verziju i postavlja fakturu na sent. Neuspeh se beleži, a neizvestan ishod se ne predstavlja kao uspeh.

## Bankarski podaci

PDF koristi invoice snapshot payment instrukcija kada postoji, a legacy podatke firme za stare fakture bez snapshot-a.

## KPO PDF

KpoController generiše server-side PDF sa pet fizičkih kolona: redni broj, datum/opis, proizvodi, usluge i ukupno. income_category je primarni izvor, a legacy NULL kategorija koristi poznati income_type mapping. Nepoznata legacy klasifikacija se prikazuje kao unresolved/warning i ne dobija izmišljenu kategoriju.

KPO PDF ne treba označavati kao potpuno pravno usaglašen obrazac bez računovodstvene/propisne potvrde.
