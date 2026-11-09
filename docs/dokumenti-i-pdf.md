# Dokumenti i PDF

## Invoice PDF

InvoiceController generiše PDF preko Spatie Laravel PDF, Browsershot/Puppeteer i SSR React dokumenta. DDEV web image sadrži Chromium, a Vite manifest i SSR bundle moraju postojati.

PDF verzije su u invoice_pdf_versions, sa verzijom, immutable storage putanjom, protected_at/sent_at i current PDF vezom preko fakture. Zaštićene i aktivne verzije nisu predmet retention brisanja. Retention radi staging kopiju i prijavljuje filesystem/DB grešku bez lažnog uspeha.

Ponovljeni download bez promene koristi postojeću verziju. Regeneracija izdate fakture dodaje novu verziju i čuva prethodnu zaštićenu verziju. E-mail beleži tačnu PDF verziju.

PDF fajlovi su u privatnom storage-u i ne treba ih izlagati javnim URL-ovima. Preuzimanje i istorija proveravaju policy i aktivnu firmu.

## E-mail

InvoiceController::sendEmail koristi invoice_email_sends i idempotency ključ. Isti ključ se ne šalje ponovo; nova namerna operacija dobija novi ključ. Uspešno slanje beleži vreme slanja korišćene PDF verzije. Neuspeh se beleži, a neizvestan ishod se ne predstavlja kao uspeh.

## Bankarski podaci

PDF koristi invoice snapshot payment instrukcija kada postoji, a legacy podatke firme za stare fakture bez snapshot-a.

## KPO PDF

KpoController generiše server-side PDF sa pet fizičkih kolona: redni broj, datum/opis, proizvodi, usluge i ukupno. income_category je primarni izvor, a legacy NULL kategorija koristi poznati income_type mapping. Nepoznata legacy klasifikacija se prikazuje kao unresolved/warning i ne dobija izmišljenu kategoriju.

KPO PDF ne treba označavati kao potpuno pravno usaglašen obrazac bez računovodstvene/propisne potvrde.

## Izdati i legacy dokumenti

React detalj i SSR koriste isti serverski payload. Izdati dokument koristi sačuvane podatke kupca i izdavaoca, logo kopiran u snapshot, efektivne metapodatke i postojeće bankarske i FX kolone. IPS QR se određuje pri izdavanju. Regeneracija kompletne izdate fakture dodaje zaštićenu PDF verziju. Poslovna izmena izdate fakture blokirana je u običnom update toku.

Legacy dokumenti bez potpunih snapshot podataka dostupni su kroz postojeće PDF fajlove i istoriju. Zahtev za regeneraciju vraća 409. Email zahteva izdavanje ili zaštićeni legacy dokument sa dostupnim fajlom. Slanje koristi određenu zaštićenu PDF verziju, a retry sa istim ključem vraća prethodni ishod i čuva stanje naplate.

## Lokalni IPS QR na fakturama

InvoiceController priprema QR kroz postojeći IpsQrService i QrCodeService. React i SSR dobijaju isti serverski payload sa Base64 PNG slikom, tekstualnim IPS payload-om i konkretnim razlogom ako QR nije dostupan. SSR skripta isključivo renderuje prosleđeni dokument.

Pri izdavanju se rezultat čuva u postojećem document_settings kao qr_code, qr_payload i qr_error. Sačuvani qr_code ima prednost i koristi se bez ponovnog generisanja. Nova regeneracija PDF-a koristi tu sliku i pravi novu PDF verziju.

Starija izdata faktura sa potpunim snapshotima i bez QR slike može dobiti lokalno izračunat QR iz sačuvanih podataka. Ako postojeća metadata sadrži eksplicitnu šifru plaćanja, koristi se ta šifra. Čitanje ne upisuje nove finansijske ili QR podatke. Za legacy dokumente bez potvrđenih snapshotova važi postojeći tok zaštićenog PDF-a.

Izvorni frontend pozivi NBS QR generatoru i SSR režim `--qr` uklonjeni su. NBS kursna lista ostaje u postojećem ExchangeRateService toku.

Provereni su generisanje i SSR ugradnja slike, kao i stvarni PDF preko Chromium-a. QR je dekodiran i sa rasterizovane PDF strane. Dva reprezentativna sintetička payload-a sa različitim računima i referencama dobila su rezultat `code: 0` na [zvaničnom NBS validatoru](https://ips.nbs.rs/sr_lat/qr-validacija-generisanje/validator-nbs-ips-qr-koda), 9. novembra 2026.

Regresioni slučajevi su u [InvoiceIpsQrTest.php](../tests/Feature/InvoiceIpsQrTest.php), uz postojeće invoice PDF, IPS unit i poreske feature testove. Test stvarnog PDF-a zahteva završene frontend/SSR build-ove i Chromium.
