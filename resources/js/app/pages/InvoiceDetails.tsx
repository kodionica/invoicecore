import React, {useEffect, useRef, useState} from 'react';
import {useParams, useNavigate, useLocation} from 'react-router';
import {useApp, type InvoiceIncomeCategory, type InvoiceStatus, type InvoicePdfVersion} from '../context/AppContext';
import {ArrowLeft, Printer, Download, Mail, Trash2, Pencil, Save, X, History} from 'lucide-react';
import {toast} from 'sonner';
import {getInvoiceStatusLabelMap, getInvoiceStatusOptions, invoiceStatusSelectClass} from '../utils/invoiceStatus';
import InvoiceDocument from '../components/InvoiceDocument';
import {LoadingButton, LoadingSpinner} from '../components/Loading';
import {formatLocalDate} from '../utils/format';

export default function InvoiceDetails() {
   const {id} = useParams();
   const navigate = useNavigate();
    const location = useLocation();
    const {invoices, clients, companies, updateInvoiceStatus, refreshInvoice, updateInvoiceItemClassifications, getInvoiceHistory, getInvoicePdfVersions, downloadInvoicePdfVersion, getInvoiceEmailHistory, deleteInvoice, downloadInvoicePdf, sendInvoiceEmail, meta} = useApp();
    const printRef = useRef<HTMLDivElement>(null);
    const [sendingEmail, setSendingEmail] = useState(false);
    const [emailFormOpen, setEmailFormOpen] = useState(false);
    const [emailRecipient, setEmailRecipient] = useState('');
    const [emailSubject, setEmailSubject] = useState('');
    const [emailMessage, setEmailMessage] = useState('');
    const [emailHistory, setEmailHistory] = useState<any[]>([]);
    const [downloadingPdf, setDownloadingPdf] = useState(false);
    const [updatingStatus, setUpdatingStatus] = useState(false);
    const [pdfVersionsLoading, setPdfVersionsLoading] = useState(false);
    const [pdfVersionsError, setPdfVersionsError] = useState<string | null>(null);
    const pdfVersionsRequestRef = useRef(0);
    const [pdfMenuOpen, setPdfMenuOpen] = useState(false);
    const [editingClassifications, setEditingClassifications] = useState(false);
    const [savingClassifications, setSavingClassifications] = useState(false);
    const [deletingInvoice, setDeletingInvoice] = useState(false);
    const [kpoDeletionConfirmation, setKpoDeletionConfirmation] = useState<any | null>(null);
    const [classificationDraft, setClassificationDraft] = useState<Record<number, InvoiceIncomeCategory>>({});
    const [history, setHistory] = useState<any[] | null>(null);
    const [pdfVersions, setPdfVersions] = useState<InvoicePdfVersion[]>([]);
    const [paymentDialogOpen, setPaymentDialogOpen] = useState(false);
    const [paymentDate, setPaymentDate] = useState('');
    const [paymentNote, setPaymentNote] = useState('');
    const pdfMenuRef = useRef<HTMLDivElement>(null);

    const openPaymentDialog = () => {
        setPaymentDate(invoice?.paidAt ? invoice.paidAt.slice(0, 10) : formatLocalDate(new Date()));
        setPaymentNote(invoice?.paymentNote ?? '');
        setPaymentDialogOpen(true);
    };

    const invoiceId = id ? Number(id) : null;
    const invoice = invoiceId ? invoices.find(i => i.id === invoiceId) : undefined;
    const client = clients.find(c => c.id === invoice?.clientId);
    const company = companies.find(c => c.id === invoice?.companyId);
    const statusOptions = getInvoiceStatusOptions(meta?.invoice_statuses);
    const statusLabelMap = getInvoiceStatusLabelMap(statusOptions);

    const loadPdfVersions = async (invoiceId: number): Promise<void> => {
        const requestId = ++pdfVersionsRequestRef.current;
        setPdfVersionsLoading(true);
        setPdfVersionsError(null);
        try {
            const versions = await getInvoicePdfVersions(invoiceId);
            if (requestId === pdfVersionsRequestRef.current) {
                setPdfVersions(versions);
            }
        } catch (error) {
            if (requestId === pdfVersionsRequestRef.current) {
                setPdfVersionsError('Istorija PDF verzija trenutno nije dostupna.');
            }
            throw error;
        } finally {
            if (requestId === pdfVersionsRequestRef.current) {
                setPdfVersionsLoading(false);
            }
        }
    };

    useEffect(() => {
        if (!invoice) return;
        setClassificationDraft(Object.fromEntries(
            invoice.items.map(item => [item.id, item.incomeCategory ?? 'unclassified'])
        ));
        setEditingClassifications(false);
        setEmailSubject(`Faktura #${invoice.number}`);
        setEmailMessage('U prilogu se nalazi PDF faktura. Ako imate pitanja, slobodno nas kontaktirajte.');
        setEmailRecipient(client?.email ?? '');
        getInvoiceEmailHistory(invoice.id).then(setEmailHistory).catch(() => setEmailHistory([]));
    }, [invoice?.id]);

   useEffect(() => {
       if (!invoice) return;
       loadPdfVersions(invoice.id).catch(() => undefined);
   }, [invoice?.id]);

    const openPaymentDialogFromNavigation = Boolean(
        (location.state as {openPaymentDialog?: boolean} | null)?.openPaymentDialog
    );

    useEffect(() => {
        if (!invoice || !openPaymentDialogFromNavigation) return;
        openPaymentDialog();
    }, [invoice?.id, openPaymentDialogFromNavigation]);

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

    const refreshPdfVersions = async (): Promise<void> => {
        await loadPdfVersions(invoice.id);
    };

    const synchronizePdfState = async (): Promise<void> => {
        const results = await Promise.allSettled([
            refreshInvoice(invoice.id),
            refreshPdfVersions(),
        ]);

        if (results.some(result => result.status === 'rejected')) {
            toast.error('PDF je uspešno obrađen, ali prikaz verzija nije u potpunosti osvežen. Pokušajte ponovo.');
        }
    };

    const handlePrint = () => {
        window.print();
    };

    const handleDownloadPdf = async () => {
        if (downloadingPdf) return;
        setDownloadingPdf(true);
        try {
            await downloadInvoicePdf(invoice.id, invoice.number);
            await synchronizePdfState();
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
            await synchronizePdfState();
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
            await sendInvoiceEmail(invoice.id, {
                recipient: emailRecipient.trim(),
                subject: emailSubject.trim() || undefined,
                message: emailMessage.trim() || undefined,
            });
            await synchronizePdfState();
            setEmailHistory(await getInvoiceEmailHistory(invoice.id));
            setEmailFormOpen(false);
            toast.success(`Email je poslat na ${emailRecipient.trim()}`);
        } catch (error: any) {
            await Promise.allSettled([
                refreshInvoice(invoice.id),
                refreshPdfVersions(),
                getInvoiceEmailHistory(invoice.id).then(setEmailHistory),
            ]);
            const message = error?.response?.data?.message ?? 'Neuspešno slanje emaila.';
            toast.error(message);
        } finally {
            setSendingEmail(false);
        }
    };

    const handleStatusChange = async (status: InvoiceStatus) => {
        if (updatingStatus || status === invoice.status) return;
        if (status === 'paid') {
            openPaymentDialog();
            return;
        }
        if (invoice.status === 'paid' && !window.confirm('Vratiti fakturu u status koji nije plaćen? Datum uplate će biti uklonjen iz aktivnih podataka.')) return;
        setUpdatingStatus(true);
        try {
            await updateInvoiceStatus(invoice.id, status);
            await synchronizePdfState();
            const label = statusLabelMap[status] ?? status;
            toast.success(`Status fakture promenjen u ${label}`);
        } catch (error: any) {
            toast.error(error?.response?.data?.message ?? 'Status fakture nije promenjen.');
        } finally {
            setUpdatingStatus(false);
        }
    };

    const handleSavePayment = async () => {
        if (updatingStatus || !paymentDate) return;
        setUpdatingStatus(true);
        try {
            await updateInvoiceStatus(invoice.id, 'paid', {
                paidAt: paymentDate,
                paymentNote: paymentNote.trim() || null,
            });
            await synchronizePdfState();
            setPaymentDialogOpen(false);
            toast.success('Faktura je označena kao plaćena');
        } catch (error: any) {
            toast.error(error?.response?.data?.message ?? 'Podaci o uplati nisu sačuvani.');
        } finally {
            setUpdatingStatus(false);
        }
    };

    const handleRetryPdfVersions = async () => {
        try {
            await Promise.all([refreshInvoice(invoice.id), refreshPdfVersions()]);
        } catch {
            toast.error('Istorija PDF verzija i dalje nije dostupna.');
        }
    };

    const handleHistory = async () => {
        if (!invoice) return;
        try {
            const response = await getInvoiceHistory(invoice.id);
            setHistory(response.revisions ?? []);
        } catch (error: any) {
            toast.error(error?.response?.data?.message ?? 'Istorija izmena nije dostupna.');
        }
    };

    const handleDownloadPdfVersion = async (version: InvoicePdfVersion) => {
        try {
            await downloadInvoicePdfVersion(invoice.id, version.version, invoice.number);
        } catch (error: any) {
            toast.error(error?.response?.data?.message ?? 'PDF verzija nije dostupna.');
        }
    };

    const handleDelete = async (confirmedKpoRemoval = false) => {
        if (deletingInvoice) return;
        if (confirmedKpoRemoval || confirm('Da li ste sigurni da želite da obrišete ovu fakturu?')) {
            setDeletingInvoice(true);
            try {
                await deleteInvoice(invoice.id, confirmedKpoRemoval);
                toast.success('Faktura obrisana');
                navigate('/dashboard/invoices');
            } catch (error: any) {
                if (error?.response?.status === 409 && error?.response?.data?.kpo_deletion_confirmation) {
                    setKpoDeletionConfirmation(error.response.data.kpo_deletion_confirmation);
                    return;
                }
                toast.error(error?.response?.data?.message ?? 'Brisanje fakture nije uspelo.');
            } finally {
                setDeletingInvoice(false);
            }
        }
    };

    const handleSaveClassifications = async () => {
        if (!invoice || savingClassifications) return;
        setSavingClassifications(true);
        try {
            await updateInvoiceItemClassifications(invoice.id, invoice.items.map(item => ({
                id: item.id,
                incomeCategory: classificationDraft[item.id] ?? 'unclassified',
            })));
            setEditingClassifications(false);
            toast.success('Klasifikacije stavki su sačuvane');
        } catch (error: any) {
            toast.error(error?.response?.data?.message ?? 'Neuspešno čuvanje klasifikacija.');
        } finally {
            setSavingClassifications(false);
        }
    };

    const currency = invoice.currency || company.currency || 'RSD';

    return (
        <div className="max-w-5xl mx-auto space-y-6 pb-12">
            {kpoDeletionConfirmation && (
                <section className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="alert">
                    <h2 className="font-semibold">Faktura ima aktivne KPO zapise</h2>
                    <p className="mt-1">Brisanjem će povezani KPO zapisi biti soft-obrisani i sačuvani u istoriji. Iznosi i redni brojevi ostaju sačuvani.</p>
                    <ul className="mt-3 list-disc space-y-1 pl-5">
                        {kpoDeletionConfirmation.entries?.map((entry: any) => (
                            <li key={entry.id}>KPO #{entry.entry_number} · {entry.income_category || 'legacy'} · {entry.total_rsd} RSD</li>
                        ))}
                    </ul>
                    <div className="mt-4 flex gap-2">
                        <button type="button" onClick={() => { setKpoDeletionConfirmation(null); void handleDelete(true); }} disabled={deletingInvoice} className="rounded-md bg-amber-700 px-3 py-2 font-medium text-white disabled:opacity-60">Potvrdi brisanje fakture i KPO zapisa</button>
                        <button type="button" onClick={() => setKpoDeletionConfirmation(null)} className="rounded-md border border-amber-400 px-3 py-2 font-medium text-amber-900">Otkaži</button>
                    </div>
                </section>
            )}
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
                        <span className="inline-flex items-center gap-2" aria-busy={updatingStatus}>
                            {updatingStatus && <LoadingSpinner label="Čuvanje statusa..." size="sm"/>}
                            <select
                                value={invoice.status}
                                onChange={(e) => handleStatusChange(e.target.value as InvoiceStatus)}
                                disabled={updatingStatus}
                                className={`block rounded-md border-0 py-1.5 pl-3 pr-10 text-xs font-semibold ring-1 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-xs sm:leading-6 ${invoiceStatusSelectClass[invoice.status]}`}
                            >
                                {statusOptions.map((status) => (
                                    <option key={status.key} value={status.key}>{status.label}</option>
                                ))}
                            </select>
                            {invoice.status === 'paid' && (
                                <button type="button" onClick={openPaymentDialog} className="text-xs text-indigo-700 hover:text-indigo-900">
                                    {invoice.paidAt ? 'Izmeni uplatu' : 'Unesi datum uplate'}
                                </button>
                            )}
                        </span>
                    </div>
                </div>

                {/* Action buttons */}
                <div className="flex flex-wrap gap-2 pl-14">
                    <button
                        onClick={() => navigate(`/dashboard/invoices/${invoice.id}/edit`)}
                        className="inline-flex items-center px-4 py-2 border border-indigo-200 shadow-sm text-sm font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100"
                    >
                        <Pencil className="h-4 w-4 mr-2"/> Izmeni fakturu
                    </button>
                    <button
                        onClick={handleHistory}
                        className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <History className="h-4 w-4 mr-2"/> Istorija izmena
                    </button>
                    <LoadingButton
                        onClick={() => setEmailFormOpen(open => !open)}
                        disabled={sendingEmail}
                        loading={sendingEmail}
                        loadingLabel="Slanje..."
                        className={`inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 ${sendingEmail ? 'opacity-60 cursor-not-allowed' : ''}`}
                    >
                        <Mail className="h-4 w-4 mr-2"/>
                        Pošalji Emailom
                    </LoadingButton>
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
                        <LoadingButton
                            type="button"
                                    onClick={() => {
                                        setPdfMenuOpen(false);
                                        handleDownloadPdf();
                                    }}
                            className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                        >
                                    Preuzmi postojeći PDF
                        </LoadingButton>
                        <LoadingButton
                            type="button"
                                    onClick={() => {
                                        setPdfMenuOpen(false);
                                        handleRegeneratePdf();
                                    }}
                            loading={downloadingPdf}
                            loadingLabel="Generisanje PDF-a..."
                            className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                        >
                            Generiši novi PDF
                        </LoadingButton>
                            </div>
                        )}
                    </div>
                    <LoadingButton
                        onClick={() => { void handleDelete(); }}
                        loading={deletingInvoice}
                        loadingLabel="Brisanje..."
                        className="inline-flex items-center px-4 py-2 border border-red-200 shadow-sm text-sm font-medium rounded-md text-red-600 bg-red-50 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Trash2 className="h-4 w-4 mr-2"/>
                        Obriši
                    </LoadingButton>
                </div>
            </div>

            {paymentDialogOpen && (
                <section className="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950 print:hidden" role="dialog" aria-label="Podaci o uplati">
                    <h2 className="font-semibold">Označi fakturu kao plaćenu</h2>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <label className="block font-medium">
                            Datum uplate
                            <input type="date" value={paymentDate} onChange={event => setPaymentDate(event.target.value)} className="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-normal text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required />
                        </label>
                        <label className="block font-medium">
                            Napomena (opciono)
                            <input value={paymentNote} onChange={event => setPaymentNote(event.target.value)} className="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-normal text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                        </label>
                    </div>
                    <div className="mt-3 flex gap-2">
                        <LoadingButton type="button" onClick={() => void handleSavePayment()} loading={updatingStatus} loadingLabel="Čuvanje..." className="rounded-md bg-indigo-600 px-3 py-2 font-medium text-white disabled:opacity-60">
                            Potvrdi uplatu
                        </LoadingButton>
                        <button type="button" onClick={() => setPaymentDialogOpen(false)} disabled={updatingStatus} className="rounded-md border border-indigo-300 px-3 py-2 font-medium disabled:opacity-60">Otkaži</button>
                    </div>
                </section>
            )}

            {(invoice.status === 'paid' || invoice.paidAt || invoice.paymentNote) && (
                <section className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 p-6 print:hidden">
                    <h2 className="text-lg font-semibold text-gray-900">Plaćanje</h2>
                    <p className="mt-2 text-sm text-gray-600">Datum uplate: {invoice.paidAt ? new Date(invoice.paidAt).toLocaleDateString('sr-Latn-RS') : 'Nije unet'}</p>
                    {invoice.paymentNote && <p className="mt-1 text-sm text-gray-600">Napomena: {invoice.paymentNote}</p>}
                </section>
            )}

            {emailFormOpen && (
                <section className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 p-6 print:hidden">
                    <h2 className="text-lg font-semibold text-gray-900">Slanje fakture emailom</h2>
                    <div className="mt-4 space-y-4">
                        <label className="block text-sm font-medium text-gray-700">
                            Primalac
                            <input type="email" value={emailRecipient} onChange={event => setEmailRecipient(event.target.value)} required className="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm" />
                        </label>
                        <label className="block text-sm font-medium text-gray-700">
                            Naslov
                            <input value={emailSubject} onChange={event => setEmailSubject(event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm" />
                        </label>
                        <label className="block text-sm font-medium text-gray-700">
                            Poruka
                            <textarea value={emailMessage} onChange={event => setEmailMessage(event.target.value)} rows={4} className="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm" />
                        </label>
                        <LoadingButton type="button" onClick={handleSendEmail} loading={sendingEmail} loadingLabel="Slanje..." className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <Mail className="mr-2 h-4 w-4" /> Pošalji
                        </LoadingButton>
                    </div>
                </section>
            )}

            {/* Invoice Document */}
            <div ref={printRef}>
                <InvoiceDocument invoice={invoice} client={client} company={company} currency={currency} meta={meta}/>
            </div>

            <section className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 p-6 print:hidden">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Klasifikacija prihoda</h2>
                        <p className="text-sm text-gray-500">Menja se samo kategorija stavke, bez izmene iznosa fakture.</p>
                    </div>
                    {!editingClassifications ? (
                        <button type="button" onClick={() => setEditingClassifications(true)} className="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <Pencil className="mr-2 h-4 w-4"/> Izmeni
                        </button>
                    ) : (
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setEditingClassifications(false)} className="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <X className="mr-2 h-4 w-4"/> Otkaži
                            </button>
                            <LoadingButton type="button" onClick={handleSaveClassifications} loading={savingClassifications} loadingLabel="Čuvanje..." className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-60">
                                <Save className="mr-2 h-4 w-4"/> Sačuvaj
                            </LoadingButton>
                        </div>
                    )}
                </div>
                <div className="mt-4 divide-y divide-gray-200">
                    {invoice.items.map(item => (
                        <div key={item.id} className="flex items-center justify-between gap-4 py-3">
                            <span className="min-w-0 truncate text-sm text-gray-700">{item.description}</span>
                            {editingClassifications ? (
                                <select value={classificationDraft[item.id] ?? 'unclassified'} onChange={event => setClassificationDraft(current => ({...current, [item.id]: event.target.value as InvoiceIncomeCategory}))} className="rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="product">Proizvod</option>
                                    <option value="service">Usluga</option>
                                    <option value="unclassified">Nerazvrstano</option>
                                </select>
                            ) : (
                                <span className="text-sm text-gray-500">{classificationDraft[item.id] === 'product' ? 'Proizvod' : classificationDraft[item.id] === 'service' ? 'Usluga' : 'Nerazvrstano'}</span>
                            )}
                        </div>
                    ))}
                </div>
            </section>

            {history && (
                <section className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 p-6 print:hidden">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-gray-900">Istorija izmena</h2>
                        <button type="button" onClick={() => setHistory(null)} className="text-sm text-gray-500 hover:text-gray-900">Zatvori</button>
                    </div>
                    <div className="mt-4 space-y-3">
                        {history.length === 0 && <p className="text-sm text-gray-500">Nema zabeleženih izmena.</p>}
                        {history.map(revision => (
                            <div key={revision.id} className="rounded border border-gray-200 p-3 text-sm">
                                <div className="flex justify-between gap-3">
                                    <span className="font-medium text-gray-900">{revision.action_type}</span>
                                    <span className="text-gray-500">{revision.created_at}</span>
                                </div>
                                <p className="mt-1 text-gray-600">Promenjena polja: {(revision.changed_fields ?? []).join(', ') || '—'}</p>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <section className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 p-6 print:hidden">
                <h2 className="text-lg font-semibold text-gray-900">PDF verzije</h2>
                {pdfVersionsLoading ? (
                    <p className="mt-3 text-sm text-gray-500">Osvežavanje PDF verzija...</p>
                ) : pdfVersionsError ? (
                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <p className="text-sm text-amber-700">{pdfVersionsError}</p>
                        <button type="button" onClick={handleRetryPdfVersions} className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Pokušaj ponovo</button>
                    </div>
                ) : pdfVersions.length === 0 ? (
                    <p className="mt-3 text-sm text-gray-500">PDF još nije generisan.</p>
                ) : (
                    <div className="mt-4 divide-y divide-gray-200">
                        {pdfVersions.map(version => (
                            <div key={version.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                <div>
                                    <p className="font-medium text-gray-900">Verzija {version.version}{version.path === invoice.pdfPath ? ' · Trenutna' : ''}</p>
                                    <p className="text-gray-500">{version.createdAt ? new Date(version.createdAt).toLocaleString('sr-Latn-RS') : '—'} · {version.protectedAt || version.sentAt ? 'Poslata/zaštićena' : 'Nezaštićena'}</p>
                                </div>
                                <button type="button" onClick={() => handleDownloadPdfVersion(version)} className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Preuzmi</button>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            <section className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 p-6 print:hidden">
                <h2 className="text-lg font-semibold text-gray-900">Istorija slanja emaila</h2>
                {emailHistory.length === 0 ? (
                    <p className="mt-3 text-sm text-gray-500">Nema pokušaja slanja.</p>
                ) : (
                    <div className="mt-4 divide-y divide-gray-200">
                        {emailHistory.map(attempt => (
                            <div key={attempt.id} className="py-3 text-sm">
                                <div className="flex flex-wrap justify-between gap-2">
                                    <span className="font-medium text-gray-900">{attempt.recipient}</span>
                                    <span className="text-gray-500">{attempt.status} · {attempt.created_at ? new Date(attempt.created_at).toLocaleString('sr-Latn-RS') : '—'}</span>
                                </div>
                                <p className="mt-1 text-gray-500">PDF verzija: {attempt.pdf_version?.version ?? '—'}</p>
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
