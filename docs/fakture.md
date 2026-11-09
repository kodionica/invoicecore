# Fakture

## Stranice i API

- /dashboard/invoices — lista, filteri, dospele fakture i statusi
- /dashboard/invoices/new — kreiranje
- /dashboard/invoices/:id — detalj, plaćanje, PDF, e-mail i istorija
- GET|POST|PUT|DELETE /api/invoices — company-scoped CRUD
- GET /api/invoices/{invoice}/pdf — privatni PDF
- POST /api/invoices/{invoice}/email — idempotentno slanje
- GET /api/invoices/next-number — predlog broja

Implementacija je u InvoiceController, Form Request klasama, Invoice modelu, policies i AppContext/React stranicama.

Pri kreiranju fakture stavke podrazumevano dobijaju kategoriju Usluga (`service`). Eksplicitno izabrana kategorija ima prednost.

## Iznosi, valute i PDV

Server čuva subtotal, porez i total u originalnoj valuti i RSD, uz fx_rate_to_rsd, fx_provider i fx_date snapshot. Kurs dobavlja ExchangeRateService; nova faktura ne zavisi od kasnije promene kursa.

vat_enabled_at_issue čuva poreski snapshot. KPO eligibility koristi snapshot, a ne trenutni Company.vat_enabled. Istorijske fakture sa NULL snapshotom mogu biti blokirane dok se status eksplicitno ne razreši.

## Numeracija i izmene

Brojevi faktura su jedinstveni po firmi kroz bazu i serversku validaciju. Counter je po firmi, godini i mesecu. Izmena bez promene broja je dozvoljena; konflikt sa drugim brojem vraća validation grešku.

Izmena fakture može zahtevati KPO confirmation. Potvrđene strukturne promene se izvršavaju transakcijski: zadržani KPO zapisi čuvaju ID/broj, obsolete generated zapisi se soft-delete-uju, a audit ostaje sačuvan.

## Statusi i plaćanje

Statusi su draft, sent, paid, overdue i cancelled. Minimalna evidencija plaćanja koristi paid_at i payment_note na fakturi; nema parcijalnih uplata, salda ili Payment tabele. paid zahteva datum uplate za nove promene. Vraćanje iz paid čisti aktivne payment vrednosti uz invoice revision audit.

Dospelost se računa kada due_date postoji, prethodi današnjem datumu firme i status nije paid, cancelled ili draft. Filter se izvršava na backendu pre paginacije.

## Bankovni račun fakture

Faktura čuva bank_account_id i snapshot bankarskih instrukcija. Pri kreiranju se bira aktivan račun odgovarajuće valute; kasnija promena/deaktiviranje računa ne menja već sačuvanu fakturu ili PDF. Zaštićene stare fakture bez snapshot-a koriste sačuvani PDF.

## KPO

include_in_kpo kontroliše da li se faktura nudi za novo KPO generisanje. KPO datum može koristiti paid_at kao predlog, ali korisnik potvrđuje konačan datum. Promena statusa plaćanja ne menja postojeće KPO zapise.

## Otvorena pitanja

Računovodstveno značenje KPO prihoda, storno i zaključavanje perioda dokumentovani su u otvorena-pitanja.md.

## Eksplicitno izdavanje i integritet

`POST /api/invoices/{invoice}/issue` zaključava podatke kupca, izdavaoca i stavki, generiše zaštićeni PDF i upisuje `issued_at`. Ponovljen zahtev vraća istu izdatu fakturu. `sent` ostaje kompatibilan status prikaza, dok ishod slanja pripada email evidenciji.

Obična izmena i brisanje zaključanih poslovnih podataka vraćaju 409. Evidencija naplate i KPO ostaju odvojene operacije. Korektivni dokumenti i dozvoljene tehničke revizije zahtevaju definisana poslovna pravila pre dodavanja zasebnog toka.

Pre primene novih migracija proveriti FK pravila i dostupnost istorijskih PDF verzija. Stari dokumenti sa statusom ili evidencijom zaštite/slanja ostaju zaključani bez automatskog popunjavanja `issued_at`. Klijent ili firma sa povezanom istorijom dobijaju 409 pri brisanju.

## Datum prometa

`service_date` je zaseban datum. Pri kreiranju se bez eksplicitne vrednosti koristi datum izdavanja. Izmena koja izostavi polje čuva sačuvanu vrednost. Za istorijske zapise bez datuma prometa dokument koristi datum izdavanja pri prikazu. Izdavanje zaključava datum prometa zajedno sa ostalim poslovnim podacima.

## Instrukcije za lokalni IPS QR

Na RSD nacrtu forme postoji unos IPS šifre plaćanja, opcione svrhe i modela spojenog sa pozivom na broj. API prima `ips_payment_details` sa ključevima `SF`, `S` i `RO`, kroz postojeće store/update rute. Vrednosti se validiraju postojećim IpsQrService servisom i čuvaju u document_settings. Izostavljanje polja pri API izmeni čuva prethodne instrukcije.

Šifra plaćanja se eksplicitno unosi prema osnovu plaćanja. Poziv na broj se upisuje sa svojim modelom kada je primenljiv. Za ranije fakture bez eksplicitne svrhe ostaje postojeće pravilo korišćenja prvih 35 znakova opisa prve stavke.

Lokalni QR se generiše za RSD fakturu domaćeg izdavaoca i domaćeg klijenta, sa validnim domaćim dinarskim računom. Koristi se originalni iznos fakture, pretvoren u cele pare bez FX konverzije. Devizna faktura, strani klijent, devizni ili strani račun, nedostajuća šifra i nevalidne instrukcije daju objašnjenje na dokumentu.

Vrsta izabranog računa čuva se uz instrukcije pri izboru računa. Već izdata faktura koristi seller/buyer, bankarske i QR snapshot podatke. Eksplicitne instrukcije zaključavaju se pri izdavanju.
