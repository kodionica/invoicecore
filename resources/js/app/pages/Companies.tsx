import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { Plus, Building2, MapPin, FileText, Trash2, Edit } from 'lucide-react';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';

export default function Companies() {
  const { companies, setActiveCompany, activeCompanyId } = useApp();
  const navigate = useNavigate();

  return (
    <div className="space-y-6">
      <div className="sm:flex sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Moje Firme</h1>
          <p className="mt-2 text-sm text-gray-700">Upravljajte podacima vaših firmi.</p>
        </div>
        <div className="mt-4 sm:mt-0">
          <button
            onClick={() => navigate('/dashboard/companies/new')}
            className="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
          >
            <Plus className="h-4 w-4 mr-2" />
            Nova Firma
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {companies.map((company) => (
          <div
            key={company.id}
            className={`relative rounded-lg border bg-white px-6 py-5 shadow-sm flex flex-col space-y-4 hover:shadow-md transition-shadow cursor-pointer ${activeCompanyId === company.id ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-300'}`}
            onClick={() => navigate(`/dashboard/companies/${company.id}`)}
          >
            <div className="flex items-center justify-between">
               <div className="flex-shrink-0 h-10 w-10 bg-indigo-50 rounded-lg flex items-center justify-center">
                  <Building2 className="h-6 w-6 text-indigo-600" />
               </div>
               {activeCompanyId === company.id && (
                 <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                   Aktivna
                 </span>
               )}
            </div>

            <div className="flex-1">
               <h3 className="text-lg font-medium text-gray-900">{company.name}</h3>
                <div className="mt-2 flex items-center text-sm text-gray-500">
                    <FileText className="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" />
                    <p>PIB: {company.tax_id}</p>
                </div>
                <div className="mt-2 flex items-center text-sm text-gray-500">
                    <FileText className="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" />
                    <p>MB: {company.registration_number}</p>
                </div>
               <div className="mt-1 flex items-center text-sm text-gray-500">
                  <MapPin className="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" />
                  <p>{company.address}, {company.city}</p>
               </div>
            </div>

            <div className="border-t border-gray-100 pt-4 flex justify-between items-center">
               <button
                 onClick={(e) => {
                   e.stopPropagation();
                   setActiveCompany(company.id);
                 }}
                 className={`text-sm font-medium ${activeCompanyId === company.id ? 'text-gray-400 cursor-default' : 'text-indigo-600 hover:text-indigo-500'}`}
                 disabled={activeCompanyId === company.id}
               >
                 {activeCompanyId === company.id ? 'Trenutno izabrana' : 'Postavi kao aktivnu'}
               </button>
               <div className="flex gap-2">
                 <button
                   onClick={(e) => {
                     e.stopPropagation();
                     navigate(`/dashboard/companies/${company.id}`);
                   }}
                   className="text-gray-400 hover:text-gray-600"
                 >
                    <Edit className="h-4 w-4" />
                 </button>
               </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
