import React, {useMemo, useRef, useState} from 'react';
import {Download, FileSpreadsheet, RefreshCw, Trash2, Upload, X} from 'lucide-react';
import {toast} from 'sonner';
import {CsvImportPreview, useApp} from '../context/AppContext';

const labels: Record<string, string> = {
    name: 'Naziv', email: 'Email', tax_id: 'PIB / Tax ID', registration_number: 'Matični / registracioni broj', country_code: 'ISO kod države', postal_code: 'Poštanski broj', contact_person: 'Kontakt osoba', default_currency: 'Podrazumevana valuta', preferred_language: 'Jezik fakture', address: 'Adresa', city: 'Grad', country: 'Država', phone: 'Telefon', client_type: 'Tip klijenta',
    invoice_number: 'Broj fakture', issue_date: 'Datum izdavanja', due_date: 'Rok plaćanja', currency: 'Valuta', status: 'Status', client_id: 'ID klijenta', client_name: 'Naziv klijenta', payment_method: 'Način plaćanja', note: 'Napomena', vat_enabled_at_issue: 'PDV pri izdavanju', paid_at: 'Datum uplate', payment_note: 'Napomena o uplati', fx_rate_to_rsd: 'Kurs prema RSD', fx_provider: 'Izvor kursa', fx_date: 'Datum kursa', item_name: 'Naziv stavke', item_description: 'Opis stavke', quantity: 'Količina', price: 'Cena', tax_percent: 'Porez %', income_category: 'Kategorija prihoda',
};

const requiredFields: Record<'clients' | 'invoices', string[]> = {
    clients: ['name'],
    invoices: ['invoice_number', 'issue_date', 'due_date', 'currency', 'client_id', 'client_name', 'item_name', 'quantity', 'price'],
};

const fileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export default function ImportExport() {
    const {previewCsvImport, importCsv, exportCsv} = useApp();
    const inputRef = useRef<HTMLInputElement>(null);
    const requestIdRef = useRef(0);
    const [type, setType] = useState<'clients' | 'invoices'>('clients');
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<CsvImportPreview | null>(null);
    const [mapping, setMapping] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState<'preview' | 'import' | 'export' | null>(null);
    const [dragging, setDragging] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');

    const available = useMemo(() => preview?.headers ?? [], [preview]);
    const fields = preview?.fields ?? [];
    const isBusy = busy !== null;

    const showError = (error: any, fallback: string) => {
        const message = error?.response?.data?.message ?? fallback;
        setErrorMessage(message);
        toast.error(message);
    };

    const requestPreview = async (nextType: 'clients' | 'invoices', nextFile: File, nextMapping: Record<string, string> = {}) => {
        const requestId = ++requestIdRef.current;
        setBusy('preview');
        setErrorMessage('');
        try {
            const result = await previewCsvImport(nextType, nextFile, nextMapping);
            if (requestId !== requestIdRef.current) return;
            setPreview(result);
            setMapping(result.mapping);
        } catch (error: any) {
            if (requestId !== requestIdRef.current) return;
            setPreview(null);
            showError(error, 'CSV fajl nije moguće pročitati.');
        } finally {
            if (requestId === requestIdRef.current) setBusy(null);
        }
    };

    const selectFile = (selected: File | null) => {
        if (!selected) return;
        setFile(selected);
        setPreview(null);
        setMapping({});
        void requestPreview(type, selected);
    };

    const changeType = (nextType: 'clients' | 'invoices') => {
        setType(nextType);
        setPreview(null);
        setMapping({});
        if (file) void requestPreview(nextType, file);
    };

    const clearFile = () => {
        ++requestIdRef.current;
        setFile(null);
        setPreview(null);
        setMapping({});
        setErrorMessage('');
        if (inputRef.current) inputRef.current.value = '';
    };

    const changeMapping = (field: string, value: string) => {
        if (value && Object.entries(mapping).some(([otherField, selected]) => otherField !== field && selected === value)) {
            setErrorMessage('Jedna CSV kolona može biti mapirana samo na jedno polje.');
            return;
        }
        setMapping(current => ({...current, [field]: value}));
        setErrorMessage('');
    };

    const confirmImport = async () => {
        if (!file || !preview?.can_import || isBusy) return;
        if (!window.confirm('Da li želite da importujete prikazane podatke?')) return;
        setBusy('import');
        setErrorMessage('');
        try {
            const result = await importCsv(type, file, mapping);
            toast.success(`Import završen: ${result.created_invoices ?? result.created_clients ?? 0} zapisa.`);
            clearFile();
        } catch (error: any) {
            showError(error, 'Import nije uspeo.');
        } finally {
            setBusy(null);
        }
    };

    const downloadExport = async () => {
        if (isBusy) return;
        setBusy('export');
        setErrorMessage('');
        try {
            await exportCsv(type);
            toast.success('CSV je spreman za preuzimanje.');
        } catch (error: any) {
            showError(error, 'Export nije uspeo.');
        } finally {
            setBusy(null);
        }
    };

    const onDrop = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        selectFile(event.dataTransfer.files?.[0] ?? null);
    };

    return <div className="space-y-6">
        <div>
            <h1 className="text-2xl font-bold text-gray-900">Import / Export</h1>
            <p className="mt-2 text-sm text-gray-600">CSV podaci aktivne firme. Import ne kreira KPO, PDF ili email poruke.</p>
        </div>
        <section className="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm" aria-busy={isBusy}>
            <div className="flex flex-wrap items-end justify-between gap-4">
                <label className="text-sm font-medium text-gray-700">Tip podataka
                    <select className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={type} onChange={event => changeType(event.target.value as 'clients' | 'invoices')} disabled={isBusy && busy !== 'preview'}>
                        <option value="clients">Klijenti</option><option value="invoices">Fakture sa stavkama</option>
                    </select>
                </label>
                <button type="button" disabled={isBusy} onClick={downloadExport} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                    {busy === 'export' ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}{busy === 'export' ? 'Preuzimanje...' : 'Preuzmi CSV'}
                </button>
            </div>

            <div className={`rounded-lg border-2 border-dashed p-8 text-center transition-colors ${dragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-gray-50 hover:border-indigo-400'} ${isBusy && busy !== 'preview' ? 'pointer-events-none opacity-60' : ''}`}
                role="button" tabIndex={0} aria-label="Izaberite CSV fajl prevlačenjem ili pritiskom na Enter"
                onClick={() => inputRef.current?.click()}
                onKeyDown={event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); inputRef.current?.click(); } }}
                onDragEnter={event => { event.preventDefault(); setDragging(true); }} onDragOver={event => event.preventDefault()}
                onDragLeave={event => { if (event.currentTarget === event.target) setDragging(false); }} onDrop={onDrop}>
                <input ref={inputRef} className="sr-only" type="file" accept=".csv,.txt,text/csv" onChange={event => selectFile(event.target.files?.[0] ?? null)} />
                <FileSpreadsheet className="mx-auto h-10 w-10 text-indigo-500" aria-hidden="true" />
                <p className="mt-3 text-sm font-medium text-gray-900">Prevucite CSV fajl ovde</p><p className="mt-1 text-sm text-gray-500">ili pritisnite Enter / Space za izbor fajla</p><p className="mt-2 text-xs text-gray-400">Maksimalna veličina: 5 MB</p>
            </div>

            {file && <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm">
                <div className="flex min-w-0 items-center gap-3"><FileSpreadsheet className="h-5 w-5 shrink-0 text-indigo-600" /><span className="truncate font-medium text-indigo-900">{file.name}</span><span className="shrink-0 text-indigo-700">{fileSize(file.size)}</span></div>
                <div className="flex items-center gap-2"><button type="button" className="rounded-md px-3 py-1.5 text-indigo-700 hover:bg-indigo-100" onClick={() => inputRef.current?.click()} disabled={isBusy}>Zameni fajl</button><button type="button" className="inline-flex items-center rounded-md px-2 py-1.5 text-red-700 hover:bg-red-100" onClick={clearFile} disabled={isBusy} aria-label="Ukloni CSV fajl"><Trash2 className="h-4 w-4" /></button></div>
            </div>}

            {busy === 'preview' && <p role="status" className="flex items-center gap-2 text-sm text-indigo-600"><RefreshCw className="h-4 w-4 animate-spin" /> Provera CSV fajla...</p>}
            {busy === 'import' && <p role="status" className="flex items-center gap-2 text-sm text-indigo-600"><RefreshCw className="h-4 w-4 animate-spin" /> Import u toku...</p>}
            {errorMessage && <div role="alert" className="flex items-start gap-2 rounded-md bg-red-50 p-4 text-sm text-red-700"><X className="mt-0.5 h-4 w-4 shrink-0" />{errorMessage}</div>}

            {preview && <>
                <div className="grid gap-3 rounded-md bg-gray-50 p-4 text-sm sm:grid-cols-3"><span>Redova: {preview.summary.rows}</span><span>Faktura: {preview.summary.invoices}</span><span>Stavki: {preview.summary.items}</span><span>Klijenata: {preview.summary.clients}</span><span>Duplikata: {preview.summary.duplicates}</span></div>
                <div><div className="mb-3"><h2 className="text-base font-semibold text-gray-900">Mapiranje kolona</h2><p className="text-sm text-gray-500">Izaberite CSV kolonu za svako polje ili odaberite „Preskoči“.</p></div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{fields.map(field => { const isRequired = requiredFields[type].includes(field); const conditionalClient = type === 'invoices' && (field === 'client_id' || field === 'client_name'); return <label key={field} className="rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-700 shadow-sm"><span className="flex items-center justify-between gap-2"><span className="font-medium">{labels[field] ?? field}</span>{isRequired && !conditionalClient && <span className="text-xs font-normal text-red-600">Obavezno</span>}{conditionalClient && <span className="text-xs font-normal text-gray-500">ID ili naziv</span>}</span><select className="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={mapping[field] ?? ''} onChange={event => changeMapping(field, event.target.value)}><option value="">Preskoči</option>{available.map(header => <option key={header} value={header}>{header}</option>)}</select></label>; })}</div>
                </div>
                <div className="flex flex-wrap items-center gap-3"><button type="button" disabled={isBusy} onClick={() => file && requestPreview(type, file, mapping)} className="inline-flex items-center rounded-md border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"><RefreshCw className="mr-2 h-4 w-4" />Proveri podatke</button><span className="text-xs text-gray-500">Preview se ponovo proverava nakon promene mapiranja.</span></div>
                {preview.errors.length > 0 && <div className="rounded-md bg-red-50 p-4 text-sm text-red-700"><strong>Greške pre importa</strong><ul className="mt-2 list-disc space-y-1 pl-5">{preview.errors.slice(0, 30).map((message, index) => <li key={`${message}-${index}`}>{message}</li>)}</ul>{preview.errors.length > 30 && <p className="mt-2">Prikazano je prvih 30 grešaka.</p>}</div>}
                {preview.preview.length > 0 && <div className="overflow-x-auto rounded-md border border-gray-200"><table className="min-w-full text-left text-xs"><caption className="sr-only">Preview prvih deset redova CSV fajla</caption><thead className="bg-gray-50"><tr>{preview.headers.map(header => <th className="whitespace-nowrap border-b p-2 font-semibold" key={header}>{header}</th>)}</tr></thead><tbody>{preview.preview.map((row, index) => <tr key={index} className="odd:bg-white even:bg-gray-50">{preview.headers.map((_, column) => <td className="max-w-xs border-b p-2 align-top" key={column}>{row[column] ?? ''}</td>)}</tr>)}</tbody></table></div>}
                <button type="button" disabled={isBusy || !preview.can_import} onClick={confirmImport} className="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"><Upload className="mr-2 h-4 w-4" />{busy === 'import' ? 'Import...' : 'Potvrdi import'}</button>
            </>}
            {!file && <div className="flex items-center gap-2 text-sm text-gray-500"><FileSpreadsheet className="h-4 w-4" />Izaberite CSV fajl za preview i ručno mapiranje kolona.</div>}
        </section>
    </div>;
}
