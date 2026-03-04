import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useApp } from '../context/AppContext';
import { ArrowLeft, Building2, Save, Trash2, CheckCircle, Users, FileText, Calculator } from 'lucide-react';
import { toast } from 'sonner';

export default function CompanyDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { companies, clients, invoices, activeCompanyId, setActiveCompany, updateCompany, deleteCompany } = useApp();

  const company = companies.find(c => c.id === id);
  const [isEditing, setIsEditing] = useState(false);
  
  // Form state
  const [formData, setFormData] = useState({
    name: company?.name || '',
    pib: company?.pib || '',
    address: company?.address || '',
  });

  if (!company) {
    return (
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold text-gray-900">Firma nije pronađena</h2>
        <button 
          onClick={() => navigate('/dashboard/companies')}
          className="mt-4 text-indigo-600 hover:text-indigo-500"
        >
          Nazad na listu
        </button>
      </div>
    );
  }

  // Calculate statistics
  const companyClients = clients.filter(c => c.companyId === company.id);
  const companyInvoices = invoices.filter(inv => inv.companyId === company.id);
  const paidInvoices = companyInvoices.filter(inv => inv.status === 'paid');
  const totalRevenue = paidInvoices.reduce((sum, inv) => sum + inv.total, 0);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    updateCompany(company.id, formData);
    setIsEditing(false);
    toast.success('Podaci firme su ažurirani');
  };

  const handleDelete = () => {
    if (confirm('Da li ste sigurni da želite da obrišete ovu firmu? Svi povezani podaci će biti obrisani.')) {
      deleteCompany(company.id);
      toast.success('Firma je obrisana');
      navigate('/dashboard/companies');
    }
  };

  const handleSetActive = () => {
    setActiveCompany(company.id);
    toast.success(`${company.name} je postavljena kao aktivna firma`);
  };

  const isActive = activeCompanyId === company.id;

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <button 
            onClick={() => navigate('/dashboard/companies')}
            className="p-2 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-900 transition-colors"
          >
            <ArrowLeft className="h-5 w-5" />
          </button>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">{company.name}</h1>
            {isActive && (
              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 mt-1">
                Aktivna firma
              </span>
            )}
          </div>
        </div>

        <div className="flex gap-2">
          {!isActive && (
            <button 
              onClick={handleSetActive}
              className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
            >
              <CheckCircle className="h-4 w-4 mr-2" />
              Postavi kao aktivnu
            </button>
          )}
          <button 
            onClick={handleDelete}
            className="inline-flex items-center px-4 py-2 border border-red-200 shadow-sm text-sm font-medium rounded-md text-red-600 bg-red-50 hover:bg-red-100"
          >
            <Trash2 className="h-4 w-4 mr-2" />
            Obriši
          </button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div className="bg-white overflow-hidden shadow rounded-lg border border-gray-200">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <Users className="h-6 w-6 text-gray-400" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Broj Klijenata</dt>
                  <dd className="text-lg font-bold text-gray-900">{companyClients.length}</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg border border-gray-200">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FileText className="h-6 w-6 text-gray-400" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Broj Faktura</dt>
                  <dd className="text-lg font-bold text-gray-900">{companyInvoices.length}</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg border border-gray-200">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <Calculator className="h-6 w-6 text-gray-400" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Ukupan Prihod</dt>
                  <dd className="text-lg font-bold text-gray-900">€{totalRevenue.toLocaleString()}</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Company Details Form */}
      <div className="bg-white shadow-sm rounded-lg border border-gray-200">
        <div className="px-6 py-5 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-medium text-gray-900">Podaci o firmi</h2>
            {!isEditing ? (
              <button
                onClick={() => {
                  setIsEditing(true);
                  setFormData({
                    name: company.name,
                    pib: company.pib,
                    address: company.address,
                  });
                }}
                className="text-sm font-medium text-indigo-600 hover:text-indigo-500"
              >
                Izmeni
              </button>
            ) : (
              <div className="flex gap-2">
                <button
                  onClick={() => {
                    setIsEditing(false);
                    setFormData({
                      name: company.name,
                      pib: company.pib,
                      address: company.address,
                    });
                  }}
                  className="text-sm font-medium text-gray-600 hover:text-gray-500"
                >
                  Otkaži
                </button>
              </div>
            )}
          </div>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="px-6 py-6 space-y-6">
            {/* Company Icon */}
            <div className="flex items-center gap-4">
              <div className="h-16 w-16 bg-indigo-50 rounded-lg flex items-center justify-center">
                <Building2 className="h-8 w-8 text-indigo-600" />
              </div>
              {isEditing && (
                <div className="text-sm text-gray-500">
                  <p>Logo i branding opcije uskoro</p>
                </div>
              )}
            </div>

            {/* Name */}
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                Naziv Firme
              </label>
              <div className="mt-1">
                {isEditing ? (
                  <input
                    type="text"
                    id="name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                    required
                  />
                ) : (
                  <p className="text-sm text-gray-900 py-2">{company.name}</p>
                )}
              </div>
            </div>

            {/* PIB */}
            <div>
              <label htmlFor="pib" className="block text-sm font-medium text-gray-700">
                PIB
              </label>
              <div className="mt-1">
                {isEditing ? (
                  <input
                    type="text"
                    id="pib"
                    value={formData.pib}
                    onChange={(e) => setFormData({ ...formData, pib: e.target.value })}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                    required
                  />
                ) : (
                  <p className="text-sm text-gray-900 py-2">{company.pib}</p>
                )}
              </div>
            </div>

            {/* Address */}
            <div>
              <label htmlFor="address" className="block text-sm font-medium text-gray-700">
                Adresa
              </label>
              <div className="mt-1">
                {isEditing ? (
                  <textarea
                    id="address"
                    rows={3}
                    value={formData.address}
                    onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                    required
                  />
                ) : (
                  <p className="text-sm text-gray-900 py-2">{company.address}</p>
                )}
              </div>
            </div>
          </div>

          {isEditing && (
            <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
              <button
                type="submit"
                className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <Save className="h-4 w-4 mr-2" />
                Sačuvaj Izmene
              </button>
            </div>
          )}
        </form>
      </div>

      {/* Recent Activity Section */}
      <div className="bg-white shadow-sm rounded-lg border border-gray-200">
        <div className="px-6 py-5 border-b border-gray-200">
          <h2 className="text-lg font-medium text-gray-900">Nedavna Aktivnost</h2>
        </div>
        <div className="px-6 py-6">
          <div className="space-y-4">
            <div className="flex items-center justify-between text-sm">
              <span className="text-gray-600">Poslednja faktura</span>
              <span className="font-medium text-gray-900">
                {companyInvoices.length > 0 ? `#${companyInvoices[companyInvoices.length - 1].number}` : 'Nema faktura'}
              </span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-gray-600">Ukupno plaćenih faktura</span>
              <span className="font-medium text-gray-900">{paidInvoices.length}</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-gray-600">Status</span>
              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                Aktivna
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
