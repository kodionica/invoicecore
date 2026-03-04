import React, { useState } from 'react';
import { useApp, Client } from '../context/AppContext';
import { Plus, Search, MoreHorizontal, Edit, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';

export default function Clients() {
  const { clients, activeCompanyId, deleteClient } = useApp();
  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState('');

  const filteredClients = clients
    .filter(c => c.companyId === activeCompanyId)
    .filter(c => c.name.toLowerCase().includes(searchTerm.toLowerCase()) || c.email.toLowerCase().includes(searchTerm.toLowerCase()));

  const handleDelete = async (id: string) => {
    await deleteClient(id);
    toast.success('Klijent obrisan');
  };

  return (
    <div className="space-y-6">
      <div className="sm:flex sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Klijenti</h1>
          <p className="mt-2 text-sm text-gray-700">Lista svih klijenata za izabranu firmu.</p>
        </div>
        <div className="mt-4 sm:mt-0">
          <button
            onClick={() => navigate('/dashboard/clients/new')}
            className="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
          >
            <Plus className="h-4 w-4 mr-2" />
            Novi Klijent
          </button>
        </div>
      </div>

      <div className="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        <div className="p-4 border-b border-gray-200 bg-gray-50 flex items-center">
          <div className="relative rounded-md shadow-sm max-w-xs w-full">
            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
              <Search className="h-4 w-4 text-gray-400" aria-hidden="true" />
            </div>
            <input
              type="text"
              className="block w-full rounded-md border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2"
              placeholder="Pretraži klijente..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </div>
        
        <ul role="list" className="divide-y divide-gray-200">
          {filteredClients.length > 0 ? (
            filteredClients.map((client) => (
              <li key={client.id} className="hover:bg-gray-50 transition-colors">
                <div className="px-4 py-4 sm:px-6 flex items-center justify-between">
                  <div className="flex items-center min-w-0 gap-4">
                     <div className="h-10 w-10 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                        {client.name.substring(0, 2).toUpperCase()}
                     </div>
                     <div className="min-w-0">
                        <p className="text-sm font-medium text-indigo-600 truncate">{client.name}</p>
                        <p className="flex items-center text-sm text-gray-500">
                           <span className="truncate">{client.email}</span>
                           <span className="mx-2 text-gray-300">&bull;</span>
                           <span className="truncate">{client.tax_id || 'Nema PIB'}</span>
                        </p>
                     </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <button 
                      onClick={() => navigate(`/dashboard/clients/${client.id}`)}
                      className="p-2 text-gray-400 hover:text-indigo-600 rounded-full hover:bg-indigo-50 transition-colors"
                    >
                      <Edit className="h-4 w-4" />
                    </button>
                     <button 
                      onClick={() => handleDelete(client.id)}
                      className="p-2 text-gray-400 hover:text-red-600 rounded-full hover:bg-red-50 transition-colors"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </li>
            ))
          ) : (
            <li className="px-4 py-12 text-center text-gray-500">
              Nema klijenata koji odgovaraju pretrazi.
            </li>
          )}
        </ul>
      </div>
    </div>
  );
}
