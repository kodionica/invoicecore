import React, {useEffect, useRef, useState} from 'react';
import {useParams, useNavigate} from 'react-router';
import {useApp, type InvoiceStatus} from '../context/AppContext';
import {ArrowLeft, Printer, Download, Mail, Trash2} from 'lucide-react';
import {toast} from 'sonner';
import {getInvoiceStatusLabelMap, getInvoiceStatusOptions, invoiceStatusSelectClass} from '../utils/invoiceStatus';
import InvoiceDocument from '../components/InvoiceDocument';

export default function InvoiceDetails() {
    const {id} = useParams();
    const navigate = useNavigate();
    const {invoices, clients, companies, updateInvoiceStatus, deleteInvoice, downloadInvoicePdf, sendInvoiceEmail, meta} = useApp();
    const printRef = useRef<HTMLDivElement>(null);
    const [sendingEmail, setSendingEmail] = useState(false);
    const [downloadingPdf, setDownloadingPdf] = useState(false);
    const [pdfMenuOpen, setPdfMenuOpen] = useState(false);
    const pdfMenuRef = useRef<HTMLDivElement>(null);

    const invoiceId = id ? Number(id) : null;
    const invoice = invoiceId ? invoices.find(i => i.id === invoiceId) : undefined;
    const client = clients.find(c => c.id === invoice?.clientId);
    const company = companies.find(c => c.id === invoice?.companyId);
    const statusOptions = getInvoiceStatusOptions(meta?.invoice_statuses);
    const statusLabelMap = getInvoiceStatusLabelMap(statusOptions);

    useEffect(() => {
        if (!pdfMenuOpen) return;
        const handleClick = (event: MouseEvent) => {
            if (!pdfMenuRef.current) return;
            if (pdfMenuRef.current.contains(event.target as Node)) return;
            setPdfMenuOpen(false);
        };
        const handleKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setPdfMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClick);
        document.addEventListener('keydown', handleKey);
        return () => {
            document.removeEventListener('mousedown', handleClick);
            document.removeEventListener('keydown', handleKey);
        };
    }, [pdfMenuOpen]);

    if (!invoice || !client || !company) {
        return (
            <div className="text-center py-12">
                <h2 className="text-2xl font-bold text-gray-900">Faktura nije pronađena</h2>
                <button
                    onClick={() => navigate('/dashboard/invoices')}
                    className="mt-4 text-indigo-600 hover:text-indigo-500"
                >
                    Nazad na listu
                </button>
            </div>
        );
    }

    const handlePrint = () => {
        window.print();
    };

    const handleDownloadPdf = async () => {
        if (downloadingPdf) return;
        setDownloadingPdf(true);
        try {
            await downloadInvoicePdf(invoice.id, invoice.number);
            toast.success('PDF je preuzet');
        } catch (error: any) {
            const message = error?.response?.data?.message ?? 'Neuspešno preuzimanje PDF-a.';
            toast.error(message);
        } finally {
            setDownloadingPdf(false);
        }
    };

    const handleRegeneratePdf = async () => {
        if (downloadingPdf) return;
        setDownloadingPdf(true);
        try {
            await downloadInvoicePdf(invoice.id, invoice.number, {force: true});
            toast.success('PDF je ponovo generisan i preuzet');
        } catch (error: any) {
            const message = error?.response?.data?.message ?? 'Neuspešno generisanje PDF-a.';
            toast.error(message);
        } finally {
            setDownloadingPdf(false);
        }
    };

    const handleSendEmail = async () => {
        if (sendingEmail) return;
        setSendingEmail(true);
        try {
            await sendInvoiceEmail(invoice.id);
            if (invoice.status !== 'sent') {
                await updateInvoiceStatus(invoice.id, 'sent');
            }
            toast.success(`Email je poslat na ${client.email}`);
        } catch (error: any) {
            const message = error?.response?.data?.message ?? 'Neuspešno slanje emaila.';
            toast.error(message);
        } finally {
            setSendingEmail(false);
        }
    };

    const handleStatusChange = async (status: InvoiceStatus) => {
        await updateInvoiceStatus(invoice.id, status);
        const label = statusLabelMap[status] ?? status;
        toast.success(`Status fakture promenjen u ${label}`);
    };

    const handleDelete = async () => {
        if (confirm('Da li ste sigurni da želite da obrišete ovu fakturu?')) {
            await deleteInvoice(invoice.id)

            toast.success('Faktura obrisana');
            navigate('/dashboard/invoices');
        }
    };

    const currency = invoice.currency || company.currency || 'RSD';

    return (
        <div className="max-w-5xl mx-auto space-y-6 pb-12">
            {/* Header Actions */}
            <div className="flex flex-col gap-4 print:hidden">
                {/* Back button and title */}
                <div className="flex items-center gap-4">
                    <button
                        onClick={() => navigate('/dashboard/invoices')}
                        className="p-2 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-900 transition-colors"
                    >
                        <ArrowLeft className="h-5 w-5"/>
                    </button>
                    <div className="flex-1 flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-gray-900">Faktura #{invoice.number}</h1>
                        <select
                            value={invoice.status}
                            onChange={(e) => handleStatusChange(e.target.value as InvoiceStatus)}
                            className={`block rounded-md border-0 py-1.5 pl-3 pr-10 text-xs font-semibold ring-1 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-xs sm:leading-6 ${invoiceStatusSelectClass[invoice.status]}`}
                        >
                            {statusOptions.map((status) => (
                                <option key={status.key} value={status.key}>{status.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Action buttons */}
                <div className="flex flex-wrap gap-2 pl-14">
                    <button
                        onClick={handleSendEmail}
                        disabled={sendingEmail}
                        className={`inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 ${sendingEmail ? 'opacity-60 cursor-not-allowed' : ''}`}
                    >
                        <Mail className="h-4 w-4 mr-2"/>
                        {sendingEmail ? 'Slanje...' : 'Pošalji Emailom'}
                    </button>
                    <button
                        onClick={handlePrint}
                        className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <Printer className="h-4 w-4 mr-2"/>
                        Štampaj
                    </button>
                    <div className="relative" ref={pdfMenuRef}>
                        <button
                            onClick={() => setPdfMenuOpen(open => !open)}
                            disabled={downloadingPdf}
                            className={`inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ${downloadingPdf ? 'opacity-60 cursor-not-allowed' : ''}`}
                        >
                            <Download className="h-4 w-4 mr-2"/>
                            {downloadingPdf ? 'Preuzimanje...' : 'PDF'}
                        </button>
                        {pdfMenuOpen && (
                            <div className="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black/5 z-10">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setPdfMenuOpen(false);
                                        handleDownloadPdf();
                                    }}
                                    className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    Preuzmi postojeći PDF
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setPdfMenuOpen(false);
                                        handleRegeneratePdf();
                                    }}
                                    className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    Generiši novi PDF
                                </button>
                            </div>
                        )}
                    </div>
                    <button
                        onClick={handleDelete}
                        className="inline-flex items-center px-4 py-2 border border-red-200 shadow-sm text-sm font-medium rounded-md text-red-600 bg-red-50 hover:bg-red-100"
                    >
                        <Trash2 className="h-4 w-4 mr-2"/>
                        Obriši
                    </button>
                </div>
            </div>

            {/* Invoice Document */}
            <div ref={printRef}>
                <InvoiceDocument invoice={invoice} client={client} company={company} currency={currency} meta={meta}/>
            </div>
        </div>
    );
}
