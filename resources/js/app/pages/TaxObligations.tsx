import {useEffect, useState, type FormEvent} from 'react';
import {toast} from 'sonner';
import TaxObligationDetails, {TaxAttachments, taxMoney} from '../components/taxes/TaxObligationDetails';
import {TaxRevisionForm, TaxScheduleForm} from '../components/taxes/TaxScheduleForm';
import {useApp} from '../context/AppContext';
import {taxesApi, taxError, taxFrequencyLabels, taxInput, taxSecondaryButton} from '../utils/taxObligations';

export default function TaxObligations() {
    const {activeCompanyId} = useApp();
    const [year, setYear] = useState(() => Number(new Intl.DateTimeFormat('en', {year: 'numeric', timeZone: 'Europe/Belgrade'}).format(new Date())));

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold text-gray-900">Porezi i doprinosi</h1>
                {activeCompanyId && <label className="text-sm">Godina perioda
                    <select value={year} onChange={event => setYear(Number(event.target.value))} className={taxInput}>
                        {Array.from({length: 101}, (_, index) => 2100 - index).map(option => <option key={option}>{option}</option>)}
                    </select>
                </label>}
            </div>
            {activeCompanyId
                ? <TaxWorkspace key={`${activeCompanyId}-${year}`} company={activeCompanyId} year={year} onYear={setYear}/>
                : <p className="text-sm text-gray-600">Izaberite aktivnu firmu.</p>}
        </div>
    );
}

