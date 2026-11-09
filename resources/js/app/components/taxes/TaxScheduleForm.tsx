import {useState, type FormEvent} from 'react';
import {toast} from 'sonner';
import {
    parseTaxAmount, standardTaxCategories, taxButton, taxError, taxFrequencyLabels, taxInput, taxesApi,
    type PaymentDetails, type PaymentTag, type TaxFrequency, type TaxSchedule, type TaxVersion,
} from '../../utils/taxObligations';

const paymentFields: {tag: PaymentTag; label: string; limit: number}[] = [
    {tag: 'R', label: 'Račun primaoca', limit: 20},
    {tag: 'N', label: 'Naziv primaoca', limit: 70},
    {tag: 'SF', label: 'Šifra plaćanja', limit: 3},
    {tag: 'RO', label: 'Model i poziv na broj, spojeno', limit: 25},
    {tag: 'S', label: 'Svrha plaćanja', limit: 35},
    {tag: 'P', label: 'Platilac', limit: 70},
];

function versionFields(form: FormData, prefix: string) {
    const payment_details: PaymentDetails = {};
    paymentFields.forEach(({tag}) => {
        payment_details[tag] = String(form.get(prefix + tag) ?? '').trim() || null;
    });

    return {amount_minor: parseTaxAmount(String(form.get(prefix + 'amount') ?? '')), payment_details};
}

function VersionFields({prefix, version}: {prefix: string; version?: TaxVersion}) {
    return (
        <>
            <label className="block text-sm">
                Iznos po periodu (RSD)
                <input name={prefix + 'amount'} inputMode="decimal" className={taxInput}
                    defaultValue={version?.amount_minor == null ? '' : (version.amount_minor / 100).toFixed(2)}
                    placeholder="Prazno za promenljiv iznos"/>
            </label>
            <details className="rounded-md border border-gray-200 p-3">
                <summary className="cursor-pointer text-sm font-medium">Podaci za uplatu i IPS QR</summary>
                <p className="my-2 text-xs text-gray-600">Za QR su potrebni račun, naziv primaoca i šifra plaćanja. Za osnovne poreske kategorije unesite i model i poziv na broj iz rešenja.</p>
                <div className="grid gap-3 sm:grid-cols-2">
                    {paymentFields.map(({tag, label, limit}) => (
                        <label key={tag} className="text-sm">
                            {label}
                            {tag === 'N' || tag === 'P' ? (
                                <textarea name={prefix + tag} rows={2} maxLength={limit} className={taxInput}
                                    defaultValue={version?.payment_details[tag] ?? ''}/>
                            ) : (
                                <input name={prefix + tag} maxLength={limit} className={taxInput}
                                    defaultValue={version?.payment_details[tag] ?? ''}/>
                            )}
                        </label>
                    ))}
                </div>
            </details>
        </>
    );
}

