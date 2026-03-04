import React, {useState} from 'react';
import {useParams, useNavigate} from 'react-router';
import {useApp} from '../context/AppContext';
import {ArrowLeft, Building2, Save, Trash2, CheckCircle, Users, FileText, Calculator} from 'lucide-react';
import {toast} from 'sonner';

export default function CompanyDetails() {
    const {id} = useParams();
    const navigate = useNavigate();
    const {companies, clients, invoices, activeCompanyId, setActiveCompany, updateCompany, deleteCompany} = useApp();

    const company = companies.find(c => c.id === id);
    const [isEditing, setIsEditing] = useState(false);

    // Form state
    const [formData, setFormData] = useState({
        name: company?.name || '',
        tax_id: company?.tax_id || '',
        registration_number: company?.registration_number || '',
        address: company?.address || '',
        city: company?.city || '',
        country: company?.country || '',
        email: company?.email || '',
        phone: company?.phone || '',
        bank_account: company?.bank_account || '',
        iban: company?.iban || '',
        swift: company?.swift || '',
        currency: company?.currency || '',
        vat_enabled: company?.vat_enabled || false,
        logoFile: undefined as File | undefined,
        remove_logo: false,
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

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        const {logoFile, remove_logo, ...payload} = formData;
        await updateCompany(company.id, {
            ...payload,
            logoFile,
            remove_logo,
        });
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

    const handleSetActive = async () => {
        await setActiveCompany(company.id);
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
                        <ArrowLeft className="h-5 w-5"/>
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
                            <CheckCircle className="h-4 w-4 mr-2"/>
                            Postavi kao aktivnu
                        </button>
                    )}
                    <button
                        onClick={handleDelete}
                        className="inline-flex items-center px-4 py-2 border border-red-200 shadow-sm text-sm font-medium rounded-md text-red-600 bg-red-50 hover:bg-red-100"
                    >
                        <Trash2 className="h-4 w-4 mr-2"/>
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
                                <Users className="h-6 w-6 text-gray-400"/>
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
                                <FileText className="h-6 w-6 text-gray-400"/>
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
                                <Calculator className="h-6 w-6 text-gray-400"/>
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
                                        tax_id: company.tax_id,
                                        registration_number: company.registration_number,
                                        address: company.address,
                                        city: company.city,
                                        country: company.country,
                                        email: company.email,
                                        phone: company.phone,
                                        bank_account: company.bank_account,
                                        iban: company.iban,
                                        swift: company.swift,
                                        currency: company.currency,
                                        vat_enabled: company.vat_enabled,
                                        logoFile: undefined,
                                        remove_logo: false,
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
                                            tax_id: company.tax_id,
                                            registration_number: company.registration_number,
                                            address: company.address,
                                            city: company.city,
                                            country: company.country,
                                            email: company.email,
                                            phone: company.phone,
                                            bank_account: company.bank_account,
                                            iban: company.iban,
                                            swift: company.swift,
                                            currency: company.currency,
                                            vat_enabled: company.vat_enabled,
                                            logoFile: undefined,
                                            remove_logo: false,
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
                            <div className="h-16 w-16 bg-indigo-50 rounded-lg flex items-center justify-center overflow-hidden">
                                {company.logoUrl ? (
                                    <img src={company.logoUrl} alt={company.name} className="h-full w-full object-cover"/>
                                ) : (
                                    <Building2 className="h-8 w-8 text-indigo-600"/>
                                )}
                            </div>
                            {isEditing && (
                                <div className="text-sm text-gray-500">
                                    <p>Učitaj logo (PNG, JPG, SVG) ili ukloni postojeći.</p>
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
                                        onChange={(e) => setFormData({...formData, name: e.target.value})}
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
                            <label htmlFor="tax_id" className="block text-sm font-medium text-gray-700">
                                PIB
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="tax_id"
                                        value={formData.tax_id}
                                        onChange={(e) => setFormData({...formData, tax_id: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                        required
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.tax_id}</p>
                                )}
                            </div>
                        </div>

                        {/* Registration Number */}
                        <div>
                            <label htmlFor="registration_number" className="block text-sm font-medium text-gray-700">
                                Matični broj
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="registration_number"
                                        value={formData.registration_number}
                                        onChange={(e) => setFormData({...formData, registration_number: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                        required
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.registration_number}</p>
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
                                        onChange={(e) => setFormData({...formData, address: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                        required
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.address}</p>
                                )}
                            </div>
                        </div>

                        {/* City & Country */}
                        <div>
                            <label htmlFor="city" className="block text-sm font-medium text-gray-700">
                                Grad
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="city"
                                        value={formData.city}
                                        onChange={(e) => setFormData({...formData, city: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                        required
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.city}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="country" className="block text-sm font-medium text-gray-700">
                                Država
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="country"
                                        value={formData.country}
                                        onChange={(e) => setFormData({...formData, country: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                        required
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.country}</p>
                                )}
                            </div>
                        </div>

                        {/* Contact */}
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                                Email
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="email"
                                        id="email"
                                        value={formData.email}
                                        onChange={(e) => setFormData({...formData, email: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                        required
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.email}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="phone" className="block text-sm font-medium text-gray-700">
                                Telefon
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="phone"
                                        value={formData.phone}
                                        onChange={(e) => setFormData({...formData, phone: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.phone}</p>
                                )}
                            </div>
                        </div>

                        {/* Bank */}
                        <div>
                            <label htmlFor="bank_account" className="block text-sm font-medium text-gray-700">
                                Broj računa
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="bank_account"
                                        value={formData.bank_account}
                                        onChange={(e) => setFormData({...formData, bank_account: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                        required
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.bank_account}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="iban" className="block text-sm font-medium text-gray-700">
                                IBAN
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="iban"
                                        value={formData.iban}
                                        onChange={(e) => setFormData({...formData, iban: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.iban}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="swift" className="block text-sm font-medium text-gray-700">
                                SWIFT
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="swift"
                                        value={formData.swift}
                                        onChange={(e) => setFormData({...formData, swift: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.swift}</p>
                                )}
                            </div>
                        </div>

                        {/* Billing */}
                        <div>
                            <label htmlFor="currency" className="block text-sm font-medium text-gray-700">
                                Valuta
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="text"
                                        id="currency"
                                        value={formData.currency}
                                        onChange={(e) => setFormData({...formData, currency: e.target.value})}
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                        required
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.currency}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="vat_enabled" className="block text-sm font-medium text-gray-700">
                                PDV obveznik
                            </label>
                            <div className="mt-1">
                                {isEditing ? (
                                    <input
                                        type="checkbox"
                                        id="vat_enabled"
                                        checked={formData.vat_enabled}
                                        onChange={(e) => setFormData({...formData, vat_enabled: e.target.checked})}
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                ) : (
                                    <p className="text-sm text-gray-900 py-2">{company.vat_enabled ? 'Da' : 'Ne'}</p>
                                )}
                            </div>
                        </div>

                        {isEditing && (
                            <div className="space-y-4">
                                <div>
                                    <label htmlFor="logoFile" className="block text-sm font-medium text-gray-700">
                                        Logo (PNG, JPG, SVG)
                                    </label>
                                    <div className="mt-1">
                                        <input
                                            type="file"
                                            id="logoFile"
                                            accept="image/*,.svg"
                                            onChange={(e) => setFormData({
                                                ...formData,
                                                logoFile: e.target.files?.item(0) || undefined,
                                                remove_logo: false,
                                            })}
                                            className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100"
                                        />
                                    </div>
                                </div>

                                {company.logoUrl && (
                                    <label className="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <input
                                            type="checkbox"
                                            checked={formData.remove_logo}
                                            onChange={(e) => setFormData({
                                                ...formData,
                                                remove_logo: e.target.checked,
                                                logoFile: e.target.checked ? undefined : formData.logoFile,
                                            })}
                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        Ukloni postojeći logo
                                    </label>
                                )}
                            </div>
                        )}
                    </div>

                    {isEditing && (
                        <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                            <button
                                type="submit"
                                className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <Save className="h-4 w-4 mr-2"/>
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
