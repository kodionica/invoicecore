import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { Plus, Search, Filter, Download, Eye, Trash2, Send } from 'lucide-react';
import { useNavigate } from 'react-router';
import { format } from 'date-fns';
import { srLatn } from 'date-fns/locale';
import { toast } from 'sonner';

export default function Invoices() {
  const { invoices, clients, activeCompanyId, updateInvoiceStatus } = useApp();
  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState('');

  const filteredInvoices = invoices
    .filter(inv => inv.companyId === activeCompanyId)
    .filter(inv => inv.number.includes(searchTerm) || clients.find(c => c.id === inv.clientId)?.name.toLowerCase().includes(searchTerm.toLowerCase()));

  const handleStatusChange = async (id: string, newStatus: string) => {
    await updateInvoiceStatus(id, newStatus as any);
    toast.success('Status promenjen');
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'paid': return 'bg-green-100 text-green-800 border-green-200';
      case 'sent': return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'overdue': return 'bg-red-100 text-red-800 border-red-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'paid': return 'PLAĆENO';
      case 'sent': return 'POSLATO';
      case 'overdue': return 'KASNI';
      default: return 'NACRT';
    }
  };
  
  return (
    <div className="space-y-6">
      <div className="sm:flex sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Fakture</h1>
          <p className="mt-2 text-sm text-gray-700">Pregled svih izdatih faktura.</p>
        </div>
        <div className="mt-4 sm:mt-0">
          <button
            onClick={() => navigate('/dashboard/invoices/new')}
            className="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
          >
            <Plus className="h-4 w-4 mr-2" />
            Nova Faktura
          </button>
        </div>
      </div>

      <div className="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        {/* Filters & Search */}
        <div className="p-4 border-b border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="relative rounded-md shadow-sm max-w-xs w-full">
            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
              <Search className="h-4 w-4 text-gray-400" aria-hidden="true" />
            </div>
            <input
              type="text"
              className="block w-full rounded-md border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2"
              placeholder="Pretraži fakture..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <div className="flex gap-2">
            <button className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              <Filter className="h-4 w-4 mr-2 text-gray-500" />
              Filteri
            </button>
             <button className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              <Download className="h-4 w-4 mr-2 text-gray-500" />
              Izvoz
            </button>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Broj Fakture
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Klijent
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Datum
                </th>
                 <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Rok
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Iznos
                </th>
                <th scope="col" className="relative px-6 py-3">
                  <span className="sr-only">Akcije</span>
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredInvoices.map((invoice) => {
                const client = clients.find(c => c.id === invoice.clientId);
                return (
                  <tr key={invoice.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ${getStatusColor(invoice.status)}`}>
                        {getStatusLabel(invoice.status)}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      #{invoice.number}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {client?.name || 'Nepoznat'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {format(new Date(invoice.date), 'dd. MMM yyyy', { locale: srLatn })}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {format(new Date(invoice.dueDate), 'dd. MMM yyyy', { locale: srLatn })}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                      €{invoice.total.toLocaleString()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex justify-end gap-2">
                        <button onClick={() => navigate(`/dashboard/invoices/${invoice.id}`)} className="text-indigo-600 hover:text-indigo-900" title="Pregledaj">
                          <Eye className="h-4 w-4" />
                        </button>
                        <button className="text-gray-400 hover:text-gray-600" title="Pošalji">
                          <Send className="h-4 w-4" />
                        </button>
                         <button className="text-red-400 hover:text-red-600" title="Obriši">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          {filteredInvoices.length === 0 && (
             <div className="text-center py-12 text-gray-500">
               Nema pronađenih faktura.
             </div>
          )}
        </div>
      </div>
    </div>
  );
}