function TaxWorkspace({company, year, onYear}: {company: number; year: number; onYear: (year: number) => void}) {
    const [overview, setOverview] = useState<Awaited<ReturnType<typeof taxesApi.overview>>>();
    const [loadError, setLoadError] = useState('');
    const [revision, setRevision] = useState(0);
    const [busy, setBusy] = useState(false);
    const [form, setForm] = useState<'annual' | 'custom' | null>(null);
    const [selected, setSelected] = useState<number | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        setLoadError('');
        taxesApi.overview(company, year, controller.signal)
            .then(data => {
                if (!controller.signal.aborted) setOverview(data);
            })
            .catch(error => {
                if (!controller.signal.aborted) setLoadError(taxError(error));
            });
        return () => controller.abort();
    }, [company, year, revision]);

    function refresh() {
        setRevision(value => value + 1);
    }

    async function generate() {
        setBusy(true);
        try {
            const {created} = await taxesApi.generate(company, year);
            toast.success(created === 0 ? 'Svi periodi za ovu godinu već su generisani ili nema aktivnih rasporeda.' : `Broj novih perioda: ${created}`);
            refresh();
        } catch (error) {
            toast.error(taxError(error));
        } finally {
            setBusy(false);
        }
    }

    async function saveCalendar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const days = String(new FormData(event.currentTarget).get('days') ?? '').trim().split(/[\s,]+/).filter(Boolean);
        setBusy(true);
        try {
            await taxesApi.calendar(company, days);
            toast.success('Neradni dani su sačuvani.');
            refresh();
        } catch (error) {
            toast.error(taxError(error));
        } finally {
            setBusy(false);
        }
    }

    if (!overview) {
        return loadError ? <p role="alert" className="text-red-700">{loadError}</p> : <p role="status" className="text-sm text-gray-600">Učitavanje obaveza…</p>;
    }

    const {totals, obligations, reminders, schedules, today} = overview;

    return (
        <div className="space-y-5">
            {loadError && <p role="alert" className="text-red-700">{loadError}</p>}
            <div className="flex flex-wrap gap-2">
                <button type="button" className={taxSecondaryButton} onClick={() => setForm(form === 'annual' ? null : 'annual')}>Godišnje poresko rešenje</button>
                <button type="button" className={taxSecondaryButton} onClick={() => setForm(form === 'custom' ? null : 'custom')}>Nova obaveza</button>
                <button type="button" className={taxSecondaryButton} disabled={busy} onClick={generate}>Generiši periode za {year}.</button>
            </div>
            {form && (
                <section className="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 className="mb-3 text-lg font-semibold">{form === 'annual' ? 'Godišnje poresko rešenje · 12 mesečnih perioda' : 'Nova obaveza'}</h2>
                    <TaxScheduleForm key={form} company={company} categories={overview.categories} annual={form === 'annual'} year={year}
                        onSaved={createdYear => {
                            setForm(null);
                            if (createdYear === year) refresh();
                            else onYear(createdYear);
                        }}/>
                </section>
            )}

            <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {[
                    ['Obračunato', totals.assessed_minor],
                    ['Uplaćeno za ove periode', totals.paid_minor],
                    ['Preostalo za uplatu', totals.outstanding_minor],
                    ['Dospelo i neplaćeno', totals.overdue_minor],
                ].map(([label, amount]) => (
                    <div key={label} className="rounded-lg border border-gray-200 bg-white p-4">
                        <dt className="text-sm text-gray-600">{label}</dt>
                        <dd className="mt-1 font-semibold">{taxMoney(Number(amount))}</dd>
                    </div>
                ))}
            </dl>
            {totals.unassessed_count > 0 && <p className="text-sm text-gray-600">Periodi za koje treba uneti iznos: {totals.unassessed_count}.</p>}
            {reminders.length > 0 && (
                <section className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <h2 className="font-semibold">Podsetnici za dospeće</h2>
                    <ul className="mt-2 space-y-1 text-sm">
                        {reminders.map(entry => <li key={entry.id}>
                            <button type="button" className="text-left text-indigo-700 underline" onClick={() => setSelected(entry.id)}>
                                {entry.schedule.name} · {entry.due_on} · {taxMoney(entry.outstanding_minor)}
                            </button>
                        </li>)}
                    </ul>
                </section>
            )}
            {selected !== null && (
                <TaxObligationDetails key={selected} company={company} id={selected} today={today}
                    onClose={() => setSelected(null)} onChanged={refresh}/>
            )}

            <section className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table className="w-full text-left text-sm">
                    <caption className="p-4 text-left text-lg font-semibold">Obaveze i istorija perioda</caption>
                    <thead className="bg-gray-50 text-gray-600">
                        <tr>
                            {['Obaveza', 'Period', 'Dospeće', 'Iznos', 'Uplaćeno', 'Preostalo', 'Stanje'].map(label => <th scope="col" key={label} className="px-4 py-3">{label}</th>)}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {obligations.length === 0 && <tr><td colSpan={7} className="p-5 text-gray-500">Nema generisanih perioda za ovu godinu.</td></tr>}
                        {obligations.map(entry => {
                            const overdue = (entry.outstanding_minor ?? 0) > 0 && entry.due_on < today;
                            return (
                                <tr key={entry.id}>
                                    <td className="px-4 py-3">
                                        <button type="button" className="text-left font-medium text-indigo-700 underline" onClick={() => setSelected(entry.id)}>{entry.schedule.name}</button>
                                        <div className="text-xs text-gray-500">{entry.schedule.category}</div>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3">{entry.period_start}{entry.period_start !== entry.period_end && ` – ${entry.period_end}`}</td>
                                    <td className="whitespace-nowrap px-4 py-3">{entry.due_on}</td>
                                    <td className="whitespace-nowrap px-4 py-3">{taxMoney(entry.amount_minor)}</td>
                                    <td className="whitespace-nowrap px-4 py-3">{taxMoney(entry.paid_minor)}</td>
                                    <td className="whitespace-nowrap px-4 py-3">{taxMoney(entry.outstanding_minor)}</td>
                                    <td className={`whitespace-nowrap px-4 py-3 ${overdue ? 'text-red-700' : ''}`}>
                                        {entry.outstanding_minor === null ? 'Čeka iznos' : entry.outstanding_minor === 0 ? (entry.paid_minor ? 'Plaćeno' : 'Bez duga') : overdue ? 'Dospelo' : entry.paid_minor ? 'Delimično plaćeno' : 'Otvoreno'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </section>

            <section className="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                <h2 className="text-lg font-semibold">Rasporedi i verzije</h2>
                {schedules.length === 0 && <p className="text-sm text-gray-500">Nema sačuvanih rasporeda.</p>}
                {schedules.map(schedule => (
                    <details key={schedule.id} className="rounded-md border border-gray-200 p-3">
                        <summary className="cursor-pointer font-medium">{schedule.name} · {taxFrequencyLabels[schedule.frequency]} · od {schedule.starts_on}</summary>
                        <div className="mt-3 space-y-4">
                            {schedule.note && <p className="whitespace-pre-wrap break-words text-sm">{schedule.note}</p>}
                            <ul className="space-y-1 text-sm">
                                {schedule.versions.map(version => (
                                    <li key={version.id}>Od {version.effective_on} · {version.amount_minor === null ? 'Promenljiv iznos' : taxMoney(version.amount_minor)}
                                        {version.payment_details.R && ` · račun ${version.payment_details.R}`}
                                    </li>
                                ))}
                            </ul>
                            {schedule.frequency !== 'once' && <details className="rounded-md border border-gray-200 p-3">
                                <summary className="mb-2 cursor-pointer text-sm font-medium">Nova verzija iznosa i podataka za uplatu</summary>
                                <TaxRevisionForm key={schedule.versions.length} company={company} schedule={schedule} onSaved={refresh}/>
                            </details>}
                            <TaxAttachments company={company} schedule={schedule.id} files={schedule.attachments} onSaved={refresh}/>
                        </div>
                    </details>
                ))}
            </section>
            <details className="rounded-lg border border-gray-200 bg-white p-4">
                <summary className="cursor-pointer font-semibold">Neradni dani firme</summary>
                <form onSubmit={saveCalendar} className="mt-3 space-y-2">
                    <label className="block text-sm">Dodatni neradni datumi, jedan po redu (GGGG-MM-DD)
                        <textarea key={overview.non_working_days.join(',')} name="days" rows={4} className={taxInput}
                            defaultValue={overview.non_working_days.join('\n')} placeholder="GGGG-MM-DD"/>
                    </label>
                    <p className="text-sm text-gray-600">Kada je uključeno pomeranje roka, koriste se subota, nedelja i ovi datumi pri generisanju novih perioda.</p>
                    <button className={taxSecondaryButton} disabled={busy}>Sačuvaj kalendar</button>
                </form>
            </details>
        </div>
    );
}
