import {useEffect, useState, type FormEvent} from 'react';
import {toast} from 'sonner';
import {formatCurrency} from '../../utils/format';
import {
    parseTaxAmount, taxButton, taxError, taxInput, taxSecondaryButton, taxesApi,
    type TaxAttachment, type TaxObligationDetail,
} from '../../utils/taxObligations';

export function taxMoney(minor: number | null) {
    return minor === null ? '—' : formatCurrency(minor / 100, 'RSD');
}

export function TaxAttachments({company, schedule, obligation, files, onSaved}: {
    company: number;
    schedule: number;
    obligation?: number;
    files: TaxAttachment[];
    onSaved: () => void;
}) {
    const [busy, setBusy] = useState(false);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = event.currentTarget;
        const file = new FormData(form).get('file');
        if (!(file instanceof File) || file.size === 0) return;
        setBusy(true);
        try {
            await taxesApi.attach(company, schedule, file, obligation);
            form.reset();
            toast.success('Prilog je sačuvan.');
            onSaved();
        } catch (error) {
            toast.error(taxError(error));
        } finally {
            setBusy(false);
        }
    }

    return (
        <section className="space-y-2">
            <h3 className="font-medium">Prilozi</h3>
            {files.length > 0 && (
                <ul className="space-y-1 text-sm">
                    {files.map(file => <li key={file.id} className="break-all">
                        <a className="text-indigo-700 underline" href={taxesApi.attachmentUrl(company, schedule, file.id)}>{file.name}</a>
                    </li>)}
                </ul>
            )}
            <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
                <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg" aria-label="Prilog (PDF, PNG ili JPG)"
                    className="max-w-full text-sm" disabled={busy}/>
                <button className={taxSecondaryButton} disabled={busy}>{busy ? 'Otpremanje…' : 'Dodaj prilog'}</button>
            </form>
        </section>
    );
}

