import React, {useEffect, useMemo, useState} from 'react';
import {useForm} from 'react-hook-form';
import {useNavigate, useParams} from 'react-router';
import {Client, useApp} from '../context/AppContext';
import {toast} from 'sonner';
import {ArrowLeft} from 'lucide-react';

interface ClientFormData {
    name: string;
    email: string;
    tax_id: string;
    registration_number: string;
    address: string;
    phone: string;
    city: string;
    country: string;
    client_type: string;
}


export default function ClientForm() {
    const {id} = useParams();
    const navigate = useNavigate();
    const {clients, addClient, updateClient, activeCompanyId, meta} = useApp();
    const isEdit = !!id;
    const [fallbackClient, setFallbackClient] = useState<Client | null>(null);
    const [isLoadingClient, setIsLoadingClient] = useState(false);

    const clientId = id ? Number(id) : null;
    const clientFromList = isEdit && clientId ? clients.find(c => c.id === clientId) : null;
    const client = clientFromList ?? fallbackClient;

    const toFormData = (item: Client): ClientFormData => {
        return {
            name: item.name ?? '',
            email: item.email ?? '',
            address: item.address ?? '',
            tax_id: item.tax_id ?? '',
            registration_number: item.registration_number ?? '',
            phone: item.phone ?? '',
            city: item.city ?? '',
            country: item.country ?? '',
            client_type: item.client_type ?? 'b2b',
        };
    };

    const defaultClientType = useMemo(
        () => meta?.client_types?.[0]?.value ?? 'b2b',
        [meta?.client_types]
    );

    const {register, handleSubmit, formState: {errors}, reset} = useForm<ClientFormData>({
        defaultValues: {
            name: '',
            email: '',
            address: '',
            tax_id: '',
            registration_number: '',
            phone: '',
            city: '',
            country: '',
            client_type: defaultClientType,
        }
    });

    useEffect(() => {
        if (isEdit && client) {
            reset(toFormData(client));
            return;
        }

        if (!isEdit) {
            reset({
                name: '',
                email: '',
                address: '',
                tax_id: '',
                registration_number: '',
                phone: '',
                city: '',
                country: '',
                client_type: defaultClientType,
            });
        }
    }, [isEdit, client, reset, defaultClientType]);

    useEffect(() => {
        if (!isEdit || !clientId || clientFromList || fallbackClient) {
            return;
        }

        let isCancelled = false;
        const loadClient = async () => {
            setIsLoadingClient(true);
            try {
                const response = await fetch(`/api/clients/${clientId}`);
                if (!response.ok) {
                    throw new Error('Nije moguće učitati klijenta.');
                }
                const apiClient = await response.json();
                if (isCancelled) return;
                setFallbackClient({
                    id: Number(apiClient.id),
                    companyId: Number(apiClient.company_id ?? 0),
                    name: apiClient.name ?? '',
                    email: apiClient.email ?? '',
                    address: apiClient.address ?? '',
                    tax_id: apiClient.tax_id ?? undefined,
                    registration_number: apiClient.registration_number ?? undefined,
                    phone: apiClient.phone ?? undefined,
                    city: apiClient.city ?? undefined,
                    country: apiClient.country ?? undefined,
                    createdAt: apiClient.created_at ?? undefined,
                    client_type: apiClient.client_type ?? 'b2b',
                });
            } catch (error: any) {
                if (!isCancelled) {
                    toast.error(error?.message ?? 'Nije moguće učitati klijenta.');
                    navigate('/dashboard/clients');
                }
            } finally {
                if (!isCancelled) {
                    setIsLoadingClient(false);
                }
            }
        };

        loadClient().catch(() => undefined);
        return () => {
            isCancelled = true;
        };
    }, [isEdit, clientId, clientFromList, fallbackClient, navigate]);

    const onSubmit = async (data: ClientFormData) => {
        const payload: ClientFormData = {
            ...data,
            client_type: data.client_type || defaultClientType,
        };

        if (isEdit) {
            if (!clientId) {
                return;
            }
            await updateClient(clientId, payload);
            toast.success('Klijent uspešno izmenjen');
        } else {
            if (!activeCompanyId) {
                toast.error('Molimo izaberite aktivnu firmu');
                return;
            }
            await addClient(payload);
            toast.success('Novi klijent uspešno dodat');
        }
        navigate('/dashboard/clients');
    };

    if (isEdit && isLoadingClient && !client) {
        return (
            <div className="max-w-2xl mx-auto">
                <div className="bg-white shadow rounded-lg p-6 text-gray-500">Učitavanje klijenta...</div>
            </div>
        );
    }

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            <div className="flex items-center space-x-4">
                <button
                    onClick={() => navigate('/dashboard/clients')}
                    className="p-2 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-900 transition-colors"
                >
                    <ArrowLeft className="h-5 w-5"/>
                </button>
                <h1 className="text-2xl font-bold text-gray-900">
                    {isEdit ? 'Izmena Klijenta' : 'Novi Klijent'}
                </h1>
            </div>

            <div className="bg-white shadow rounded-lg overflow-hidden">
                <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-6">
                    <div className="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">

                        <div className="sm:col-span-6">
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                                Naziv Klijenta / Ime
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="name"
                                    {...register("name", {required: "Naziv je obavezan"})}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                                Email Adresa
                            </label>
                            <div className="mt-1">
                                <input
                                    type="email"
                                    id="email"
                                    {...register("email", {
                                        required: "Email je obavezan",
                                        pattern: {
                                            value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                                            message: "Neispravna email adresa"
                                        }
                                    })}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>}
                            </div>
                        </div>

                        <div className="sm:col-span-3">
                            <label htmlFor="client_type" className="block text-sm font-medium text-gray-700">
                                Tip klijenta
                            </label>
                            <div className="mt-1">
                                <select id="client_type"
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                        {...register("client_type")}
                                >
                                    {meta?.client_types?.map(type => <option value={type.value} key={type.value}>{type.label}</option>)}
                                </select>
                                {errors.client_type && <p className="mt-1 text-sm text-red-600">{errors.client_type.message}</p>}
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
                                    {...register("tax_id")}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
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
                                    {...register("registration_number")}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
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
                                    {...register("phone")}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                            </div>
                        </div>

                        <div className="sm:col-span-6">
                            <label htmlFor="address" className="block text-sm font-medium text-gray-700">
                                Adresa
                            </label>
                            <div className="mt-1">
                                <input
                                    type="text"
                                    id="address"
                                    {...register("address")}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
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
                                    {...register("city")}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
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
                                    {...register("country")}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                                />
                            </div>
                        </div>

                    </div>

                    <div className="pt-5 border-t border-gray-200 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => navigate('/dashboard/clients')}
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
