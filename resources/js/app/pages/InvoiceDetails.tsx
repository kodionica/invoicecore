import React, { useRef } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useApp } from '../context/AppContext';
import { ArrowLeft, Printer, Download, Mail, Trash2, Edit, CheckCircle, XCircle, Send } from 'lucide-react';
import { format } from 'date-fns';
import { srLatn } from 'date-fns/locale';
import { toast } from 'sonner';

export default function InvoiceDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { invoices, clients, companies, updateInvoiceStatus } = useApp();
  const printRef = useRef<HTMLDivElement>(null);

  const invoice = invoices.find(i => i.id === id);
  const client = clients.find(c => c.id === invoice?.clientId);
  const company = companies.find(c => c.id === invoice?.companyId);

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

  const handleStatusChange = async (status: 'paid' | 'sent' | 'draft' | 'overdue' | 'cancelled') => {
    await updateInvoiceStatus(invoice.id, status);
    toast.success(`Status fakture promenjen u ${status}`);
  };

  const handleDelete = () => {
    if (confirm('Da li ste sigurni da želite da obrišete ovu fakturu?')) {
      // In a real app we would have a deleteInvoice function in context
      toast.success('Faktura obrisana');
      navigate('/dashboard/invoices');
    }
  };

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
            <ArrowLeft className="h-5 w-5" />
          </button>
          <div className="flex-1 flex items-center gap-3">
            <h1 className="text-2xl font-bold text-gray-900">Faktura #{invoice.number}</h1>
            <select
              value={invoice.status}
              onChange={(e) => handleStatusChange(e.target.value as any)}
              className={`block rounded-md border-0 py-1.5 pl-3 pr-10 text-xs font-semibold ring-1 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-xs sm:leading-6
                ${invoice.status === 'paid' ? 'bg-green-100 text-green-800 ring-green-200' : 
                  invoice.status === 'sent' ? 'bg-blue-100 text-blue-800 ring-blue-200' : 
                  invoice.status === 'overdue' ? 'bg-red-100 text-red-800 ring-red-200' : 'bg-gray-100 text-gray-800 ring-gray-200'}`}
            >
              <option value="draft">NACRT</option>
              <option value="sent">POSLATO</option>
              <option value="paid">PLAĆENO</option>
              <option value="overdue">KASNI</option>
            </select>
          </div>
        </div>

        {/* Action buttons */}
        <div className="flex flex-wrap gap-2 pl-14">
          <button 
            className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
          >
            <Mail className="h-4 w-4 mr-2" />
            Pošalji Emailom
          </button>
          <button 
            onClick={handlePrint}
            className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            <Printer className="h-4 w-4 mr-2" />
            Štampaj
          </button>
          <button 
            className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            <Download className="h-4 w-4 mr-2" />
            PDF
          </button>
          <button 
            onClick={handleDelete}
            className="inline-flex items-center px-4 py-2 border border-red-200 shadow-sm text-sm font-medium rounded-md text-red-600 bg-red-50 hover:bg-red-100"
          >
            <Trash2 className="h-4 w-4 mr-2" />
            Obriši
          </button>
        </div>
      </div>

      {/* Invoice Document */}
      <div className="bg-white shadow-lg rounded-lg overflow-hidden print:shadow-none" ref={printRef}>
        <div className="p-8 sm:p-12">
          
          {/* Header */}
          <div className="flex flex-col sm:flex-row justify-between items-start gap-8 border-b border-gray-100 pb-8 mb-8">
            <div>
               <div className="h-12 w-12 bg-indigo-600 rounded-lg flex items-center justify-center mb-4">
                  <span className="font-bold text-2xl text-white">{company.name.substring(0,1)}</span>
               </div>
               <h2 className="text-xl font-bold text-gray-900">{company.name}</h2>
               <div className="text-gray-500 text-sm mt-2 space-y-1">
                 <p>{company.address}, {company.city}</p>
                 <p>PIB: {company.tax_id}</p>
               </div>
            </div>
            <div className="text-right sm:text-right">
              <h1 className="text-3xl font-bold text-gray-900 mb-2">FAKTURA</h1>
              <p className="text-lg font-medium text-gray-600">#{invoice.number}</p>
              <div className="mt-4 space-y-1 text-sm text-gray-500">
                <div className="flex justify-between gap-8">
                  <span>Datum izdavanja:</span>
                  <span className="font-medium text-gray-900">{format(new Date(invoice.date), 'dd. MMM yyyy', { locale: srLatn })}</span>
                </div>
                <div className="flex justify-between gap-8">
                  <span>Rok plaćanja:</span>
                  <span className="font-medium text-gray-900">{format(new Date(invoice.dueDate), 'dd. MMM yyyy', { locale: srLatn })}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Client Info */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-8">
            <div>
              <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Za klijenta:</h3>
              <div className="text-gray-900 font-medium text-lg">{client.name}</div>
              <div className="text-gray-500 text-sm mt-1 space-y-1">
                <p>{client.address}</p>
                <p>{client.email}</p>
                {client.tax_id && <p>PIB: {client.tax_id}</p>}
              </div>
            </div>
          </div>

          {/* Items Table */}
          <div className="mt-8">
            <table className="min-w-full divide-y divide-gray-200">
              <thead>
                <tr>
                  <th scope="col" className="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Opis</th>
                  <th scope="col" className="py-3.5 px-3 text-right text-sm font-semibold text-gray-900">Količina</th>
                  <th scope="col" className="py-3.5 px-3 text-right text-sm font-semibold text-gray-900">Cena</th>
                  <th scope="col" className="py-3.5 pl-3 pr-4 text-right text-sm font-semibold text-gray-900 sm:pr-0">Ukupno</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {invoice.items.map((item) => (
                  <tr key={item.id}>
                    <td className="py-4 pl-4 pr-3 text-sm sm:pl-0">
                      <div className="font-medium text-gray-900">{item.description}</div>
                    </td>
                    <td className="py-4 px-3 text-sm text-right text-gray-500">{item.quantity}</td>
                    <td className="py-4 px-3 text-sm text-right text-gray-500">€{item.price.toFixed(2)}</td>
                    <td className="py-4 pl-3 pr-4 text-sm text-right text-gray-900 font-medium sm:pr-0">
                      €{(item.quantity * item.price).toFixed(2)}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr>
                  <th scope="row" colSpan={3} className="hidden pl-4 pr-3 pt-6 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0">
                    Međuzbir
                  </th>
                  <th scope="row" className="pl-4 pr-3 pt-6 text-left text-sm font-normal text-gray-500 sm:hidden">
                    Međuzbir
                  </th>
                  <td className="pl-3 pr-4 pt-6 text-right text-sm text-gray-500 sm:pr-0">
                    €{invoice.total.toFixed(2)}
                  </td>
                </tr>
                <tr>
                  <th scope="row" colSpan={3} className="hidden pl-4 pr-3 pt-4 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0">
                    PDV (20%)
                  </th>
                   <th scope="row" className="pl-4 pr-3 pt-4 text-left text-sm font-normal text-gray-500 sm:hidden">
                    PDV (20%)
                  </th>
                  <td className="pl-3 pr-4 pt-4 text-right text-sm text-gray-500 sm:pr-0">
                    €{(invoice.total * 0.2).toFixed(2)}
                  </td>
                </tr>
                <tr>
                  <th scope="row" colSpan={3} className="hidden pl-4 pr-3 pt-4 text-right text-base font-bold text-gray-900 sm:table-cell sm:pl-0">
                    Ukupno za plaćanje
                  </th>
                   <th scope="row" className="pl-4 pr-3 pt-4 text-left text-base font-bold text-gray-900 sm:hidden">
                    Ukupno
                  </th>
                  <td className="pl-3 pr-4 pt-4 text-right text-base font-bold text-gray-900 sm:pr-0">
                    €{(invoice.total * 1.2).toFixed(2)}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div className="mt-12 pt-8 border-t border-gray-100">
            <p className="text-gray-500 text-sm">
              Hvala vam na poslovanju! Molimo vas da iznos uplatite u roku od {format(new Date(invoice.dueDate), 'dd. MMM yyyy', { locale: srLatn })}.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