export default function TaxObligationDetails({company, id, today, onClose, onChanged}: {
    company: number;
    id: number;
    today: string;
    onClose: () => void;
    onChanged: () => void;
}) {
    const [entry, setEntry] = useState<TaxObligationDetail>();
    const [error, setError] = useState('');
    const [revision, setRevision] = useState(0);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [paymentId, setPaymentId] = useState(() => crypto.randomUUID());

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError('');
        taxesApi.obligation(company, id, controller.signal)
            .then(result => {
                if (!controller.signal.aborted) setEntry(result);
            })
            .catch(error => {
                if (!controller.signal.aborted) setError(taxError(error));
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [company, id, revision]);

    function refresh() {
        setRevision(value => value + 1);
        onChanged();
    }

    async function saveEntry(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        setBusy(true);
        try {
            const amount = form.has('amount') ? parseTaxAmount(String(form.get('amount'))) : null;
            await taxesApi.update(company, id, {
                note: String(form.get('note') ?? ''),
                ...(amount === null ? {} : {amount_minor: amount}),
            });
            toast.success('Podaci su sačuvani.');
            refresh();
        } catch (error) {
            toast.error(taxError(error));
        } finally {
            setBusy(false);
        }
    }

    async function recordPayment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const element = event.currentTarget;
        const form = new FormData(element);
        setBusy(true);
        try {
            const amount = parseTaxAmount(String(form.get('amount')));
            if (amount === null || amount === 0) throw new Error('Unesite iznos uplate veći od nule.');
            await taxesApi.pay(company, id, {
                request_id: paymentId, amount_minor: amount,
                paid_on: String(form.get('paid_on')), note: String(form.get('note') ?? ''),
            });
            setPaymentId(crypto.randomUUID());
            element.reset();
            toast.success('Uplata je evidentirana.');
            refresh();
        } catch (error) {
            toast.error(taxError(error));
        } finally {
            setBusy(false);
        }
    }

    async function copyPaymentData(text: string) {
        try {
            await navigator.clipboard.writeText(text);
            toast.success('Podaci su kopirani.');
        } catch (error) {
            toast.error(taxError(error));
        }
    }

    return (
        <section className="space-y-4 rounded-lg border border-indigo-200 bg-white p-4" aria-label="Detalji obaveze">
            <header className="flex items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">{entry?.schedule.name ?? 'Detalji obaveze'}</h2>
                <button type="button" className={taxSecondaryButton} onClick={onClose}>Zatvori</button>
            </header>
            {loading && <p role="status" className="text-sm text-gray-600">Učitavanje detalja…</p>}
            {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
            {entry && !loading && !error && (
                <>
                    <p className="text-sm">Period {entry.period_start} – {entry.period_end} · Dospeće {entry.due_on} · Preostalo {taxMoney(entry.outstanding_minor)}</p>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-5">
                            <form onSubmit={saveEntry}>
                                <fieldset disabled={busy} className="space-y-2">
                                    {entry.version.amount_minor === null && entry.payments.length === 0 && (
                                        <label className="block text-sm">Iznos ovog perioda (RSD)
                                            <input name="amount" inputMode="decimal" className={taxInput}
                                                defaultValue={entry.amount_minor === null ? '' : (entry.amount_minor / 100).toFixed(2)}/>
                                        </label>
                                    )}
                                    <label className="block text-sm">Napomena za period
                                        <textarea name="note" rows={2} maxLength={4000} defaultValue={entry.note ?? ''} className={taxInput}/>
                                    </label>
                                    <button className={taxSecondaryButton}>Sačuvaj detalje</button>
                                </fieldset>
                            </form>
                            <TaxAttachments company={company} schedule={entry.schedule.id} obligation={id} files={entry.attachments} onSaved={refresh}/>
                            <section className="space-y-2">
                                <h3 className="font-medium">Istorija uplata</h3>
                                {entry.payments.length === 0 ? <p className="text-sm text-gray-600">Nema evidentiranih uplata.</p> : (
                                    <ul className="divide-y divide-gray-200 text-sm">
                                        {entry.payments.map(payment => (
                                            <li key={payment.id} className="py-2">
                                                <span>{payment.paid_on} · {taxMoney(payment.amount_minor)}</span>
                                                {payment.note && <p className="whitespace-pre-wrap break-words text-gray-600">{payment.note}</p>}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                            {(entry.outstanding_minor ?? 0) > 0 && (
                                <form onSubmit={recordPayment}>
                                    <fieldset disabled={busy} className="space-y-2">
                                        <legend className="font-medium">Evidentiraj uplatu</legend>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <label className="text-sm">Iznos (RSD)
                                                <input name="amount" inputMode="decimal" required className={taxInput}
                                                    defaultValue={((entry.outstanding_minor ?? 0) / 100).toFixed(2)}/>
                                            </label>
                                            <label className="text-sm">Datum uplate
                                                <input type="date" name="paid_on" defaultValue={today} max={today} required className={taxInput}/>
                                            </label>
                                        </div>
                                        <label className="block text-sm">Napomena uz uplatu
                                            <input name="note" maxLength={4000} className={taxInput}/>
                                        </label>
                                        <button className={taxButton}>Sačuvaj uplatu</button>
                                    </fieldset>
                                </form>
                            )}
                        </div>
                        <section className="space-y-3">
                            <h3 className="font-medium">Podaci za uplatu</h3>
                            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                {Object.entries({
                                    'Račun primaoca': entry.version.payment_details.R,
                                    'Primalac': entry.version.payment_details.N,
                                    'Šifra plaćanja': entry.version.payment_details.SF,
                                    'Model i poziv na broj': entry.version.payment_details.RO,
                                    'Svrha': entry.version.payment_details.S,
                                    'Platilac': entry.version.payment_details.P,
                                }).filter(([, value]) => value).map(([label, value]) => (
                                    <div key={label}><dt className="text-gray-500">{label}</dt><dd className="whitespace-pre-wrap break-words">{value}</dd></div>
                                ))}
                            </dl>
                            {Object.values(entry.qr.errors).flat().map(message => <p key={message} className="text-sm text-red-700">{message}</p>)}
                            {entry.qr.png && entry.qr.payload && (
                                <>
                                    <img src={entry.qr.png} alt="IPS QR za preostali iznos" width={224} height={224}/>
                                    <div className="flex flex-wrap gap-2">
                                        <button type="button" className={taxSecondaryButton} onClick={() => copyPaymentData(entry.qr.payload!)}>Kopiraj IPS podatke</button>
                                        <a href={entry.qr.png} download={`ips-${id}.png`} className={taxSecondaryButton}>Preuzmi PNG</a>
                                    </div>
                                </>
                            )}
                        </section>
                    </div>
                </>
            )}
        </section>
    );
}
