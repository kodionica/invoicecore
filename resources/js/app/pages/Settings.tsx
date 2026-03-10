import React, {useEffect} from 'react';
import {useForm} from 'react-hook-form';
import {useApp} from '../context/AppContext';
import {toast} from 'sonner';
import {User, Lock, Globe, Save} from 'lucide-react';

type PreferencesFormValues = {
    language: string;
    notifications: {
        invoices: boolean;
        clients: boolean;
    };
};

const defaultPreferences: PreferencesFormValues = {
    language: 'sr-Latn',
    notifications: {
        invoices: true,
        clients: false,
    },
};

export default function Settings() {
    const {user, updateUser, resetPassword, appSettings, appSettingsLoading, saveAppSettings} = useApp();

    const {register: registerProfile, handleSubmit: handleProfileSubmit} = useForm({
        defaultValues: {
            first_name: user?.first_name || '',
            last_name: user?.last_name || '',
            name: user?.name || '',
            email: user?.email || '',
            username: user?.username || '',
            phone: user?.phone || '',
        }
    });

    const {register: registerSecurity, handleSubmit: handleSecuritySubmit, reset: resetSecurity} = useForm();
    const {
        register: registerPreferences,
        handleSubmit: handlePreferencesSubmit,
        reset: resetPreferences,
    } = useForm<PreferencesFormValues>({
        defaultValues: defaultPreferences,
    });

    useEffect(() => {
        if (appSettings) {
            resetPreferences(appSettings);
        }
    }, [appSettings, resetPreferences]);

    const onProfileSubmit = (data: any) => {
        updateUser(data)
            .then(() => {
                toast.success('Profil uspešno ažuriran');
            });
    };

    const onSecuritySubmit = async (data: any) => {
        if (data.password !== data.password_confirmation) {
            toast.error('Lozinke se ne podudaraju');

            return;
        }

        try {
            await resetPassword(data);
            resetSecurity();
        } catch {
            // resetPassword already shows a toast with the error message.
        }
    };

    const onPreferencesSubmit = async (data: PreferencesFormValues) => {
        try {
            await saveAppSettings(data);
        } catch {
            // saveAppSettings already shows a toast with the error message.
        }
    };

    return (
        <div className="max-w-4xl mx-auto space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Podešavanja</h1>
                <p className="mt-2 text-sm text-gray-700">Upravljajte svojim profilom i preferencijama aplikacije.</p>
            </div>

            {/* Profile Settings */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <div className="p-6 border-b border-gray-200">
                    <div className="flex items-center gap-4">
                        <div className="p-2 bg-indigo-50 rounded-lg">
                            <User className="h-6 w-6 text-indigo-600"/>
                        </div>
                        <div>
                            <h2 className="text-lg font-medium text-gray-900">Osnovne Informacije</h2>
                            <p className="text-sm text-gray-500">Ažurirajte svoje lične podatke.</p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleProfileSubmit(onProfileSubmit)} className="p-6 space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Ime</label>
                            <input
                                type="text"
                                {...registerProfile("first_name", {required: true})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Prezime</label>
                            <input
                                type="text"
                                {...registerProfile("last_name", {required: true})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Korisničko ime</label>
                            <input
                                type="text"
                                {...registerProfile("username", {required: true})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Telefon</label>
                            <input
                                type="text"
                                {...registerProfile("phone", {required: true})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Email Adresa</label>
                            <input
                                readOnly={true}
                                type="email"
                                {...registerProfile("email", {required: true})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end pt-4">
                        <button
                            type="submit"
                            className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            <Save className="h-4 w-4 mr-2"/>
                            Sačuvaj Promene
                        </button>
                    </div>
                </form>
            </div>

            {/* Security Settings */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <div className="p-6 border-b border-gray-200">
                    <div className="flex items-center gap-4">
                        <div className="p-2 bg-indigo-50 rounded-lg">
                            <Lock className="h-6 w-6 text-indigo-600"/>
                        </div>
                        <div>
                            <h2 className="text-lg font-medium text-gray-900">Sigurnost</h2>
                            <p className="text-sm text-gray-500">Promenite vašu lozinku.</p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSecuritySubmit(onSecuritySubmit)} className="p-6 space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Trenutna Lozinka</label>
                            <input
                                type="password"
                                {...registerSecurity("current_password", {required: true})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Nova Lozinka</label>
                            <input
                                type="password"
                                {...registerSecurity("password", {required: true})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Potvrdi Lozinku</label>
                            <input
                                type="password"
                                {...registerSecurity("password_confirmation", {required: true})}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3 border"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end pt-4">
                        <button
                            type="submit"
                            className="inline-flex justify-center rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Promeni Lozinku
                        </button>
                    </div>
                </form>
            </div>

            {/* Preferences */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <div className="p-6 border-b border-gray-200">
                    <div className="flex items-center gap-4">
                        <div className="p-2 bg-indigo-50 rounded-lg">
                            <Globe className="h-6 w-6 text-indigo-600"/>
                        </div>
                        <div>
                            <h2 className="text-lg font-medium text-gray-900">Jezik i Obaveštenja</h2>
                            <p className="text-sm text-gray-500">Podesite lokalizaciju i notifikacije.</p>
                        </div>
                    </div>
                </div>

                <form id="preferences-form" onSubmit={handlePreferencesSubmit(onPreferencesSubmit)} className="p-6 space-y-6">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Jezik Aplikacije</label>
                        <select
                            {...registerPreferences("language")}
                            disabled={appSettingsLoading}
                            className="mt-1 block w-full md:w-1/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 border disabled:bg-gray-100"
                        >
                            <option value="sr-Latn">Srpski (Latinica)</option>
                            <option value="en-US">English (US)</option>
                            <option value="de-DE">Deutsch</option>
                        </select>
                    </div>

                    <div className="border-t border-gray-100 pt-6">
                        <h3 className="text-sm font-medium text-gray-900 mb-4">Email Obaveštenja</h3>
                        <div className="space-y-4">
                            <div className="flex items-start">
                                <div className="flex items-center h-5">
                                    <input
                                        id="notif-invoices"
                                        type="checkbox"
                                        {...registerPreferences("notifications.invoices")}
                                        disabled={appSettingsLoading}
                                        className="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded disabled:bg-gray-100"
                                    />
                                </div>
                                <div className="ml-3 text-sm">
                                    <label htmlFor="notif-invoices" className="font-medium text-gray-700">Nove Fakture</label>
                                    <p className="text-gray-500">Obavesti me kada se kreira nova faktura.</p>
                                </div>
                            </div>
                            <div className="flex items-start">
                                <div className="flex items-center h-5">
                                    <input
                                        id="notif-clients"
                                        type="checkbox"
                                        {...registerPreferences("notifications.clients")}
                                        disabled={appSettingsLoading}
                                        className="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded disabled:bg-gray-100"
                                    />
                                </div>
                                <div className="ml-3 text-sm">
                                    <label htmlFor="notif-clients" className="font-medium text-gray-700">Novi Klijenti</label>
                                    <p className="text-gray-500">Obavesti me kada se doda novi klijent.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                    <button
                        type="submit"
                        form="preferences-form"
                        disabled={appSettingsLoading}
                        className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-70"
                    >
                        Sačuvaj Podešavanja
                    </button>
                </div>
            </div>

        </div>
    );
}
