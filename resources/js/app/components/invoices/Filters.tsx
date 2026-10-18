import {Filter, RotateCcw, Search, SlidersHorizontal} from 'lucide-react';
import type {Client, InvoiceListFilters} from '../../context/AppContext';
import type {InvoiceStatusOption} from '../../utils/invoiceStatus';

interface FiltersProps {
    filters: InvoiceListFilters;
    clients: Client[];
    currencies: {code: string; name: string}[];
    statusOptions: InvoiceStatusOption[];
    open: boolean;
    active: boolean;
    onToggle: () => void;
    onChange: (filters: InvoiceListFilters) => void;
    onApply: () => void;
    onReset: () => void;
}

export default function Filters({filters, clients, currencies, statusOptions, open, active, onToggle, onChange, onApply, onReset}: FiltersProps) {
    const update = (value: Partial<InvoiceListFilters>) => onChange({...filters, ...value});

    return <div className="border-b border-gray-200 bg-gray-50">
        <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="relative w-full max-w-md">
                <Search className="pointer-events-none absolute left-0 top-3 ml-3 h-4 w-4 text-gray-400" aria-hidden="true" />
                <input type="search" className="block w-full rounded-md border-gray-300 py-2 pl-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Pretraži po broju ili klijentu..." value={filters.search ?? ''} onChange={event => update({search: event.target.value})} onKeyDown={event => {if (event.key === 'Enter') onApply();}} />
            </div>
            <button type="button" aria-expanded={open} onClick={onToggle} className={`inline-flex items-center justify-center rounded-md border px-3 py-2 text-sm font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 ${active ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'}`}>
                <Filter className="mr-2 h-4 w-4" />Filteri{active && <span className="ml-2 rounded-full bg-indigo-600 px-2 py-0.5 text-xs text-white">aktivni</span>}
            </button>
        </div>
        {open && <div className="space-y-4 border-t border-gray-200 p-4" role="region" aria-label="Filteri faktura">
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <label className="text-sm font-medium text-gray-700">Dospelost<select className="mt-1 block w-full rounded-md border-gray-300 text-sm" value={filters.overdue ? 'overdue' : 'all'} onChange={event => update({overdue: event.target.value === 'overdue'})}><option value="all">Sve fakture</option><option value="overdue">Dospela</option></select></label>
                <label className="text-sm font-medium text-gray-700">Status<select className="mt-1 block w-full rounded-md border-gray-300 text-sm" value={filters.status ?? ''} onChange={event => update({status: event.target.value as InvoiceStatusOption['key'] | ''})}><option value="">Svi statusi</option>{statusOptions.map(status => <option key={status.key} value={status.key}>{status.label}</option>)}</select></label>
                <label className="text-sm font-medium text-gray-700">Klijent<select className="mt-1 block w-full rounded-md border-gray-300 text-sm" value={filters.clientId ?? ''} onChange={event => update({clientId: event.target.value ? Number(event.target.value) : ''})}><option value="">Svi klijenti</option>{clients.map(client => <option key={client.id} value={client.id}>{client.name}</option>)}</select></label>
                <label className="text-sm font-medium text-gray-700">Valuta<select className="mt-1 block w-full rounded-md border-gray-300 text-sm" value={filters.currency ?? ''} onChange={event => update({currency: event.target.value})}><option value="">Sve valute</option>{currencies.map(currency => <option key={currency.code} value={currency.code}>{currency.code} — {currency.name}</option>)}</select></label>
                <label className="text-sm font-medium text-gray-700">Izdato od<input type="date" className="mt-1 block w-full rounded-md border-gray-300 text-sm" value={filters.issueDateFrom ?? ''} onChange={event => update({issueDateFrom: event.target.value})} /></label>
                <label className="text-sm font-medium text-gray-700">Izdato do<input type="date" className="mt-1 block w-full rounded-md border-gray-300 text-sm" value={filters.issueDateTo ?? ''} onChange={event => update({issueDateTo: event.target.value})} /></label>
            </div>
            <div className="flex flex-wrap gap-2"><button type="button" onClick={onApply} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><SlidersHorizontal className="mr-2 h-4 w-4" />Primeni filtere</button><button type="button" onClick={onReset} className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><RotateCcw className="mr-2 h-4 w-4" />Resetuj</button></div>
        </div>}
    </div>;
}
