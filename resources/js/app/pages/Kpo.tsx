import React, {useEffect, useMemo, useState} from 'react';
import {ArrowDown, ArrowUp, ArrowUpDown, BookOpen, Download, Eye, RefreshCw, Save, X} from 'lucide-react';
import {Link} from 'react-router';
import {toast} from 'sonner';
import {formatCurrency} from '../utils/format';
import {useApp} from '../context/AppContext';
import type {KpoEntry, KpoFilters, KpoGenerationPreview, KpoGenerationResult, KpoGenerationSelection, KpoRegenerationPreview, KpoRegenerationResult, KpoTotals} from '../context/AppContext';

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
    const matches = entry.incomeCategory === 'product'
        ? type === 'product'
        : entry.incomeCategory === 'service'
            ? type === 'service'
            : type === 'product' ? isProduct(entry.incomeType) : isService(entry.incomeType);
    return matches ? entry.totalRsd : null;
};

const regenerationStatusLabel = (status: string) => ({
    ready: 'Spremno',
    partial: 'Delimično',
    blocked: 'Blokirano',
}[status] ?? status);

const regenerationActionLabel = (action: string) => ({
    create: 'Nedostaje — kreiranje',
    update: 'Izmenjeno — ažuriranje',
    retain: 'Bez promene',
}[action] ?? action);

const revisionChangedFields = (revision: any) => {
    const previous = revision.previous_values ?? {};
    const next = revision.new_values ?? {};
    return [...new Set([...Object.keys(previous), ...Object.keys(next)])].filter(key => JSON.stringify(previous[key]) !== JSON.stringify(next[key]));
};

const emptyTotals: KpoTotals = {amountRsd: 0, adjustmentRsd: 0, totalRsd: 0};

type SortColumn = 'entryNumber' | 'date' | 'description' | 'product' | 'service' | 'total';
type SortDirection = 'asc' | 'desc';

