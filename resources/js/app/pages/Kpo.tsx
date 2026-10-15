import React, {useEffect, useMemo, useState} from 'react';
import {BookOpen, Download, Eye, RefreshCw, Save, X} from 'lucide-react';
import {Link} from 'react-router';
import {toast} from 'sonner';
import {formatCurrency} from '../utils/format';
import {useApp} from '../context/AppContext';
import type {KpoEntry, KpoFilters, KpoGenerationPreview, KpoGenerationSelection, KpoTotals} from '../context/AppContext';

const currentYear = new Date().getFullYear();

const formatDate = (value: string) => {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('sr-RS');
};

const inputDate = (value: string) => value ? value.slice(0, 10) : '';

const getErrorMessage = (error: any, fallback: string) => {
    const apiData = error?.response?.data;
    const fieldErrors = apiData?.errors ? Object.values(apiData.errors).flat() : [];
    return apiData?.message || fieldErrors[0] || fallback;
};

const isProduct = (incomeType?: string | null) => {
    const value = incomeType?.toLowerCase() ?? '';
    return value.includes('proiz') || value.includes('product');
};

const isService = (incomeType?: string | null) => {
    const value = incomeType?.toLowerCase() ?? '';
    return value.includes('uslug') || value.includes('servic');
};

const incomeByType = (entry: KpoEntry, type: 'product' | 'service') => {
    const matches = type === 'product' ? isProduct(entry.incomeType) : isService(entry.incomeType);
    return matches ? entry.totalRsd : null;
};

const emptyTotals: KpoTotals = {amountRsd: 0, adjustmentRsd: 0, totalRsd: 0};

