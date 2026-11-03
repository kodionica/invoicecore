import React, {useEffect, useState} from 'react';
import {useForm} from 'react-hook-form';
import {useNavigate, useParams} from 'react-router';
import {type Client, type ClientCreatePayload, useApp} from '../context/AppContext';
import {toast} from 'sonner';
import {ArrowLeft} from 'lucide-react';
import {LoadingButton} from '../components/Loading';

type Field = keyof ClientCreatePayload;
type Form = Record<Field, string>;

const defaults: Form = {
    name: '', client_type: 'company', country_code: 'RS', tax_id: '', registration_number: '',
    address: '', city: '', country: '', postal_code: '', email: '', phone: '',
    contact_person: '', default_currency: '', preferred_language: '',
};

const inputClass = 'mt-1 block w-full rounded-md border border-gray-300 py-2 px-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const countryNames = new Intl.DisplayNames(['sr-Latn'], {type: 'region'});

export default function ClientForm() {
    const {id} = useParams();
    const navigate = useNavigate();
    const {clients, getClient, addClient, updateClient, activeCompanyId, meta} = useApp();
    const [loadedClient, setLoadedClient] = useState<Client | null>(null);
    const [saving, setSaving] = useState(false);
    const client = clients.find(item => item.id === Number(id) && item.companyId === activeCompanyId)
        ?? (loadedClient?.id === Number(id) && loadedClient.companyId === activeCompanyId ? loadedClient : null);
    const {register, handleSubmit, watch, reset, setError, setValue, formState: {errors, dirtyFields}} = useForm<Form>({defaultValues: defaults});
    const country = watch('country_code');
    const type = watch('client_type');
    const [clearIdentifiers, setClearIdentifiers] = useState(false);

    useEffect(() => {
        setClearIdentifiers(false);
        if (!id) {
            reset(defaults);
        } else if (client) {
            const values = {...defaults};
            for (const field of Object.keys(defaults) as Field[]) {
                values[field] = client[field] ?? '';
            }
            reset(values);
        }
    }, [id, client, reset]);

    useEffect(() => {
        if (!id || client) return;
        let cancelled = false;
        getClient(Number(id)).then(result => {
            if (!cancelled) setLoadedClient(result);
        }).catch(() => {
            if (!cancelled) {
                toast.error('Nije moguće učitati klijenta.');
                navigate('/dashboard/clients');
            }
        });
        return () => { cancelled = true; };
    }, [id, client, activeCompanyId, navigate]);

    const save = async (values: Form) => {
        setSaving(true);
        try {
            if (id) {
                const changes: Partial<ClientCreatePayload> = {};
                for (const field of Object.keys(dirtyFields) as Field[]) {
                    changes[field] = values[field];
                }
                if (type === 'individual' && !clearIdentifiers) {
                    delete changes.tax_id;
                    delete changes.registration_number;
                }
                await updateClient(Number(id), changes);
            } else {
                await addClient({
                    ...values,
                    tax_id: type === 'individual' ? '' : values.tax_id,
                    registration_number: type === 'individual' ? '' : values.registration_number,
                });
            }
            toast.success(id ? 'Klijent uspešno izmenjen' : 'Novi klijent uspešno dodat');
            navigate('/dashboard/clients');
        } catch (error: any) {
            const response = error?.response;
            if (response?.status === 422) {
                for (const [field, messages] of Object.entries(response.data.errors ?? {})) {
                    if (Object.hasOwn(defaults, field) && Array.isArray(messages)) {
                        setError(field as Field, {type: 'server', message: messages[0]});
                    }
                }
            }
            toast.error(response?.data?.message ?? 'Čuvanje klijenta nije uspelo.');
        } finally {
            setSaving(false);
        }
    };

    const fieldError = (field: Field) => errors[field]?.message && <p className="mt-1 text-sm text-red-600">{errors[field]?.message}</p>;
    const input = (field: Field, label: string, inputType = 'text') => (
        <div>
            <label htmlFor={field} className="text-sm font-medium text-gray-700">{label}</label>
            <input id={field} type={inputType} className={inputClass} {...register(field, {required: field === 'name' && 'Naziv je obavezan'})}/>
            {fieldError(field)}
        </div>
    );

    const select = (field: Field, label: string, options: Array<[string, string]>) => (
        <div>
            <label htmlFor={field} className="text-sm font-medium text-gray-700">{label}</label>
            <select id={field} className={inputClass} {...register(field)}>
                {options.map(([value, text]) => <option key={value} value={value}>{text}</option>)}
            </select>
            {fieldError(field)}
        </div>
    );

    const countries: Array<[string, string]> = (meta?.client_countries ?? ['RS'])
        .map((code): [string, string] => [code, countryNames.of(code) ?? code])
        .sort((a, b) => a[1].localeCompare(b[1], 'sr-Latn'));
    const types: Array<[string, string]> = meta?.client_types?.map(option => [option.value, option.label])
        ?? [['company', 'Pravno lice'], ['entrepreneur', 'Preduzetnik'], ['individual', 'Fizičko lice']];
    const currencies: Array<[string, string]> = Array.isArray(meta?.currencies)
        ? meta.currencies.map(currency => [currency.code, currency.name])
        : Object.entries(meta?.currencies ?? {}).map(([code, currency]) => [code, currency.name]);

    if (id && !client) return <p>Učitavanje klijenta...</p>;

    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <div className="flex items-center gap-4">
                <button type="button" aria-label="Nazad na klijente" onClick={() => navigate('/dashboard/clients')}><ArrowLeft/></button>
                <h1 className="text-2xl font-bold text-gray-900">{id ? 'Izmena klijenta' : 'Novi klijent'}</h1>
            </div>
            <form onSubmit={handleSubmit(save)} className="space-y-6 rounded-lg bg-white p-6 shadow">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {select('country_code', 'Država', [['', client?.country ? `Nerazrešeno (${client.country})` : 'Izaberite državu'], ...countries])}
                    {select('client_type', 'Tip klijenta', types)}
                    {input('name', type === 'individual' ? 'Ime i prezime' : 'Naziv')}
                    {input('email', 'Email', 'email')}
                    {type !== 'individual' && input('tax_id', country === 'RS' ? 'PIB' : 'Tax ID / TIN')}
                    {type !== 'individual' && input('registration_number', country === 'RS' ? 'Matični broj' : 'Registration number')}
                    {type === 'individual' && client && (client.tax_id || client.registration_number) && (
                        <div className="text-sm text-gray-600">
                            <button type="button" disabled={clearIdentifiers} className="text-indigo-700 underline disabled:no-underline" onClick={() => {
                                if (window.confirm('Ukloniti sačuvane poslovne identifikatore pri čuvanju klijenta?')) {
                                    setClearIdentifiers(true);
                                    setValue('tax_id', '', {shouldDirty: true});
                                    setValue('registration_number', '', {shouldDirty: true});
                                }
                            }}>
                                {clearIdentifiers ? 'Identifikatori će biti uklonjeni pri čuvanju' : 'Ukloni sačuvane poslovne identifikatore'}
                            </button>
                            {fieldError('tax_id')}
                            {fieldError('registration_number')}
                        </div>
                    )}
                    {input('address', 'Adresa')}
                    {input('city', 'Grad')}
                    {input('postal_code', 'Poštanski broj')}
                    {input('phone', 'Telefon')}
                    {input('contact_person', 'Kontakt osoba')}
                    {select('default_currency', 'Podrazumevana valuta', [['', 'Podrazumevana valuta firme'], ...currencies])}
                    {select('preferred_language', 'Jezik fakture', [['', 'Podrazumevani jezik'], ...Object.entries(meta?.invoice_languages ?? {'sr-Latn': 'Srpski (latinica)'})])}
                </div>
                <div className="flex justify-end gap-3 border-t border-gray-200 pt-5">
                    <button type="button" onClick={() => navigate('/dashboard/clients')}>Otkaži</button>
                    <LoadingButton type="submit" loading={saving} loadingLabel="Čuvanje..." className="rounded-md bg-indigo-600 px-4 py-2 text-white">Sačuvaj</LoadingButton>
                </div>
            </form>
        </div>
    );
}
