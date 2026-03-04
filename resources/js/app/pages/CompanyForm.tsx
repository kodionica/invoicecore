import React from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router';
import { useApp } from '../context/AppContext';
import { toast } from 'sonner';
import { ArrowLeft } from 'lucide-react';

interface CompanyFormData {
    name: string;
    tax_id: string;
    registration_number: string;
    address: string;
    city: string;
    country: string;
    email: string;
    phone: string;
    bank_account: string;
    iban: string;
    swift: string;
    currency: string;
    vat_enabled: boolean;
    logoFile?: FileList;
}

export default function CompanyForm() {
    const navigate = useNavigate();
    const { addCompany } = useApp();

    const { register, handleSubmit, formState: { errors } } = useForm<CompanyFormData>({
        defaultValues: {
            currency: 'RSD',
            vat_enabled: true,
        },
    });

    const onSubmit = async (data: CompanyFormData) => {
        await addCompany({
            name: data.name,
            tax_id: data.tax_id,
            registration_number: data.registration_number,
            address: data.address,
            city: data.city,
            country: data.country,
            email: data.email,
            phone: data.phone,
            bank_account: data.bank_account,
            iban: data.iban,
            swift: data.swift,
            currency: data.currency,
            vat_enabled: data.vat_enabled,
            logoFile: data.logoFile?.item(0) || undefined,
        });

        toast.success('Nova firma uspešno dodata');
        navigate('/dashboard/companies');
    };

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            <div className="flex items-center space-x-4">
                <button
                    onClick={() => navigate('/dashboard/companies')}
                    className="p-2 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-900 transition-colors"
                >
                    <ArrowLeft className="h-5 w-5" />
                </button>
                <h1 className="text-2xl font-bold text-gray-900">Nova Firma</h1>
            </div>

            <div className="bg-white shadow rounded-lg overflow-hidden">
                <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-6">
                    <div className="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div className="sm:col-span-6">
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                                Naziv Firme
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="name"
                                    {...register('name', {required: 'Naziv je obavezan'})}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="tax_id" className="block text-sm font-medium text-gray-700">
                                PIB
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="tax_id"
                                    {...register('tax_id', { required: 'PIB je obavezan' })}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.tax_id && <p className="mt-1 text-sm text-red-600">{errors.tax_id.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="registration_number" className="block text-sm font-medium text-gray-700">
                                Matični broj
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="registration_number"
                                    {...register('registration_number', { required: 'Matični broj je obavezan' })}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.registration_number && <p className="mt-1 text-sm text-red-600">{errors.registration_number.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                                Email
                            </label>
                            <div className="mt-1">
                                <input
                                    type="email"
                                    id="email"
                                    {...register('email', { required: 'Email je obavezan' })}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="phone" className="block text-sm font-medium text-gray-700">
                                Telefon
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="phone"
                                    {...register('phone')}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                            </div>
                        </div>

                        <div className="sm:col-span-6">
                            <label htmlFor="address" className="block text-sm font-medium text-gray-700">
                                Adresa
                            </label>
                            <div className="mt-1">
                <textarea
                    id="address"
                    rows={3}
                    {...register('address', { required: 'Adresa je obavezna' })}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                />
                                {errors.address && <p className="mt-1 text-sm text-red-600">{errors.address.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="city" className="block text-sm font-medium text-gray-700">
                                Grad
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="city"
                                    {...register('city', { required: 'Grad je obavezan' })}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.city && <p className="mt-1 text-sm text-red-600">{errors.city.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="country" className="block text-sm font-medium text-gray-700">
                                Država
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="country"
                                    {...register('country', { required: 'Država je obavezna' })}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.country && <p className="mt-1 text-sm text-red-600">{errors.country.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="bank_account" className="block text-sm font-medium text-gray-700">
                                Broj računa
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="bank_account"
                                    {...register('bank_account', { required: 'Broj računa je obavezan' })}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.bank_account && <p className="mt-1 text-sm text-red-600">{errors.bank_account.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="iban" className="block text-sm font-medium text-gray-700">
                                IBAN
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="iban"
                                    {...register('iban')}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="swift" className="block text-sm font-medium text-gray-700">
                                SWIFT
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="swift"
                                    {...register('swift')}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="currency" className="block text-sm font-medium text-gray-700">
                                Valuta
                            </label>
                            <div className="mt-1">
                                <select
                                    id="currency"
                                    {...register('currency', { required: 'Valuta je obavezna' })}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                >
                                    <option value="RSD">RSD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                </select>
                                {errors.currency && <p className="mt-1 text-sm text-red-600">{errors.currency.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3 flex items-end">
                            <label className="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input
                                    type="checkbox"
                                    {...register('vat_enabled')}
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                PDV obveznik
                            </label>
                        </div>

                        <div className="sm:col-span-6">
                            <label htmlFor="logoFile" className="block text-sm font-medium text-gray-700">
                                Logo (PNG, JPG, SVG)
                            </label>
                            <div className="mt-1">
                                <input
                                    type="file"
                                    id="logoFile"
                                    accept="image/*,.svg"
                                    {...register('logoFile')}
                                    className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="pt-5 border-t border-gray-200 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => navigate('/dashboard/companies')}
                            className="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Otkaži
                        </button>
                        <button
                            type="submit"
                            className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Sačuvaj
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
