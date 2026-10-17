import React, { useEffect, useState }                          from 'react';
import { type InvoiceStatus, useApp }                         from '../context/AppContext';
import { formatCurrency }                                     from '../utils/format';
import { ArrowDown, ArrowUp, ArrowUpDown, Eye, Trash2, Send } from 'lucide-react';
import { Link, useNavigate }                                  from 'react-router';
import { format }                                             from 'date-fns';
import { srLatn }                                             from 'date-fns/locale';
import { toast }                                              from 'sonner';
import { getInvoiceStatusOptions, invoiceStatusSelectClass }  from "../utils/invoiceStatus";
import SectionHeader                                          from "../components/invoices/SectionHeader";
import Filters                                                from "../components/invoices/Filters";
import {LoadingButton, LoadingSpinner}                         from '../components/Loading';

type SortColumn = 'status' | 'number' | 'client' | 'date' | 'dueDate' | 'total';
type SortDirection = 'asc' | 'desc';

export default function Invoices() {
    const { invoices, clients, activeCompanyId, updateInvoiceStatus, deleteInvoice, sendInvoiceEmail, loadInvoices, meta } = useApp();
    const navigate = useNavigate();
    const [ searchTerm, setSearchTerm ] = useState( '' );
    const [ sort, setSort ] = useState<{ column: SortColumn; direction: SortDirection } | null>( null );
    const [ sendingInvoiceId, setSendingInvoiceId ] = useState<number | null>( null );
    const [ updatingStatusId, setUpdatingStatusId ] = useState<number | null>( null );
    const [ deletingInvoiceId, setDeletingInvoiceId ] = useState<number | null>( null );
    const [ kpoDeletionConfirmation, setKpoDeletionConfirmation ] = useState<{invoiceId: number; entries: any[]} | null>(null);
    const [ overdueOnly, setOverdueOnly ] = useState(false);
    const statusOptions = getInvoiceStatusOptions( meta?.invoice_statuses );

    useEffect(() => {
        loadInvoices(overdueOnly).catch(() => undefined);
    }, [overdueOnly, activeCompanyId]);

    const filteredInvoices = invoices
        .filter( inv => ( activeCompanyId ? inv.companyId === activeCompanyId : false ) )
        .filter( inv => inv.number.includes( searchTerm ) || clients.find( c => c.id === inv.clientId )?.name.toLowerCase().includes( searchTerm.toLowerCase() ) );

    const sortedInvoices = sort
                           ? [ ...filteredInvoices ].sort( ( a, b ) => {
            let comparison: number;
            switch ( sort.column ) {
                case 'status':
                    comparison = a.status.localeCompare( b.status );
                    break;
                case 'number':
                    comparison = a.number.localeCompare( b.number, 'sr', { numeric: true, sensitivity: 'base' } );
                    break;
                case 'client':
                    comparison = ( clients.find( client => client.id === a.clientId )?.name ?? '' )
                        .localeCompare( clients.find( client => client.id === b.clientId )?.name ?? '', 'sr', { sensitivity: 'base' } );
                    break;
                case 'date':
                case 'dueDate':
                    comparison = Date.parse( a[sort.column] ) - Date.parse( b[sort.column] );
                    if ( Number.isNaN( comparison ) ) {
                        comparison = a[sort.column].localeCompare( b[sort.column] );
                    }
                    break;
                case 'total':
                    comparison = a.total - b.total;
                    break;
            }
            return sort.direction === 'asc' ? comparison : -comparison;
        } )
                           : filteredInvoices;

    const toggleSort = ( column: SortColumn ) => {
        setSort( current => {
            if ( current?.column !== column ) return { column, direction: 'asc' };
            if ( current.direction === 'asc' ) return { column, direction: 'desc' };
            return null;
        } );
    };

    const sortIcon = ( column: SortColumn ) => {
        if ( sort?.column !== column ) return <ArrowUpDown className="ml-1 inline h-3 w-3"/>;
        return sort.direction === 'asc'
               ? <ArrowUp className="ml-1 inline h-3 w-3"/>
               : <ArrowDown className="ml-1 inline h-3 w-3"/>;
    };

    const handleStatusChange = async ( id: number, newStatus: string ) => {
        if (updatingStatusId !== null) return;
        if (newStatus === 'paid') {
            navigate('/dashboard/invoices/' + id, {state: {openPaymentDialog: true}});
            toast.info('Datum uplate unesite na stranici detalja fakture.');
            return;
        }
        setUpdatingStatusId(id);
        try {
            await updateInvoiceStatus( id, newStatus as any );
            toast.success( 'Status promenjen' );
        } catch (error: any) {
            toast.error(error?.response?.data?.message ?? 'Promena statusa nije uspela.');
        } finally {
            setUpdatingStatusId(null);
        }
    };

    const handleDelete = async (id: number, confirmedKpoRemoval = false) => {
        if (deletingInvoiceId !== null) return;
        if (!confirmedKpoRemoval && !window.confirm('Da li ste sigurni da želite da obrišete ovu fakturu?')) return;
        setDeletingInvoiceId(id);
        try {
            await deleteInvoice(id, confirmedKpoRemoval);
            setKpoDeletionConfirmation(null);
        } catch (error: any) {
            if (error?.response?.status === 409 && error?.response?.data?.kpo_deletion_confirmation) {
                setKpoDeletionConfirmation({invoiceId: id, entries: error.response.data.kpo_deletion_confirmation.entries ?? []});
                return;
            }
            toast.error(error?.response?.data?.message ?? 'Brisanje fakture nije uspelo.');
        } finally {
            setDeletingInvoiceId(null);
        }
    };

    const handleSendEmail = async ( id: number, clientName?: string, currentStatus?: string ) => {
        if ( sendingInvoiceId ) return;
        setSendingInvoiceId( id );
        try {
            await sendInvoiceEmail( id );
            if ( currentStatus !== 'sent' ) {
                await updateInvoiceStatus( id, 'sent' as any );
            }
            toast.success( `Email je poslat${ clientName ? ` (${ clientName })` : '' }` );
        } catch ( error: any ) {
            const message = error?.response?.data?.message ?? 'Neuspešno slanje emaila.';
            toast.error( message );
        } finally {
            setSendingInvoiceId( null );
        }
    };

    return (
        <div className="space-y-6">
            <SectionHeader/>

            {kpoDeletionConfirmation && (
                <section className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="alert">
                    <h2 className="font-semibold">Faktura ima aktivne KPO zapise</h2>
                    <p className="mt-1">Potvrdite soft-brisanjem povezanih zapisa; iznosi, brojevi i istorija ostaju sačuvani.</p>
                    <ul className="mt-3 list-disc space-y-1 pl-5">
                        {kpoDeletionConfirmation.entries.map(entry => <li key={entry.id}>KPO #{entry.entry_number} · {entry.income_category || 'legacy'} · {entry.total_rsd} RSD</li>)}
                    </ul>
                    <div className="mt-4 flex gap-2">
                        <LoadingButton type="button" onClick={() => void handleDelete(kpoDeletionConfirmation.invoiceId, true)} loading={deletingInvoiceId === kpoDeletionConfirmation.invoiceId} loadingLabel="Brisanje..." className="rounded-md bg-amber-700 px-3 py-2 font-medium text-white disabled:opacity-60">Potvrdi brisanje</LoadingButton>
                        <button type="button" onClick={() => setKpoDeletionConfirmation(null)} className="rounded-md border border-amber-400 px-3 py-2 font-medium text-amber-900">Otkaži</button>
                    </div>
                </section>
            )}

            <div className="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                <Filters searchTerm={ searchTerm } onSearchTermChange={ setSearchTerm } overdueOnly={ overdueOnly } onOverdueOnlyChange={ setOverdueOnly }/>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                        <tr>
                            <th scope="col" aria-sort={ sort?.column === 'status' ? ( sort.direction === 'asc' ? 'ascending' : 'descending' ) : 'none' }
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <button type="button" onClick={ () => toggleSort( 'status' ) }>Status{ sortIcon( 'status' ) }</button>
                            </th>
                            <th scope="col" aria-sort={ sort?.column === 'number' ? ( sort.direction === 'asc' ? 'ascending' : 'descending' ) : 'none' }
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <button type="button" onClick={ () => toggleSort( 'number' ) }>Broj Fakture{ sortIcon( 'number' ) }</button>
                            </th>
                            <th scope="col" aria-sort={ sort?.column === 'client' ? ( sort.direction === 'asc' ? 'ascending' : 'descending' ) : 'none' }
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <button type="button" onClick={ () => toggleSort( 'client' ) }>Klijent{ sortIcon( 'client' ) }</button>
                            </th>
                            <th scope="col" aria-sort={ sort?.column === 'date' ? ( sort.direction === 'asc' ? 'ascending' : 'descending' ) : 'none' }
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <button type="button" onClick={ () => toggleSort( 'date' ) }>Datum{ sortIcon( 'date' ) }</button>
                            </th>
                            <th scope="col" aria-sort={ sort?.column === 'dueDate' ? ( sort.direction === 'asc' ? 'ascending' : 'descending' ) : 'none' }
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <button type="button" onClick={ () => toggleSort( 'dueDate' ) }>Rok{ sortIcon( 'dueDate' ) }</button>
                            </th>
                            <th scope="col" aria-sort={ sort?.column === 'total' ? ( sort.direction === 'asc' ? 'ascending' : 'descending' ) : 'none' }
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <button type="button" onClick={ () => toggleSort( 'total' ) }>Iznos{ sortIcon( 'total' ) }</button>
                            </th>
                            <th scope="col" className="relative px-6 py-3">
                                <span className="sr-only">Akcije</span>
                            </th>
                        </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                        { sortedInvoices.map( ( invoice ) => {
                            const client = clients.find( c => c.id === invoice.clientId );
                            return (
                                <tr key={ invoice.id } className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <select
                                            value={ invoice.status }
                                            onChange={ ( e ) => handleStatusChange( invoice.id, e.target.value as InvoiceStatus ) }
                                            disabled={updatingStatusId === invoice.id}
                                            aria-busy={updatingStatusId === invoice.id}
                                            className={ `block rounded-full border px-3 py-1 w-full text-xs text-center font-semibold ring-1 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-xs sm:leading-6 ${ invoiceStatusSelectClass[invoice.status] }` }
                                        >
                                            { statusOptions.map( ( status ) => (
                                                <option key={ status.key } value={ status.key }>{ status.label }</option>
                                            ) ) }
                                        </select>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <Link to={ `/dashboard/invoices/${ invoice.id }` }>#{ invoice.number }</Link>
                                        {invoice.isOverdue && <span className="ml-2 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Dospela</span>}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        { client?.id && <Link to={ `/dashboard/clients/${ client.id }` }>{ client.name || 'Nepoznat' }</Link> }
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        { format( new Date( invoice.date ), 'dd. M. yyyy', { locale: srLatn } ) }
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        { format( new Date( invoice.dueDate ), 'dd. M. yyyy', { locale: srLatn } ) }
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        { formatCurrency( invoice.total, invoice.currency ) }
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div className="flex justify-end gap-2">
                                            <button onClick={ () => navigate( `/dashboard/invoices/${ invoice.id }` ) } className="text-indigo-600 hover:text-indigo-900" title="Pregledaj">
                                                <Eye className="h-4 w-4"/>
                                            </button>
                                            <button
                                                onClick={ () => handleSendEmail( invoice.id, client?.name, invoice.status ) }
                                                className={ `text-gray-400 hover:text-gray-600 ${ sendingInvoiceId === invoice.id ? 'opacity-60 cursor-not-allowed' : '' }` }
                                                title="Pošalji"
                                                disabled={ sendingInvoiceId === invoice.id }
                                            >
                                                <Send className="h-4 w-4"/>
                                            </button>
                                            <button onClick={ () => void handleDelete( invoice.id ) } disabled={deletingInvoiceId === invoice.id} aria-busy={deletingInvoiceId === invoice.id} className="text-red-400 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-60" title="Obriši">
                                                {deletingInvoiceId === invoice.id ? <LoadingSpinner label="Brisanje fakture..." size="sm"/> : <Trash2 className="h-4 w-4"/>}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        } ) }
                        </tbody>
                    </table>
                    { sortedInvoices.length === 0 && (
                        <div className="text-center py-12 text-gray-500">
                            Nema pronađenih faktura.
                        </div>
                    ) }
                </div>
            </div>
        </div>
    );
}
