# Frontend i korisničke stranice

## Rutiranje

React Router konfiguracija je u `resources/js/app/routes.ts`. Dashboard koristi `DashboardLayout`, a stranice se nalaze u `resources/js/app/pages/`.

Glavne stranice:

- Dashboard — `/dashboard`
- Promet — `/dashboard/turnover`
- KPO — `/dashboard/kpo`
- Klijenti — `/dashboard/clients`
- Fakture — `/dashboard/invoices`
- Kompanije — `/dashboard/companies`
- Podešavanja — `/dashboard/settings`

## API i stanje

`AppContext.tsx` sadrži Axios pozive, normalizaciju API payload-a i globalno stanje za firme, klijente i fakture. KPO metode su `loadKpo`, `previewKpoGeneration`, `generateKpoEntries`, `getKpoEntry`, `updateKpoEntry` i `downloadKpoPdf`.

Za nove pozive koristiti isti obrazac: dodati typed payload, poziv u contextu, normalizaciju odgovora i koristiti ga iz stranice.

## UI konvencije

Postojeće stranice koriste Tailwind klase, card/table layout, `sonner` toast poruke, Lucide ikone i postojeće forme. Tabele faktura i KPO koriste dugmad u zaglavlju sa `aria-sort` i ciklusom ASC/DESC/default.

Ne uvoditi novu state biblioteku ili novu UI biblioteku bez jasne potrebe.