export function TaxScheduleForm({company, categories, annual, year, onSaved}: {
    company: number;
    categories: string[];
    annual: boolean;
    year: number;
    onSaved: (year: number) => void;
}) {
    const [frequency, setFrequency] = useState<TaxFrequency>('monthly');
    const [busy, setBusy] = useState(false);
    const [requestId] = useState(() => crypto.randomUUID());

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const value = (name: string) => String(form.get(name) ?? '').trim();
        const startsOn = value('starts_on');
        setBusy(true);
        try {
            const items = annual
                ? standardTaxCategories.map((category, index) => ({category, name: category, ...versionFields(form, `item${index}_`)}))
                : [{category: value('category'), name: value('name'), ...versionFields(form, 'item0_')}];
            await taxesApi.create(company, {
                request_id: requestId,
                starts_on: startsOn,
                frequency,
                period_count: annual ? '12' : value('period_count') || null,
                due_on: value('due_on') || null,
                due_day: value('due_day') || null,
                due_month_offset: value('due_month_offset') || null,
                roll_forward: form.has('roll_forward'),
                reminder_days: value('reminder_days') || null,
                note: value('note') || null,
                items,
            });
            toast.success('Raspored i periodi početne godine su sačuvani.');
            onSaved(Number(startsOn.slice(0, 4)));
        } catch (error) {
            toast.error(taxError(error));
        } finally {
            setBusy(false);
        }
    }

    return (
        <form className="space-y-4" onSubmit={submit}>
            <fieldset disabled={busy} className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label className="text-sm">Početak
                        <input type="date" name="starts_on" defaultValue={annual ? `${year}-01-01` : undefined}
                            min="2000-01-01" max="2100-12-31" required className={taxInput}/>
                    </label>
                    {!annual && (
                        <>
                            <label className="text-sm">Učestalost
                                <select className={taxInput} value={frequency} onChange={event => setFrequency(event.target.value as TaxFrequency)}>
                                    {Object.entries(taxFrequencyLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                                </select>
                            </label>
                            {frequency !== 'once' && <label className="text-sm">Broj perioda
                                <input type="number" name="period_count" min="1" max="1200" className={taxInput} placeholder="Prazno za neograničeno"/>
                            </label>}
                        </>
                    )}
                    {frequency === 'once' ? (
                        <label className="text-sm">Datum dospeća
                            <input type="date" name="due_on" required className={taxInput}/>
                        </label>
                    ) : (
                        <>
                            <label className="text-sm">Dan dospeća u mesecu
                                <input type="number" name="due_day" min="1" max="31" required className={taxInput}/>
                            </label>
                            <label className="text-sm">Meseci posle poslednjeg meseca perioda
                                <input type="number" name="due_month_offset" min="0" max="12" required className={taxInput} placeholder="0 za isti, 1 za sledeći mesec"/>
                            </label>
                        </>
                    )}
                    <label className="text-sm">Podsetnik, broj dana pre dospeća
                        <input type="number" name="reminder_days" min="0" max="90" className={taxInput} placeholder="Prazno bez podsetnika"/>
                    </label>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="roll_forward"/> Pomeri dospeće na prvi naredni radni dan
                </label>
                <label className="block text-sm">Napomena
                    <textarea name="note" rows={2} maxLength={4000} className={taxInput}/>
                </label>
                {annual ? (
                    <div className="grid gap-3 lg:grid-cols-2">
                        {standardTaxCategories.map((category, index) => (
                            <div key={category} className="space-y-3 rounded-md border border-gray-200 p-3">
                                <h3 className="font-medium">{category}</h3>
                                <VersionFields prefix={`item${index}_`}/>
                            </div>
                        ))}
                    </div>
                ) : (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="text-sm">Kategorija
                                <input name="category" list="tax-categories" required maxLength={100} className={taxInput}/>
                                <datalist id="tax-categories">{categories.map(category => <option key={category} value={category}/>)}</datalist>
                            </label>
                            <label className="text-sm">Naziv obaveze
                                <input name="name" required maxLength={150} className={taxInput}/>
                            </label>
                        </div>
                        <VersionFields prefix="item0_"/>
                    </>
                )}
                <button className={taxButton}>{busy ? 'Čuvanje…' : 'Sačuvaj i generiši periode'}</button>
            </fieldset>
        </form>
    );
}

export function TaxRevisionForm({company, schedule, onSaved}: {
    company: number;
    schedule: TaxSchedule;
    onSaved: () => void;
}) {
    const [busy, setBusy] = useState(false);
    const [effective, setEffective] = useState('');
    const previous = [...schedule.versions].reverse().find(version => version.effective_on <= effective) ?? schedule.versions[0];

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        setBusy(true);
        try {
            await taxesApi.revise(company, schedule.id, {effective_on: effective, ...versionFields(form, 'revision_')});
            toast.success('Nova verzija je sačuvana.');
            onSaved();
        } catch (error) {
            toast.error(taxError(error));
        } finally {
            setBusy(false);
        }
    }

    return (
        <form onSubmit={submit}>
            <fieldset disabled={busy} className="space-y-3">
                <p className="text-sm text-gray-600">Verzija se primenjuje na nove periode koji počinju od izabranog datuma. Postojeći periodi zadržavaju svoje podatke.</p>
                <label className="block text-sm">Važi od
                    <input type="date" className={taxInput} required min={schedule.starts_on} max="2100-12-31"
                        value={effective} onChange={event => setEffective(event.target.value)}/>
                </label>
                <VersionFields key={previous?.id} prefix="revision_" version={previous}/>
                <button className={taxButton}>{busy ? 'Čuvanje…' : 'Sačuvaj novu verziju'}</button>
            </fieldset>
        </form>
    );
}