export default function Kpo() {
    const {activeCompanyId, loadKpo, downloadKpoPdf, getKpoEntry, updateKpoEntry, previewKpoGeneration, generateKpoEntries} = useApp();
    const [entries, setEntries] = useState<KpoEntry[]>([]);
    const [totals, setTotals] = useState<KpoTotals>(emptyTotals);
    const [selectedYear, setSelectedYear] = useState(String(currentYear));
    const [fromDate, setFromDate] = useState('');
    const [toDate, setToDate] = useState('');
    const [loading, setLoading] = useState(false);
    const [pdfLoading, setPdfLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [selectedEntry, setSelectedEntry] = useState<KpoEntry | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [generationLoading, setGenerationLoading] = useState(false);
    const [generationOpen, setGenerationOpen] = useState(false);
    const [includeHistorical, setIncludeHistorical] = useState(false);
    const [generationPreview, setGenerationPreview] = useState<KpoGenerationPreview | null>(null);
    const [generationSelection, setGenerationSelection] = useState<Record<number, KpoGenerationSelection>>({});
    const [form, setForm] = useState({
        entryDate: '',
        incomeType: '',
        adjustmentRsd: '0.00',
        description: '',
    });

    const filters = useMemo<KpoFilters>(() => ({
        year: selectedYear ? Number(selectedYear) : undefined,
        from: fromDate || undefined,
        to: toDate || undefined,
    }), [fromDate, selectedYear, toDate]);

    const loadEntries = async () => {
        if (!activeCompanyId) {
            setEntries([]);
            setTotals(emptyTotals);
            setError('Izaberite aktivnu firmu da biste videli KPO evidenciju.');
            return;
        }

        setLoading(true);
        setError(null);
        try {
            const response = await loadKpo(filters);
            setEntries(response.entries);
            setTotals(response.totals);
            if (selectedId && !response.entries.some(entry => entry.id === selectedId)) {
                setSelectedId(null);
                setSelectedEntry(null);
            }
        } catch (requestError: any) {
            setError(getErrorMessage(requestError, 'Nije moguće učitati KPO evidenciju.'));
        } finally {
            setLoading(false);
        }
    };

    const handlePdfDownload = async () => {
        setPdfLoading(true);
        try {
            await downloadKpoPdf(filters);
            toast.success('KPO PDF je preuzet.');
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće generisati KPO PDF.'));
        } finally {
            setPdfLoading(false);
        }
    };

    const openGenerationPreview = async (historical = includeHistorical) => {
        if (!activeCompanyId || !filters.year) return;

        setGenerationLoading(true);
        try {
            const response = await previewKpoGeneration(filters.year, historical);
            setGenerationPreview(response);
            setGenerationSelection(current => Object.fromEntries(response.entries.map(invoice => [
                invoice.id,
                current[invoice.id] ?? {invoice_id: invoice.id, entry_date: ''},
            ])));
            setGenerationOpen(true);
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće učitati fakture za KPO generisanje.'));
        } finally {
            setGenerationLoading(false);
        }
    };

    const submitGeneration = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!generationPreview) return;

        const selections = Object.values(generationSelection).filter(selection => selection.entry_date);
        if (!selections.length) {
            toast.error('Izaberite najmanje jednu fakturu i unesite datum KPO evidencije.');
            return;
        }
        if (selections.length !== Object.keys(generationSelection).length) {
            toast.error('Za svaku izabranu fakturu unesite datum KPO evidencije.');
            return;
        }

        setGenerationLoading(true);
        try {
            const result = await generateKpoEntries(generationPreview.year, selections);
            toast.success(`Kreirano: ${result.created.length}, preskočeno: ${result.skipped.length}, neuspešno: ${result.failed.length}.`);
            if (result.failed.length) {
                toast.error(result.failed.map(item => `Faktura ${item.invoice_id}: ${item.reason}`).join(' '));
            }
            setGenerationOpen(false);
            setGenerationPreview(null);
            await loadEntries();
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće generisati KPO zapise.'));
        } finally {
            setGenerationLoading(false);
        }
    };

    useEffect(() => {
        setSelectedId(null);
        setSelectedEntry(null);
        void loadEntries();
    }, [activeCompanyId, filters]);

    useEffect(() => {
        if (!selectedId) return;

        let active = true;
        setDetailLoading(true);
        setDetailError(null);
        getKpoEntry(selectedId)
            .then(entry => {
                if (!active) return;
                setSelectedEntry(entry);
                setForm({
                    entryDate: inputDate(entry.entryDate),
                    incomeType: entry.incomeType ?? '',
                    adjustmentRsd: entry.adjustmentRsd.toFixed(2),
                    description: entry.description ?? '',
                });
            })
            .catch(requestError => {
                if (!active) return;
                setDetailError(getErrorMessage(requestError, 'Nije moguće učitati KPO zapis.'));
            })
            .finally(() => {
                if (active) setDetailLoading(false);
            });

        return () => {
            active = false;
        };
    }, [selectedId]);

    const selectEntry = (id: number) => {
        setSelectedId(id);
        setSelectedEntry(null);
    };

    const closeDetails = () => {
        setSelectedId(null);
        setSelectedEntry(null);
        setDetailError(null);
    };

    const saveEntry = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!selectedEntry || !form.entryDate) {
            toast.error('Datum evidencije je obavezan.');
            return;
        }

        const adjustment = Number(form.adjustmentRsd.replace(',', '.'));
        if (!Number.isFinite(adjustment)) {
            toast.error('Korekcija mora biti validan decimalni broj.');
            return;
        }

        setSaving(true);
        try {
            await updateKpoEntry(selectedEntry.id, {
                entry_date: form.entryDate,
                income_type: form.incomeType || null,
                adjustment_rsd: adjustment,
                description: form.description || null,
            });
            toast.success('KPO zapis je sačuvan.');
            await loadEntries();
            const refreshed = await getKpoEntry(selectedEntry.id);
            setSelectedEntry(refreshed);
            setForm({
                entryDate: inputDate(refreshed.entryDate),
                incomeType: refreshed.incomeType ?? '',
                adjustmentRsd: refreshed.adjustmentRsd.toFixed(2),
                description: refreshed.description ?? '',
            });
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće sačuvati KPO zapis.'));
        } finally {
            setSaving(false);
        }
    };

    const years = Array.from({length: 7}, (_, index) => currentYear - 3 + index);

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <BookOpen className="h-5 w-5"/>
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">KPO evidencija</h1>
                            <p className="mt-1 text-sm text-gray-500">Pregled serverom obračunatih KPO iznosa aktivne firme.</p>
                        </div>
                    </div>
                </div>
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => void openGenerationPreview()}
                        disabled={generationLoading || !activeCompanyId || !filters.year}
                        className="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {generationLoading ? 'Učitavanje...' : 'Generiši KPO iz faktura'}
                    </button>
                    <button
                        type="button"
                        onClick={() => void handlePdfDownload()}
                        disabled={pdfLoading || !activeCompanyId}
                        className="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Download className="h-4 w-4"/>
                        {pdfLoading ? 'Generisanje...' : 'Preuzmi PDF'}
                    </button>
                    <button
                        type="button"
                        onClick={() => void loadEntries()}
                        disabled={loading}
                        className="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`}/>
                        Osveži
                    </button>
                </div>
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label className="block text-sm font-medium text-gray-700">
                        Poslovna godina
                        <select
                            value={selectedYear}
                            onChange={event => setSelectedYear(event.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Sve godine</option>
                            {years.map(year => <option key={year} value={year}>{year}</option>)}
                        </select>
                    </label>
                    <label className="block text-sm font-medium text-gray-700">
                        Od datuma
                        <input type="date" value={fromDate} onChange={event => setFromDate(event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                    </label>
                    <label className="block text-sm font-medium text-gray-700">
                        Do datuma
                        <input type="date" value={toDate} onChange={event => setToDate(event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                    </label>
                </div>
            </div>

            {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <SummaryCard label="Osnovni iznos u RSD" value={totals.amountRsd}/>
                <SummaryCard label="Ručne korekcije" value={totals.adjustmentRsd}/>
                <SummaryCard label="Ukupan prihod u RSD" value={totals.totalRsd} emphasized/>
            </div>

            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">R.br.</th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Datum</th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Opis</th>
                            <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Proizvodi</th>
                            <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Usluge</th>
                            <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Ukupno</th>
                            <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Akcije</th>
                        </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                        {loading ? (
                            <tr><td colSpan={7} className="px-4 py-12 text-center text-sm text-gray-500">Učitavanje KPO evidencije...</td></tr>
                        ) : entries.length === 0 ? (
                            <tr><td colSpan={7} className="px-4 py-12 text-center text-sm text-gray-500">Nema KPO zapisa za izabrani period.</td></tr>
                        ) : entries.map(entry => (
                            <tr key={entry.id} className="transition-colors hover:bg-gray-50">
                                <td className="whitespace-nowrap px-4 py-4 text-sm font-medium text-gray-900">{entry.entryNumber}</td>
                                <td className="whitespace-nowrap px-4 py-4 text-sm text-gray-500">{formatDate(entry.entryDate)}</td>
                                <td className="max-w-xs px-4 py-4 text-sm text-gray-700">
                                    <div className="truncate">{entry.description || '—'}</div>
                                    <div className="mt-1 text-xs text-gray-400">{entry.incomeType || 'Nije klasifikovano'}</div>
                                </td>
                                <td className="whitespace-nowrap px-4 py-4 text-right text-sm text-gray-700">{incomeByType(entry, 'product') === null ? '—' : formatCurrency(incomeByType(entry, 'product')!, 'RSD')}</td>
                                <td className="whitespace-nowrap px-4 py-4 text-right text-sm text-gray-700">{incomeByType(entry, 'service') === null ? '—' : formatCurrency(incomeByType(entry, 'service')!, 'RSD')}</td>
                                <td className="whitespace-nowrap px-4 py-4 text-right text-sm font-bold text-gray-900">{formatCurrency(entry.totalRsd, 'RSD')}</td>
                                <td className="whitespace-nowrap px-4 py-4 text-right">
                                    <button type="button" onClick={() => selectEntry(entry.id)} className="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                        <Eye className="h-4 w-4"/> Pregled
                                    </button>
                                </td>
                            </tr>
                        ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {selectedId && (
                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900">KPO zapis</h2>
                            {selectedEntry && <p className="mt-1 text-sm text-gray-500">#{selectedEntry.entryNumber} · {selectedEntry.sourceType === 'invoice' ? 'Iz fakture' : 'Ručni zapis'}</p>}
                        </div>
                        <button type="button" onClick={closeDetails} className="text-gray-400 hover:text-gray-600" title="Zatvori"><X className="h-5 w-5"/></button>
                    </div>

                    {detailLoading && <p className="text-sm text-gray-500">Učitavanje zapisa...</p>}
                    {detailError && <p className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{detailError}</p>}
                    {selectedEntry && !detailLoading && !detailError && (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <div className="space-y-4 text-sm">
                                <DetailRow label="Osnovni iznos" value={`${formatCurrency(selectedEntry.amountRsd, 'RSD')} (${formatCurrency(selectedEntry.amountOriginal, selectedEntry.currency)})`}/>
                                <DetailRow label="Ručna korekcija" value={formatCurrency(selectedEntry.adjustmentRsd, 'RSD')}/>
                                <DetailRow label="Ukupan prihod" value={formatCurrency(selectedEntry.totalRsd, 'RSD')} emphasized/>
                                {selectedEntry.sourceInvoice && <DetailRow label="Izvorna faktura" value={<Link className="text-indigo-600 hover:text-indigo-900" to={`/dashboard/invoices/${selectedEntry.sourceInvoice.id}`}>#{selectedEntry.sourceInvoice.invoiceNumber || selectedEntry.sourceInvoice.id}</Link>}/>} 
                                <p className="text-xs text-gray-500">Ukupan prihod i zbirni iznosi dolaze sa servera. Klasifikacija proizvoda/usluga prikazuje iznos samo za prepoznatu vrednost tipa prihoda.</p>
                            </div>
                            <form onSubmit={saveEntry} className="space-y-4 rounded-lg bg-gray-50 p-4">
                                <h3 className="text-sm font-semibold text-gray-900">Dozvoljene izmene</h3>
                                <label className="block text-sm font-medium text-gray-700">Datum evidencije
                                    <input required type="date" value={form.entryDate} onChange={event => setForm(current => ({...current, entryDate: event.target.value}))} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                </label>
                                <label className="block text-sm font-medium text-gray-700">Tip prihoda
                                    <input value={form.incomeType} onChange={event => setForm(current => ({...current, incomeType: event.target.value}))} placeholder="npr. proizvod ili usluga" className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                </label>
                                <label className="block text-sm font-medium text-gray-700">Ručna korekcija (RSD)
                                    <input inputMode="decimal" value={form.adjustmentRsd} onChange={event => setForm(current => ({...current, adjustmentRsd: event.target.value}))} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                </label>
                                <label className="block text-sm font-medium text-gray-700">Opis
                                    <textarea rows={3} value={form.description} onChange={event => setForm(current => ({...current, description: event.target.value}))} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                </label>
                                <button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                                    <Save className="h-4 w-4"/> {saving ? 'Čuvanje...' : 'Sačuvaj izmene'}
                                </button>
                            </form>
                        </div>
                    )}
                </div>
            )}

            {generationOpen && generationPreview && (
                <div className="rounded-xl border border-emerald-200 bg-white p-6 shadow-sm">
                    <div className="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900">Generisanje KPO iz faktura</h2>
                            <p className="mt-1 text-sm text-gray-500">Pregled faktura bez postojećeg KPO zapisa za izabranu godinu.</p>
                        </div>
                        <button type="button" onClick={() => setGenerationOpen(false)} className="text-gray-400 hover:text-gray-600" title="Zatvori"><X className="h-5 w-5"/></button>
                    </div>
                    <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Datum KPO evidencije nije automatski izveden iz statusa ili datuma fakture. Unesite potvrđeni datum za svaku fakturu; server će zapis numerisati po godini tog datuma.
                    </div>
                    <label className="mb-4 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={includeHistorical} onChange={event => {
                            const historical = event.target.checked;
                            setIncludeHistorical(historical);
                            void openGenerationPreview(historical);
                        }}/>
                        Prikaži i fakture iz drugih godina (godina izabrana u KPO-u i dalje ne određuje datum)
                    </label>
                    {generationPreview.entries.length === 0 ? (
                        <p className="py-6 text-sm text-gray-500">Nema faktura bez KPO zapisa u ovom pregledu.</p>
                    ) : (
                        <form onSubmit={submitGeneration}>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50"><tr>
                                        <th className="px-3 py-2 text-left">Faktura</th>
                                        <th className="px-3 py-2 text-left">Datum izdavanja</th>
                                        <th className="px-3 py-2 text-right">Iznos</th>
                                        <th className="px-3 py-2 text-right">Iznos RSD</th>
                                        <th className="px-3 py-2 text-left">Status</th>
                                        <th className="px-3 py-2 text-left">Datum KPO evidencije</th>
                                    </tr></thead>
                                    <tbody className="divide-y divide-gray-200">
                                    {generationPreview.entries.map(invoice => (
                                        <tr key={invoice.id}>
                                            <td className="px-3 py-3 font-medium">#{invoice.invoice_number}</td>
                                            <td className="px-3 py-3">{formatDate(invoice.issue_date)}</td>
                                            <td className="px-3 py-3 text-right">{formatCurrency(Number(invoice.total_original), invoice.currency)}</td>
                                            <td className="px-3 py-3 text-right">{formatCurrency(Number(invoice.total_rsd), 'RSD')}</td>
                                            <td className="px-3 py-3">{invoice.status}</td>
                                            <td className="px-3 py-3">
                                                <input
                                                    required
                                                    type="date"
                                                    min={`${generationPreview.year}-01-01`}
                                                    max={`${generationPreview.year}-12-31`}
                                                    value={generationSelection[invoice.id]?.entry_date ?? ''}
                                                    onChange={event => setGenerationSelection(current => ({...current, [invoice.id]: {invoice_id: invoice.id, entry_date: event.target.value}}))}
                                                    className="rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="mt-4 flex justify-end gap-2">
                                <button type="button" onClick={() => setGenerationOpen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Otkaži</button>
                                <button type="submit" disabled={generationLoading} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60">{generationLoading ? 'Generisanje...' : 'Kreiraj KPO zapise'}</button>
                            </div>
                        </form>
                    )}
                </div>
            )}
        </div>
    );
}

function SummaryCard({label, value, emphasized = false}: {label: string; value: number; emphasized?: boolean}) {
    return <div className={`rounded-xl border p-5 shadow-sm ${emphasized ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200 bg-white'}`}>
        <p className="text-sm font-medium text-gray-500">{label}</p>
        <p className="mt-2 text-2xl font-bold text-gray-900">{formatCurrency(value, 'RSD')}</p>
    </div>;
}

function DetailRow({label, value, emphasized = false}: {label: string; value: React.ReactNode; emphasized?: boolean}) {
    return <div className="flex items-start justify-between gap-4 border-b border-gray-100 pb-3">
        <span className="text-gray-500">{label}</span>
        <span className={`text-right ${emphasized ? 'font-bold text-gray-900' : 'text-gray-700'}`}>{value}</span>
    </div>;
}
