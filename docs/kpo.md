# KPO evidencija

## Stranica i API

KPO stranica je /dashboard/kpo. Glavne rute:

- GET /api/kpo — lista i zbirni iznosi
- POST /api/kpo — ručno kreiranje
- PUT /api/kpo/{id} — ručna izmena
- GET /api/kpo/generation-preview — read-only preview
- POST /api/kpo/generate — append-only generation
- GET /api/kpo/regeneration-preview — read-only regeneration preview
- POST /api/kpo/regenerate — eksplicitna regeneracija
- DELETE /api/kpo/{id} — kontrolisano soft-delete uklanjanje
- GET /api/kpo/{id}/history — audit istorija
- GET /api/kpo/pdf — PDF

Sve rute su company-scoped.

## Generisanje

KpoAmountCalculationService koristi sačuvane invoice item total vrednosti i persisted FX snapshot. Product i service mogu proizvesti odvojene KPO redove; unclassified iznos ostaje unresolved i ne raspoređuje se automatski.

Default generisanje je idempotentno i append-only. Postojeći entry, adjustment, broj i ručna izmena se ne prepisuju. Legacy invoice-linked red sa NULL income_category blokira automatsko dodavanje kategorija da se spreči dupli prihod. VAT snapshot, FX i reconciliation pravila mogu blokirati generisanje.

Preview ne rezerviše brojeve. Regeneracija zahteva preview_fingerprint i eksplicitnu potvrdu; manual overwrite je zasebna potvrda. Stale preview se odbija. Obsolete redovi se ne brišu automatski.

## Soft-delete i audit

KPO entry podržava deleted_at. Aktivne liste, totals, PDF i normalno generisanje isključuju obrisane redove. Brojevi se ne renumerišu i ne recikliraju. KpoEntryRevision čuva created, updated, manually_adjusted, regenerated i deleted događaje sa pre/posle vrednostima.

## Datum i numeracija

KPO broj je jedinstven po company/year. Counter se zaključava u transakciji. KPO year mora odgovarati konačnom entry_date. Ako year nije prosleđen, backend koristi tekuću godinu u timezone-u aktivne firme. paid_at je predlog datuma za nove KPO kandidate, bez izmene postojećih redova.

## PDF

KPO PDF čuva redosled i sačuvane iznose. Opis i datum su u jednoj fizičkoj koloni. Nema automatskog dodavanja residual adjustment-a.