export default function Kpo() {
    const {activeCompanyId, loadKpo, downloadKpoPdf, getKpoEntry, updateKpoEntry, deleteKpoEntry, removeGeneratedKpoEntry, getKpoHistory, previewKpoGeneration, generateKpoEntries, previewKpoRegeneration, regenerateKpoEntries} = useApp();
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
    const [historyLoading, setHistoryLoading] = useState(false);
    const [history, setHistory] = useState<any[] | null>(null);
    const [saving, setSaving] = useState(false);
    const [removalReason, setRemovalReason] = useState('');
    const [generationLoading, setGenerationLoading] = useState(false);
    const [generationOpen, setGenerationOpen] = useState(false);
    const [includeHistorical, setIncludeHistorical] = useState(false);
    const [generationPreview, setGenerationPreview] = useState<KpoGenerationPreview | null>(null);
    const [generationSelection, setGenerationSelection] = useState<Record<number, KpoGenerationSelection>>({});
    const [confirmManualOverlap, setConfirmManualOverlap] = useState(false);
    const [regenerationLoading, setRegenerationLoading] = useState(false);
    const [regenerationOpen, setRegenerationOpen] = useState(false);
    const [regenerationPreview, setRegenerationPreview] = useState<KpoRegenerationPreview | null>(null);
    const [confirmRegeneration, setConfirmRegeneration] = useState(false);
    const [confirmManualOverwrite, setConfirmManualOverwrite] = useState(false);
    const [regenerationStale, setRegenerationStale] = useState(false);
    const [lastRegenerationResult, setLastRegenerationResult] = useState<KpoRegenerationResult | null>(null);
    const [sort, setSort] = useState<{ column: SortColumn; direction: SortDirection } | null>(null);
    const [form, setForm] = useState({
        entryDate: '',
        incomeType: '',
        incomeCategory: 'unclassified' as 'product' | 'service' | 'unclassified',
        adjustmentRsd: '0.00',
        description: '',
    });

    const filters = useMemo<KpoFilters>(() => ({
        year: selectedYear ? Number(selectedYear) : undefined,
        from: fromDate || undefined,
        to: toDate || undefined,
    }), [fromDate, selectedYear, toDate]);

    const sortedEntries = useMemo(() => {
        if (!sort) return entries;

        return [...entries].sort((a, b) => {
            let comparison: number;
            switch (sort.column) {
                case 'entryNumber':
                    comparison = a.entryNumber - b.entryNumber;
                    break;
                case 'date':
                    comparison = Date.parse(a.entryDate) - Date.parse(b.entryDate);
                    if (Number.isNaN(comparison)) comparison = a.entryDate.localeCompare(b.entryDate);
                    break;
                case 'description':
                    comparison = (a.description ?? '').localeCompare(b.description ?? '', 'sr', {sensitivity: 'base'});
                    break;
                case 'product':
                    comparison = (incomeByType(a, 'product') ?? 0) - (incomeByType(b, 'product') ?? 0);
                    break;
                case 'service':
                    comparison = (incomeByType(a, 'service') ?? 0) - (incomeByType(b, 'service') ?? 0);
                    break;
                case 'total':
                    comparison = a.totalRsd - b.totalRsd;
                    break;
            }

            return sort.direction === 'asc' ? comparison : -comparison;
        });
    }, [entries, sort]);

    const toggleSort = (column: SortColumn) => {
        setSort(current => {
            if (current?.column !== column) return {column, direction: 'asc'};
            if (current.direction === 'asc') return {column, direction: 'desc'};
            return null;
        });
    };

    const sortIcon = (column: SortColumn) => {
        if (sort?.column !== column) return <ArrowUpDown className="ml-1 inline h-3 w-3"/>;
        return sort.direction === 'asc'
            ? <ArrowUp className="ml-1 inline h-3 w-3"/>
            : <ArrowDown className="ml-1 inline h-3 w-3"/>;
    };

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
            setConfirmManualOverlap(false);
            setGenerationSelection(current => Object.fromEntries(response.entries.map(invoice => [
                invoice.id,
                current[invoice.id] ?? {
                    invoice_id: invoice.id,
                    entry_date: invoice.suggested_entry_date,
                },
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
            const entriesByYear = selections.reduce<Record<number, KpoGenerationSelection[]>>((groups, selection) => {
                const year = Number(selection.entry_date.slice(0, 4));
                groups[year] = groups[year] ?? [];
                groups[year].push(selection);
                return groups;
            }, {});

            const results = await Promise.all(
                Object.entries(entriesByYear).map(([year, entries]) =>
                    generateKpoEntries(Number(year), entries, confirmManualOverlap, includeHistorical)
                )
            );
            const result = results.reduce((combined, current) => ({
                created: [...combined.created, ...current.created],
                skipped: [...combined.skipped, ...current.skipped],
                failed: [...combined.failed, ...current.failed],
            }), {created: [], skipped: [], failed: []} as KpoGenerationResult);

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

    const openRegenerationPreview = async () => {
        if (!activeCompanyId || !filters.year) return;

        const invoiceIds = [...new Set(entries.map(entry => entry.sourceInvoiceId).filter((id): id is number => Boolean(id)))];
        if (!invoiceIds.length) {
            toast.error('Za proveru regeneracije nema faktura povezanih sa prikazanim KPO zapisima.');
            return;
        }

        setRegenerationLoading(true);
        setRegenerationStale(false);
        setLastRegenerationResult(null);
        try {
            const response = await previewKpoRegeneration(filters.year, invoiceIds);
            setRegenerationPreview(response);
            setConfirmRegeneration(false);
            setConfirmManualOverwrite(false);
            setRegenerationOpen(true);
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće proveriti promene KPO zapisa.'));
        } finally {
            setRegenerationLoading(false);
        }
    };

    const submitRegeneration = async () => {
        if (!regenerationPreview || !confirmRegeneration || regenerationStale) return;

        // The fingerprint covers the complete preview snapshot, not only the
        // actionable subset. Sending all preview IDs keeps the server-side
        // snapshot identical and lets it safely retain unchanged invoices.
        const invoiceIds = regenerationPreview.invoices.map(invoice => invoice.invoiceId);
        setRegenerationLoading(true);
        try {
            const result = await regenerateKpoEntries(
                regenerationPreview.year,
                invoiceIds,
                regenerationPreview.previewFingerprint,
                confirmManualOverwrite,
                regenerationPreview.includeHistorical,
            );
            setLastRegenerationResult(result);
            toast.success(`Regeneracija završena: ${result.created.length} kreirano, ${result.updated.length} ažurirano, ${result.unchanged.length} bez promene.`);
            if (result.protected.length || result.blocked.length || result.obsolete.length || result.roundingResiduals.length) {
                toast.warning('Neke stavke su zaštićene, blokirane ili zahtevaju dodatnu odluku.');
            }
            setRegenerationOpen(false);
            setRegenerationPreview(null);
            await loadEntries();
        } catch (requestError: any) {
            const message = getErrorMessage(requestError, 'Nije moguće izvršiti regeneraciju KPO zapisa.');
            if (requestError?.response?.status === 422 && message.toLowerCase().includes('zastareo')) {
                setRegenerationStale(true);
                setConfirmRegeneration(false);
                toast.error('Preview je zastareo. Proverite promene ponovo pre regeneracije.');
            } else {
                toast.error(message);
            }
        } finally {
            setRegenerationLoading(false);
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
                    incomeCategory: entry.incomeCategory === 'product' || entry.incomeCategory === 'service' ? entry.incomeCategory : 'unclassified',
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
        setHistory(null);
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
                income_category: form.incomeCategory,
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
                    incomeCategory: refreshed.incomeCategory === 'product' || refreshed.incomeCategory === 'service' ? refreshed.incomeCategory : 'unclassified',
                adjustmentRsd: refreshed.adjustmentRsd.toFixed(2),
                description: refreshed.description ?? '',
            });
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće sačuvati KPO zapis.'));
        } finally {
            setSaving(false);
        }
    };

    const deleteSelectedEntry = async () => {
        if (!selectedEntry || selectedEntry.sourceType !== 'manual') return;
        if (! window.confirm('Obrisati ručni KPO zapis? Zapis će ostati sačuvan u istoriji.')) return;

        setSaving(true);
        try {
            await deleteKpoEntry(selectedEntry.id);
            toast.success('Ručni KPO zapis je obrisan.');
            closeDetails();
            await loadEntries();
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće obrisati KPO zapis.'));
        } finally {
            setSaving(false);
        }
    };

    const removeSelectedGeneratedEntry = async () => {
        if (!selectedEntry || selectedEntry.sourceType !== 'invoice') return;
        const reason = removalReason.trim();
        if (reason.length < 3) {
            toast.error('Unesite razlog kontrolisanog uklanjanja.');
            return;
        }
        if (!window.confirm('Ukloniti invoice KPO zapis? Zapis će biti sačuvan u istoriji.')) return;

        setSaving(true);
        try {
            await removeGeneratedKpoEntry(selectedEntry.id, reason);
            toast.success('Invoice KPO zapis je kontrolisano uklonjen.');
            setRemovalReason('');
            closeDetails();
            await loadEntries();
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće ukloniti invoice KPO zapis.'));
        } finally {
            setSaving(false);
        }
    };

    const openHistory = async () => {
        if (!selectedEntry) return;
        setHistoryLoading(true);
        try {
            const response = await getKpoHistory(selectedEntry.id);
            setHistory(response.revisions);
        } catch (requestError: any) {
            toast.error(getErrorMessage(requestError, 'Nije moguće učitati istoriju KPO zapisa.'));
        } finally {
            setHistoryLoading(false);
        }
    };

    const years = Array.from({length: 7}, (_, index) => currentYear - 3 + index);
    const calculatedFinal = selectedEntry
        ? selectedEntry.amountRsd + (Number(form.adjustmentRsd.replace(',', '.')) || 0)
        : 0;
    const actionableRegenerationInvoices = regenerationPreview?.invoices.filter(invoice => invoice.actionable) ?? [];
    const unchangedRegenerationCount = regenerationPreview
        ? regenerationPreview.invoices.length - actionableRegenerationInvoices.length
        : 0;

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
                        onClick={() => void openRegenerationPreview()}
                        disabled={regenerationLoading || !activeCompanyId || !filters.year}
                        className="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 shadow-sm hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <RefreshCw className={`h-4 w-4 ${regenerationLoading ? 'animate-spin' : ''}`}/>
                        {regenerationLoading ? 'Provera...' : 'Proveri promene'}
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
                            <th scope="col" aria-sort={sort?.column === 'entryNumber' ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'} className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button type="button" onClick={() => toggleSort('entryNumber')}>R.br.{sortIcon('entryNumber')}</button>
                            </th>
                            <th scope="col" aria-sort={sort?.column === 'date' ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'} className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button type="button" onClick={() => toggleSort('date')}>Datum{sortIcon('date')}</button>
                            </th>
                            <th scope="col" aria-sort={sort?.column === 'description' ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'} className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button type="button" onClick={() => toggleSort('description')}>Opis{sortIcon('description')}</button>
                            </th>
                            <th scope="col" aria-sort={sort?.column === 'product' ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'} className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button type="button" onClick={() => toggleSort('product')}>Proizvodi{sortIcon('product')}</button>
                            </th>
                            <th scope="col" aria-sort={sort?.column === 'service' ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'} className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button type="button" onClick={() => toggleSort('service')}>Usluge{sortIcon('service')}</button>
                            </th>
                            <th scope="col" aria-sort={sort?.column === 'total' ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'} className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button type="button" onClick={() => toggleSort('total')}>Ukupno{sortIcon('total')}</button>
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Akcije</th>
                        </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                        {loading ? (
                            <tr><td colSpan={7} className="px-4 py-12 text-center text-sm text-gray-500">Učitavanje KPO evidencije...</td></tr>
                        ) : entries.length === 0 ? (
                            <tr><td colSpan={7} className="px-4 py-12 text-center text-sm text-gray-500">Nema KPO zapisa za izabrani period.</td></tr>
                        ) : sortedEntries.map(entry => (
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
                        <div className="flex items-center gap-3">
                            {selectedEntry && <button type="button" onClick={() => void openHistory()} className="text-sm font-medium text-indigo-600 hover:text-indigo-900">Istorija</button>}
                            {selectedEntry?.sourceType === 'manual' && <button type="button" onClick={() => void deleteSelectedEntry()} disabled={saving} className="text-sm font-medium text-red-600 hover:text-red-900 disabled:opacity-60">Obriši</button>}
                            {selectedEntry?.sourceType === 'invoice' && <button type="button" onClick={() => void removeSelectedGeneratedEntry()} disabled={saving || removalReason.trim().length < 3} className="text-sm font-medium text-red-600 hover:text-red-900 disabled:opacity-60">Kontrolisano ukloni</button>}
                            <button type="button" onClick={closeDetails} className="text-gray-400 hover:text-gray-600" title="Zatvori"><X className="h-5 w-5"/></button>
                        </div>
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
                                {selectedEntry.sourceType === 'invoice' && <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">Uklanjanje je dozvoljeno samo kada faktura više ne zahteva ovu kategoriju. Unesite razlog, pa potvrdite akciju. Ako faktura i dalje ima kvalifikovani prihod, prvo izmenite fakturu.</div>}
                                <p className="text-xs text-gray-500">Ukupan prihod i zbirni iznosi dolaze sa servera. Klasifikacija proizvoda/usluga prikazuje iznos samo za prepoznatu vrednost tipa prihoda.</p>
                            </div>
                            {selectedEntry.sourceType === 'invoice' && <label className="block text-sm font-medium text-gray-700">Razlog kontrolisanog uklanjanja
                                <textarea rows={3} value={removalReason} onChange={event => setRemovalReason(event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm" placeholder="Npr. faktura je isključena iz KPO evidencije" />
                            </label>}
                            <form onSubmit={saveEntry} className="space-y-4 rounded-lg bg-gray-50 p-4">
                                <h3 className="text-sm font-semibold text-gray-900">Dozvoljene izmene</h3>
                                <label className="block text-sm font-medium text-gray-700">Datum evidencije
                                    <input required type="date" value={form.entryDate} onChange={event => setForm(current => ({...current, entryDate: event.target.value}))} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                </label>
                                <label className="block text-sm font-medium text-gray-700">Kategorija prihoda
                                    <select value={form.incomeCategory} onChange={event => setForm(current => ({...current, incomeCategory: event.target.value as 'product' | 'service' | 'unclassified'}))} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="product">Proizvod</option>
                                        <option value="service">Usluga</option>
                                        <option value="unclassified">Nerazvrstano</option>
                                    </select>
                                </label>
                                <label className="block text-sm font-medium text-gray-700">Korekcija (+/−) RSD
                                    <input inputMode="decimal" value={form.adjustmentRsd} onChange={event => setForm(current => ({...current, adjustmentRsd: event.target.value}))} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <span className="mt-1 block text-xs font-normal text-gray-500">Pozitivna vrednost povećava, a negativna smanjuje ukupan iznos.</span>
                                </label>
                                <div className="rounded-md border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm text-indigo-900">Konačan iznos: <strong>{formatCurrency(calculatedFinal, 'RSD')}</strong></div>
                                <label className="block text-sm font-medium text-gray-700">Opis
                                    <textarea rows={3} value={form.description} onChange={event => setForm(current => ({...current, description: event.target.value}))} className="mt-1 block w-full rounded-md border-gray-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                </label>
                                <button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                                    <Save className="h-4 w-4"/> {saving ? 'Čuvanje...' : 'Sačuvaj izmene'}
                                </button>
                            </form>
                        </div>
                    )}
                    {(history || historyLoading) && (
                        <div className="mt-6 rounded-lg border border-gray-200 bg-white p-4">
                            <div className="mb-3 flex items-center justify-between"><h3 className="font-semibold text-gray-900">Istorija izmena</h3><button type="button" onClick={() => setHistory(null)} className="text-sm text-gray-500">Zatvori</button></div>
                            {historyLoading ? <p className="text-sm text-gray-500">Učitavanje istorije...</p> : history?.length === 0 ? <p className="text-sm text-gray-500">Nema zabeleženih događaja.</p> : <div className="space-y-3">{history?.map((revision, index) => <div key={revision.id ?? index} className="rounded-md bg-gray-50 p-3 text-sm"><div className="flex flex-wrap justify-between gap-2 font-medium text-gray-800"><span>{revision.action_type}</span><span>{revision.created_at ? formatDate(revision.created_at) : '—'} · {revision.acting_user?.display_name || revision.acting_user?.email || 'Sistem'}</span></div><p className="mt-2 text-xs text-gray-500">Promenjena polja: {revisionChangedFields(revision).join(', ') || '—'}</p><div className="mt-2 grid gap-2 text-xs text-gray-600 sm:grid-cols-2"><pre className="overflow-auto rounded bg-white p-2">Pre: {JSON.stringify(revision.previous_values ?? {}, null, 2)}</pre><pre className="overflow-auto rounded bg-white p-2">Posle: {JSON.stringify(revision.new_values ?? {}, null, 2)}</pre></div></div>)}</div>}
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
                        Datum KPO evidencije je unapred predložen prema datumu izdavanja fakture. Proverite ili izmenite datum za svaku fakturu; server će zapis numerisati po godini konačnog datuma.
                    </div>
                    <label className="mb-4 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={includeHistorical} onChange={event => {
                            const historical = event.target.checked;
                            setIncludeHistorical(historical);
                            void openGenerationPreview(historical);
                        }}/>
                        Prikaži i fakture iz drugih godina (godina izabrana u KPO-u i dalje ne određuje datum)
                    </label>
                    {generationPreview.manualOverlapWarning?.requiresConfirmation && includeHistorical && (
                        <label className="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            <input type="checkbox" checked={confirmManualOverlap} onChange={event => setConfirmManualOverlap(event.target.checked)}/>
                            <span>Postoji {generationPreview.manualOverlapWarning.count} ručni ili odvojeni KPO zapis za {generationPreview.manualOverlapWarning.year}. Potvrđujem da želim istorijsko generisanje bez automatskog povezivanja ili menjanja tih zapisa.</span>
                        </label>
                    )}
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
                                                    {...(!includeHistorical ? {
                                                        min: `${generationPreview.year}-01-01`,
                                                        max: `${generationPreview.year}-12-31`,
                                                    } : {})}
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

            {regenerationOpen && regenerationPreview && (
                <div className="rounded-xl border border-amber-200 bg-white p-6 shadow-sm">
                    <div className="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900">Provera promena KPO zapisa</h2>
                            <p className="mt-1 text-sm text-gray-500">Pregled je samo za čitanje. Nema automatskog prepisivanja ručnih izmena.</p>
                        </div>
                        <button type="button" onClick={() => setRegenerationOpen(false)} className="text-gray-400 hover:text-gray-600" title="Zatvori"><X className="h-5 w-5"/></button>
                    </div>

                    {regenerationStale && (
                        <div className="mb-4 flex items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <span>Podaci su se promenili od pregleda. Učitajte novi preview pre regeneracije.</span>
                            <button type="button" onClick={() => void openRegenerationPreview()} className="rounded-md bg-red-100 px-3 py-1.5 font-medium hover:bg-red-200">Novi preview</button>
                        </div>
                    )}

                    <div className="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-6">
                        <CountCard label="Fakture" value={regenerationPreview.invoices.length}/>
                        <CountCard label="Promene" value={actionableRegenerationInvoices.length}/>
                        <CountCard label="Bez promena" value={unchangedRegenerationCount}/>
                        <CountCard label="Blokirane" value={regenerationPreview.invoices.filter(invoice => invoice.status === 'blocked').length}/>
                        <CountCard label="Delimične" value={regenerationPreview.invoices.filter(invoice => invoice.status === 'partial').length}/>
                        <CountCard label="Sa upozorenjem" value={regenerationPreview.invoices.filter(invoice => invoice.rounding.hasUnallocatedRoundingResidual || Number(invoice.unresolved.amountRsd) !== 0).length}/>
                    </div>

                    {actionableRegenerationInvoices.length === 0 ? (
                        <p className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            KPO evidencija je usklađena sa fakturama. Nema promena za primenu.
                        </p>
                    ) : <div className="space-y-4">
                        {actionableRegenerationInvoices.map(invoice => (
                            <div key={invoice.invoiceId} className="rounded-lg border border-gray-200 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h3 className="font-semibold text-gray-900">Faktura #{invoice.invoiceNumber}</h3>
                                        <p className="text-xs text-gray-500">Datum izdavanja: {formatDate(invoice.issueDate)}</p>
                                    </div>
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${invoice.status === 'ready' ? 'bg-green-100 text-green-800' : invoice.status === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800'}`}>
                                        {regenerationStatusLabel(invoice.status)}
                                    </span>
                                </div>

                                {invoice.blockingReasons.length > 0 && <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">Blokade: {invoice.blockingReasons.join(', ')}</p>}
                                {invoice.legacyClassificationConflicts.length > 0 && <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">Legacy zapis bez klasifikacije zahteva ručnu odluku.</p>}
                                {invoice.deletedCategories.length > 0 && <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">Obrisane kategorije ({invoice.deletedCategories.join(', ')}) ostaju u istoriji i neće biti automatski vraćene.</p>}
                                {(Number(invoice.unresolved.amountRsd) !== 0 || invoice.rounding.hasUnallocatedRoundingResidual) && (
                                    <p className="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                        Nerazrešeno: {formatCurrency(Number(invoice.unresolved.amountRsd), 'RSD')}; rezidual zaokruživanja: {formatCurrency(Number(invoice.rounding.categoryResidualRsd), 'RSD')}. Rezidual se ne raspoređuje automatski.
                                    </p>
                                )}

                                <div className="mt-3 overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead className="border-b border-gray-200 text-left text-xs uppercase text-gray-500"><tr><th className="px-2 py-2">Kategorija</th><th className="px-2 py-2">Postojeći</th><th className="px-2 py-2">Predloženi</th><th className="px-2 py-2">Status</th></tr></thead>
                                        <tbody className="divide-y divide-gray-100">
                                        {invoice.proposedEntries.map(proposal => (
                                            <tr key={`${invoice.invoiceId}-${proposal.incomeCategory}`}>
                                                <td className="px-2 py-2 font-medium">{proposal.incomeCategory}</td>
                                                <td className="px-2 py-2">{proposal.existingEntryId ? formatCurrency(Number(invoice.amountDifferences.find(item => item.existingEntryId === proposal.existingEntryId)?.existingTotalRsd ?? 0), 'RSD') : 'Nema zapisa'}</td>
                                                <td className="px-2 py-2">{formatCurrency(Number(proposal.totalRsd), 'RSD')}</td>
                                                <td className="px-2 py-2">{proposal.manuallyEdited ? 'Zaštićeno — ručna izmena' : regenerationActionLabel(proposal.action)}</td>
                                            </tr>
                                        ))}
                                        {invoice.proposedEntries.length === 0 && <tr><td colSpan={4} className="px-2 py-3 text-gray-500">Nema predloženih kategorija.</td></tr>}
                                        </tbody>
                                    </table>
                                </div>

                                {invoice.existingEntries.some(entry => entry.manuallyEdited) && <p className="mt-3 text-sm text-amber-800">Postoje ručno izmenjeni zapisi. Oni ostaju zaštićeni dok posebno ne dozvolite prepisivanje.</p>}
                                {invoice.obsoleteCategories.length > 0 && <p className="mt-2 text-sm text-gray-700">Zastarele kategorije: {invoice.obsoleteCategories.map(item => item.incomeCategory).join(', ')} — ostaju nepromenjene.</p>}
                            </div>
                        ))}
                    </div>}

                    {actionableRegenerationInvoices.length > 0 && <div className="mt-5 space-y-3 rounded-lg bg-gray-50 p-4">
                        <label className="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" checked={confirmManualOverwrite} onChange={event => setConfirmManualOverwrite(event.target.checked)}/>
                            <span>Dozvoli prepisivanje ručno izmenjenih KPO zapisa (nije uključeno po podrazumevanom).</span>
                        </label>
                        <label className="flex items-start gap-2 text-sm font-medium text-gray-900">
                            <input type="checkbox" checked={confirmRegeneration} onChange={event => setConfirmRegeneration(event.target.checked)} disabled={regenerationStale}/>
                            <span>Pregledao/la sam promene i potvrđujem regeneraciju. Zaštićeni i blokirani zapisi neće biti menjani.</span>
                        </label>
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setRegenerationOpen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Otkaži</button>
                            <button type="button" onClick={() => void submitRegeneration()} disabled={regenerationLoading || !confirmRegeneration || regenerationStale} className="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60">
                                {regenerationLoading ? 'Regeneracija...' : 'Potvrdi regeneraciju'}
                            </button>
                        </div>
                    </div>}
                </div>
            )}

            {lastRegenerationResult && !regenerationOpen && (
                <div className="rounded-xl border border-green-200 bg-green-50 p-5 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="font-semibold text-green-900">Rezultat regeneracije</h2>
                            <p className="mt-1 text-sm text-green-800">
                                Kreirano: {lastRegenerationResult.created.length} · Ažurirano: {lastRegenerationResult.updated.length} · Bez promene: {lastRegenerationResult.unchanged.length} · Zaštićeno: {lastRegenerationResult.protected.length} · Blokirano: {lastRegenerationResult.blocked.length}
                            </p>
                            {(lastRegenerationResult.obsolete.length > 0 || lastRegenerationResult.roundingResiduals.length > 0) && (
                                <p className="mt-2 text-sm text-amber-800">Zastarele stavke i/ili reziduali zahtevaju zasebnu odluku i nisu automatski menjani.</p>
                            )}
                        </div>
                        <button type="button" onClick={() => setLastRegenerationResult(null)} className="text-sm text-green-700 hover:text-green-900">Skloni</button>
                    </div>
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

function CountCard({label, value}: {label: string; value: number}) {
    return <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><p className="text-sm font-medium text-gray-500">{label}</p><p className="mt-2 text-2xl font-bold text-gray-900">{value.toLocaleString('sr-RS')}</p></div>;
}

function DetailRow({label, value, emphasized = false}: {label: string; value: React.ReactNode; emphasized?: boolean}) {
    return <div className="flex items-start justify-between gap-4 border-b border-gray-100 pb-3">
        <span className="text-gray-500">{label}</span>
        <span className={`text-right ${emphasized ? 'font-bold text-gray-900' : 'text-gray-700'}`}>{value}</span>
    </div>;
}
