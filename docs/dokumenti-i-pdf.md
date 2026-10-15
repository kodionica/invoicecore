# Dokumenti i PDF

## Faktura

PDF fakture generiše `InvoiceController::generatePDF()`. Postojeći obrazac koristi SSR/React dokument, Vite manifest i `resources/js/app/components/InvoiceDocument.tsx`.

Preuzimanje ide kroz `GET /api/invoices/{invoice}/pdf`, uz policy proveru.

PDF se kešira u `invoices/{godina}/{mesec}/faktura-{broj}.pdf`; `?refresh=1` regeneriše dokument. E-mail koristi isti PDF i `resources/views/emails/invoice.blade.php`. Produkcija zahteva Vite manifest, SSR bundle i Chromium/Puppeteer runtime.

## KPO

KPO PDF generiše `KpoController::generatePDF()` server-side iz podataka učitanih iz baze. Sadrži period, podatke firme ako postoje, datum, opis, prihode od proizvoda, prihode od usluga i ukupan prihod.

KPO PDF ruta koristi `spatie/laravel-pdf` i Browsershot/Puppeteer. Frontend preuzima blob kroz `downloadKpoPdf()`.

PDF ne treba opisivati kao pravno usaglašen obrazac bez zasebne potvrde zahteva. Posebno proveriti poslovna pravila za klasifikaciju prihoda i računovodstveni datum.

**Poznato ograničenje:** nema dokumentovanog backup-a PDF fajlova niti verzionisanja renderovanog dokumenta; kasnija regeneracija može koristiti nove podatke firme.
