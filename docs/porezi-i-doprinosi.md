# Porezi i doprinosi

Modul je dostupan na `/dashboard/taxes`. Obračun je u dinarima i koristi iznose iz korisnikovog rešenja ili druge dokumentacije.

## Rasporedi i dospeća

- **Godišnje poresko rešenje** u jednom unosu otvara 12 mesečnih perioda za porez, PIO, zdravstveno i nezaposlenost. Za početak usred godine, periodi naredne godine generišu se izborom te godine. **Nova obaveza** omogućava izbor postojeće ili unos nove kategorije, kao i mesečno, kvartalno, godišnje ili jednokratno ponavljanje.
- Jednokratna obaveza ima konkretan datum dospeća. Ponavljajući periodi obuhvataju 1, 3 ili 12 kalendarskih meseci, počev od izabranog meseca. Prvi period počinje izabranog dana. Rok se računa od poslednjeg meseca perioda, uz zadati pomak i dan. Dan veći od dužine meseca svodi se na poslednji dan meseca, a dospeće se ograničava tako da bude na početku perioda ili kasnije.
- Ako je uključeno pomeranje, rok pada na prvi naredni radni dan posle vikenda ili neradnog datuma unetog u kalendaru firme. Datumi praznika i posebni rokovi unose se prema rešenju. Podešavanje se primenjuje pri novom generisanju.
- Početna godina se generiše pri čuvanju rasporeda. Za ostale godine se koristi **Generiši periode**. Godišnji prikaz grupiše po datumu početka perioda, uključujući decembarski period s januarskim dospećem. Uplate u zbiru pripadaju godini perioda koji pokrivaju.
- Verzija iznosa i podataka za uplatu primenjuje se od početka prvog još negenerisanog perioda čiji početak pada na datum važenja ili posle njega. Svi ranije generisani periodi, uključujući unapred generisane, zadržavaju postojeći iznos, rok i verziju.
- Za promenljiv iznos ostavite polje rasporeda prazno. Iznos se unosi u detalju perioda i može se ispraviti do prve evidentirane uplate. Uplate se evidentiraju uz datum i opcionu napomenu, do visine preostalog duga.
- Podsetnik je vidljiv u modulu od podešenog broja dana pre dospeća do dana dospeća, dok je period otvoren. Dospeli neplaćeni iznosi vidljivi su u tabeli i godišnjem zbiru. PDF, PNG i JPEG prilozi do 10 MB mogu se vezati za raspored ili period.

## API i integritet

Rute su pod `/api/companies/{company}/taxes`. Sanctum i serverska provera vlasnika i aktivne firme primenjuju se na svaku rutu. Raspored i period nose `company_id`; verzije, uplate i prilozi učitavaju se kroz pripadajući resurs firme. Prilozi su na privatnom `local` disku i preuzimaju se preko autorizovane rute.

- `GET /` sa `year` vraća periode, rasporede, podsetnike, kalendar i godišnje iznose.
- `POST /schedules` čuva jedan raspored ili grupu do četiri rasporeda i generiše početnu godinu u jednoj transakciji. Ponovljeni zahtev koristi isti `request_id`.
- `POST /generate` sa `year` dodaje nedostajuće periode. Jedinstveni ključ na rasporedu i početku perioda sprečava duplikate.
- `POST /schedules/{schedule}/versions` dodaje verziju sa datumom važenja. `PUT /calendar` čuva dodatne neradne datume firme.
- `GET /obligations/{obligation}` daje uplate, podatke za uplatu i QR prikaz. `PATCH` čuva napomenu i eventualni promenljivi iznos. `POST /obligations/{obligation}/payments` evidentira uplatu uz `request_id`.
- `POST /schedules/{schedule}/attachments` prima datoteku i opcioni `obligation_id`. `GET /schedules/{schedule}/attachments/{attachment}` preuzima datoteku.

Monetarna polja s nastavkom `_minor` su celi brojevi u parama. Generisanje i dodavanje verzija serijalizuju se zaključavanjem firme. Evidencija uplate i izmena promenljivog iznosa zaključavaju red obaveze.

## IPS QR

`QrCodeService` prima UTF-8 tekst i vraća PNG ili SVG. `IpsQrService` formira i validira NBS PR payload i može vratiti PNG bajtove za prikaz ili ugradnju u PDF. PNG zahteva PHP GD. QR u poreskom modulu prikazuje preostali iznos za uplatu. Slike se generišu lokalno po otvaranju detalja.

Šifra plaćanja i poziv na broj prepisuju se iz instrukcija za uplatu. Za četiri osnovne kategorije QR zahteva poziv na broj iz rešenja. U `RO` se upisuje eksplicitni model neposredno ispred poziva na broj. Za model 97 koristi se zapis bez crtica i proverava se kontrolni broj. Tekstualna polja koriste srpsku latinicu i dozvoljenu interpunkciju, u skladu sa NBS validatorom. Nedostajuća obavezna polja prikazuju se kao greška u detalju obaveze.

- [NBS specifikacija](https://www.nbs.rs/export/sites/NBS_site/documents/propisi/propisi-ps/odluka_transfer_2018_prilog.pdf)
- [NBS preporuke za tekstualni zapis](https://ips.nbs.rs/PDF/pdfPreporukeValidacijaLat.pdf)
- [NBS šifre plaćanja](https://www.nbs.rs/export/sites/NBS_site/documents/propisi/propisi-ps/obrasci_platnih_naloga_u_dinarima_2019_p_p3.doc)
- [NBS validator](https://ips.nbs.rs/sr_lat/qr-validacija-generisanje/validator-nbs-ips-qr-koda)

NBS validator je vratio `code: 0` za dva lokalno generisana sintetička PNG-a, sa kompletnim podacima i modelom 97, odnosno sa minimalnim podacima.
