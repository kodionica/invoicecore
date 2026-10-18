import React, {useMemo, useState} from 'react';
import {Download, Upload, FileSpreadsheet} from 'lucide-react';
import {toast} from 'sonner';
import {useApp, CsvImportPreview} from '../context/AppContext';

const labels: Record<string, string> = {
    name: 'Naziv', email: 'Email', tax_id: 'PIB', registration_number: 'Matični broj', address: 'Adresa', city: 'Grad', country: 'Država', phone: 'Telefon', client_type: 'Tip klijenta',
    invoice_number: 'Broj fakture', issue_date: 'Datum izdavanja', due_date: 'Rok plaćanja', currency: 'Valuta', status: 'Status', client_id: 'ID klijenta', client_name: 'Naziv klijenta', payment_method: 'Način plaćanja', note: 'Napomena', vat_enabled_at_issue: 'PDV pri izdavanju', paid_at: 'Datum uplate', payment_note: 'Napomena o uplati', fx_rate_to_rsd: 'Kurs prema RSD', fx_provider: 'Izvor kursa', fx_date: 'Datum kursa', item_name: 'Naziv stavke', item_description: 'Opis stavke', quantity: 'Količina', price: 'Cena', tax_percent: 'Porez %', income_category: 'Kategorija prihoda',
};

export default function ImportExport() {
    const {previewCsvImport, importCsv, exportCsv} = useApp();
    const [type, setType] = useState<'clients' | 'invoices'>('clients');
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<CsvImportPreview | null>(null);
    const [mapping, setMapping] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const available = useMemo(() => preview?.headers ?? [], [preview]);
    const chooseFile = async (selected: File | null) => {
        setFile(selected); setPreview(null); setMapping({});
        if (!selected) return;
        setBusy(true);
        try {
            const result = await previewCsvImport(type, selected);
            setPreview(result); setMapping(result.mapping);
        } catch (error: any) { toast.error(error?.response?.data?.message ?? 'CSV fajl nije moguće pročitati.'); }
        finally { setBusy(false); }
    };
    const refreshPreview = async () => {
        if (!file) return;
        setBusy(true);
        try { setPreview(await previewCsvImport(type, file, mapping)); }
        catch (error: any) { toast.error(error?.response?.data?.message ?? 'Validacija importa nije uspela.'); }
        finally { setBusy(false); }
    };
    const confirmImport = async () => {
        if (!file || !preview?.can_import || busy) return;
        if (!window.confirm('Da li želite da importujete prikazane podatke?')) return;
        setBusy(true);
        try { const result = await importCsv(type, file, mapping); toast.success(`Import završen: ${result.created_invoices ?? result.created_clients ?? 0} zapisa.`); setPreview(null); setFile(null); }
        catch (error: any) { toast.error(error?.response?.data?.message ?? 'Import nije uspeo.'); }
        finally { setBusy(false); }
    };
    const fields = preview?.fields ?? [];

    return <div className="space-y-6">
        <div><h1 className="text-2xl font-bold text-gray-900">Import / Export</h1><p className="mt-2 text-sm text-gray-600">CSV podaci aktivne firme. Import ne kreira KPO, PDF ili email poruke.</p></div>
        <section className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <div className="flex flex-wrap items-end gap-4">
                <label className="text-sm font-medium text-gray-700">Tip podataka<select className="mt-1 block rounded-md border-gray-300" value={type} onChange={e => {setType(e.target.value as any); setPreview(null); setFile(null);}}><option value="clients">Klijenti</option><option value="invoices">Fakture sa stavkama</option></select></label>
                <label className="text-sm font-medium text-gray-700">CSV fajl<input className="mt-1 block text-sm" type="file" accept=".csv,.txt,text/csv" onChange={e => chooseFile(e.target.files?.[0] ?? null)} /></label>
                <button type="button" disabled={busy} onClick={() => exportCsv(type)} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"><Download className="mr-2 h-4 w-4"/>Preuzmi CSV</button>
            </div>
            {busy && <p role="status" className="text-sm text-indigo-600">Obrada CSV fajla...</p>}
            {preview && <>
                <div className="grid gap-3 md:grid-cols-3 rounded-md bg-gray-50 p-4 text-sm"><span>Redova: {preview.summary.rows}</span><span>Faktura: {preview.summary.invoices}</span><span>Stavki: {preview.summary.items}</span><span>Klijenata: {preview.summary.clients}</span><span>Duplikata: {preview.summary.duplicates}</span></div>
                <div className="grid gap-3 md:grid-cols-2">{fields.map(field => <label key={field} className="text-sm text-gray-700">{labels[field] ?? field}<select className="mt-1 block w-full rounded-md border-gray-300" value={mapping[field] ?? ''} onChange={e => setMapping(current => ({...current, [field]: e.target.value}))}><option value="">Preskoči</option>{available.map(header => <option key={header} value={header}>{header}</option>)}</select></label>)}</div>
                <button type="button" disabled={busy} onClick={refreshPreview} className="rounded-md border border-indigo-300 px-4 py-2 text-sm text-indigo-700">Proveri podatke</button>
                {preview.errors.length > 0 && <div className="rounded-md bg-red-50 p-4 text-sm text-red-700"><strong>Greške</strong><ul className="mt-2 list-disc pl-5">{preview.errors.slice(0, 30).map(error => <li key={error}>{error}</li>)}</ul></div>}
                {preview.preview.length > 0 && <div className="overflow-x-auto"><table className="min-w-full text-left text-xs"><thead><tr>{preview.headers.map(h => <th className="border-b p-2" key={h}>{h}</th>)}</tr></thead><tbody>{preview.preview.map((row, i) => <tr key={i}>{preview.headers.map((_, j) => <td className="border-b p-2" key={j}>{row[j] ?? ''}</td>)}</tr>)}</tbody></table></div>}
                <button type="button" disabled={busy || !preview.can_import} onClick={confirmImport} className="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"><Upload className="mr-2 h-4 w-4"/>Potvrdi import</button>
            </>}
            {!file && <div className="flex items-center gap-2 text-sm text-gray-500"><FileSpreadsheet className="h-4 w-4"/>Izaberite CSV fajl za preview i ručno mapiranje kolona.</div>}
        </section>
    </div>;
}
