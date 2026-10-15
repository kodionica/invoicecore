import React, { useState }                                    from 'react';
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

type SortColumn = 'status' | 'number' | 'client' | 'date' | 'dueDate' | 'total';
type SortDirection = 'asc' | 'desc';

export default function Invoices() {
    const { invoices, clients, activeCompanyId, updateInvoiceStatus, deleteInvoice, sendInvoiceEmail, meta } = useApp();
    const navigate = useNavigate();
    const [ searchTerm, setSearchTerm ] = useState( '' );
    const [ sort, setSort ] = useState<{ column: SortColumn; direction: SortDirection } | null>( null );
    const [ sendingInvoiceId, setSendingInvoiceId ] = useState<number | null>( null );
    const statusOptions = getInvoiceStatusOptions( meta?.invoice_statuses );

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
        await updateInvoiceStatus( id, newStatus as any );
        toast.success( 'Status promenjen' );
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

            <div className="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                <Filters searchTerm={ searchTerm } onSearchTermChange={ setSearchTerm }/>

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
                                            className={ `block rounded-full border px-3 py-1 w-full text-xs text-center font-semibold ring-1 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-xs sm:leading-6 ${ invoiceStatusSelectClass[invoice.status] }` }
                                        >
                                            { statusOptions.map( ( status ) => (
                                                <option key={ status.key } value={ status.key }>{ status.label }</option>
                                            ) ) }
                                        </select>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <Link to={ `/dashboard/invoices/${ invoice.id }` }>#{ invoice.number }</Link>
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
                                            <button onClick={ () => deleteInvoice( invoice.id ) } className="text-red-400 hover:text-red-600" title="Obriši">
                                                <Trash2 className="h-4 w-4"/>
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
