import React, {createContext, useContext, useEffect, useState} from 'react';
import axios from 'axios';
import {toast} from 'sonner';
import {createAvatar} from "@dicebear/core";
import {glass} from "@dicebear/collection";
import {id} from "date-fns/locale";

export interface Company {
    id: number;
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
    payment_due_days?: number;
    logoUrl?: string;
}

export interface Client {
    id: number;
    companyId: number;
    name: string;
    email: string;
    address: string;
    tax_id?: string;
    registration_number?: string;
    phone?: string;
    city?: string;
    country?: string;
    createdAt?: string;
    client_type: string;
}

export interface InvoiceItem {
    id: number;
    description: string;
    quantity: number;
    price: number;
}

export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';

export interface Invoice {
    id: number;
    companyId: number;
    clientId: number;
    number: string;
    date: string;
    dueDate: string;
    currency: string;
    items: InvoiceItem[];
    status: InvoiceStatus;
    paymentMethod: string;
    total: number;
    totalOriginal: number;
    totalRsd: number;
    fxRateToRsd?: number;
    includeInKpo: boolean;
}

export interface KpoEntry {
    id: number;
    companyId: number;
    sourceInvoiceId?: number | null;
    year: number;
    entryNumber: number;
    entryDate: string;
    sourceType: 'manual' | 'invoice' | string;
    incomeType?: string | null;
    currency: string;
    amountOriginal: number;
    amountRsd: number;
    adjustmentRsd: number;
    totalRsd: number;
    description?: string | null;
    sourceInvoice?: {
        id: number;
        invoiceNumber?: string;
    } | null;
}

export interface KpoTotals {
    amountRsd: number;
    adjustmentRsd: number;
    totalRsd: number;
}

export interface KpoFilters {
    year?: number;
    from?: string;
    to?: string;
}

export interface KpoListResponse {
    entries: KpoEntry[];
    totals: KpoTotals;
}

export interface KpoUpdatePayload {
    entry_date?: string;
    income_type?: string | null;
    adjustment_rsd?: string | number;
    description?: string | null;
}

export interface KpoGenerationCandidate {
    id: number;
    invoice_number: string;
    issue_date: string;
    due_date: string;
    suggested_entry_date: string;
    currency: string;
    total_original: string | number;
    total_rsd: string | number;
    status: string;
}

export interface KpoGenerationPreview {
    year: number;
    includeHistorical: boolean;
    dateRule: 'manual_entry_date_required' | string;
    entries: KpoGenerationCandidate[];
}

export interface KpoGenerationSelection {
    invoice_id: number;
    entry_date: string;
}

export interface KpoGenerationResult {
    created: Array<{invoice_id: number; kpo_entry_id: number; entry_number: number}>;
    skipped: Array<{invoice_id: number; reason: string}>;
    failed: Array<{invoice_id: number; reason: string}>;
}

interface User {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    username: string;
    phone: string;
    email: string;
    avatarUrl?: string;
    activeCompanyId?: number | null;
}

interface LoginPayload {
    login: string;
    password: string;
    remember?: boolean;
}

interface PasswordPayload {
    current_password: string;
    password: string;
    password_confirmation: string;
}

interface RegisterPayload {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
    phone?: string;
}

interface CompanyCreatePayload {
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
    logoFile?: File;
}

interface CompanyUpdatePayload extends Partial<Omit<CompanyCreatePayload, 'logoFile'>> {
    logoFile?: File;
    remove_logo?: boolean;
}

interface ClientCreatePayload {
    name: string;
    email?: string;
    address?: string;
    tax_id?: string;
    registration_number?: string;
    phone?: string;
    city?: string;
    country?: string;
    client_type?: string;
}

interface InvoiceCreatePayload {
    clientId: number;
    date: string;
    dueDate: string;
    number?: string;
    currency?: string;
    paymentMethod?: string;
    note?: string;
    items: { description: string; quantity: number; price: number }[];
    includeInKpo?: boolean;
}

export interface MetaData {
    countries: Record<string, string> | Array<{ code: string; name: string }>;
    currencies: Record<string, { name: string; symbol?: string }> | Array<{ code: string; name: string; symbol?: string }>;
    payment_methods: Record<string, string> | Array<{ key: string; label: string }>;
    invoice_statuses?: Array<{ key: string; label: string }>;
    client_types: Array<{ value: string; label: string, code: string }>;
}

export interface AppSettings {
    language: string;
    notifications: {
        invoices: boolean;
        clients: boolean;
    };
}

interface AppContextType {
    user: User | null;
    authLoading: boolean;
    activeCompanyId: number | null;
    companies: Company[];
    clients: Client[];
    invoices: Invoice[];
    meta: MetaData | null;
    metaLoading: boolean;
    appSettings: AppSettings | null;
    appSettingsLoading: boolean;
    login: (payload: LoginPayload) => Promise<void>;
    register: (payload: RegisterPayload) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
    updateUser: (updates: Partial<User>) => Promise<void>;
    resetPassword: (payload: PasswordPayload) => Promise<any>;
    loadAppSettings: () => Promise<void>;
    saveAppSettings: (payload: AppSettings) => Promise<void>;
    setActiveCompany: (id: number) => Promise<void>;
    addCompany: (company: CompanyCreatePayload) => Promise<void>;
    updateCompany: (id: number, data: CompanyUpdatePayload) => Promise<void>;
    deleteCompany: (id: number) => Promise<void>;
    addClient: (client: ClientCreatePayload) => Promise<void>;
    updateClient: (id: number, data: ClientCreatePayload) => Promise<void>;
    deleteClient: (id: number) => Promise<void>;
    addInvoice: (invoice: InvoiceCreatePayload) => Promise<void>;
    deleteInvoice: (id: number) => Promise<void>;
    getNextInvoiceNumber: () => Promise<string>;
    updateInvoiceStatus: (id: number, status: InvoiceStatus) => Promise<void>;
    downloadInvoicePdf: (id: number, invoiceNumber?: string, options?: { force?: boolean }) => Promise<void>;
    sendInvoiceEmail: (id: number) => Promise<void>;
    loadKpo: (filters?: KpoFilters) => Promise<KpoListResponse>;
    downloadKpoPdf: (filters?: KpoFilters) => Promise<void>;
    getKpoEntry: (id: number) => Promise<KpoEntry>;
    updateKpoEntry: (id: number, payload: KpoUpdatePayload) => Promise<KpoEntry>;
    previewKpoGeneration: (year: number, includeHistorical?: boolean) => Promise<KpoGenerationPreview>;
    generateKpoEntries: (year: number, entries: KpoGenerationSelection[]) => Promise<KpoGenerationResult>;
}

const AppContext = createContext<AppContextType | undefined>(undefined);

const api = axios.create({
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

const ensureCsrf = async () => {
    await api.get('/sanctum/csrf-cookie');
};

const normalizeUser = (apiUser: any): User => {
    const name =
        apiUser?.display_name ||
        [apiUser?.first_name, apiUser?.last_name].filter(Boolean).join(' ') ||
        apiUser?.username ||
        apiUser?.email ||
        'User';

    return {
        id: Number(apiUser?.id ?? 0),
        first_name: apiUser?.first_name,
        last_name: apiUser?.last_name,
        name,
        username: apiUser.username,
        phone: apiUser.phone,
        email: apiUser?.email ?? '',
        avatarUrl: apiUser?.avatar_url ?? apiUser?.avatarUrl ?? createAvatar(glass, {seed: apiUser.id}).toDataUri(),
        activeCompanyId: apiUser?.active_company_id ? Number(apiUser.active_company_id) : null,
    };
};

const normalizeCompany = (company: any): Company => {
    const rawLogoPath = company.logo_path ?? company.logoUrl ?? company.logo_url;
    const logoUrl = rawLogoPath
        ? rawLogoPath.startsWith('http')
            ? rawLogoPath
            : rawLogoPath.startsWith('/')
                ? `${window.location.origin}${rawLogoPath}`
                : `${window.location.origin}/storage/${rawLogoPath}`
        : undefined;

    return {
        id: Number(company.id),
        name: company.name ?? '',
        tax_id: company.tax_id ?? '',
        registration_number: company.registration_number ?? '',
        address: company.address ?? '',
        city: company.city ?? '',
        country: company.country ?? '',
        email: company.email ?? '',
        phone: company.phone ?? '',
        bank_account: company.bank_account ?? '',
        iban: company.iban ?? '',
        swift: company.swift ?? '',
        currency: company.currency ?? 'RSD',
        vat_enabled: Boolean(company.vat_enabled),
        payment_due_days: company.payment_due_days !== undefined ? Number(company.payment_due_days) : undefined,
        logoUrl,
    };
};

const normalizeClient = (client: any): Client => {
    return {
        id: Number(client.id),
        companyId: Number(client.company_id ?? 0),
        name: client.name ?? '',
        email: client.email ?? '',
        address: client.address ?? '',
        tax_id: client.tax_id ?? undefined,
        registration_number: client.registration_number ?? undefined,
        phone: client.phone ?? undefined,
        city: client.city ?? undefined,
        country: client.country ?? undefined,
        createdAt: client.created_at ?? client.createdAt ?? undefined,
        client_type: client.client_type
    };
};

const normalizeInvoice = (invoice: any): Invoice => {
    const items = Array.isArray(invoice.items) ? invoice.items : [];
    const total = Number(invoice.total ?? 0);
    const totalOriginal = Number(invoice.total_original ?? 0);
    const totalRsd = Number(invoice.total_rsd ?? 0);

    return {
        id: Number(invoice.id),
        companyId: Number(invoice.company_id ?? 0),
        clientId: Number(invoice.client_id ?? 0),
        number: invoice.invoice_number ?? '',
        date: invoice.issue_date ?? '',
        dueDate: invoice.due_date ?? '',
        currency: invoice.currency ?? 'RSD',
        status: invoice.status as InvoiceStatus,
        paymentMethod: invoice.payment_method,
        total,
        totalOriginal: totalOriginal > 0 ? totalOriginal : total,
        totalRsd: totalRsd > 0 ? totalRsd : total,
        fxRateToRsd: invoice.fx_rate_to_rsd !== undefined && invoice.fx_rate_to_rsd !== null
            ? Number(invoice.fx_rate_to_rsd)
            : undefined,
        includeInKpo: invoice.include_in_kpo !== false,
        items: items.map((item: any) => ({
            id: Number(item.id ?? 0),
            description: item.name ?? item.description ?? '',
            quantity: Number(item.quantity ?? 0),
            price: Number(item.price ?? 0),
        })),
    };
};

const normalizeKpoEntry = (entry: any): KpoEntry => ({
    id: Number(entry.id),
    companyId: Number(entry.company_id ?? 0),
    sourceInvoiceId: entry.source_invoice_id !== null && entry.source_invoice_id !== undefined
        ? Number(entry.source_invoice_id)
        : null,
    year: Number(entry.year ?? 0),
    entryNumber: Number(entry.entry_number ?? 0),
    entryDate: entry.entry_date ?? '',
    sourceType: entry.source_type ?? 'manual',
    incomeType: entry.income_type ?? null,
    currency: entry.currency ?? 'RSD',
    amountOriginal: Number(entry.amount_original ?? 0),
    amountRsd: Number(entry.amount_rsd ?? 0),
    adjustmentRsd: Number(entry.adjustment_rsd ?? 0),
    totalRsd: Number(entry.total_rsd ?? 0),
    description: entry.description ?? null,
    sourceInvoice: entry.source_invoice
        ? {
            id: Number(entry.source_invoice.id),
            invoiceNumber: entry.source_invoice.invoice_number,
        }
        : null,
});

const buildCompanyFormData = (data: CompanyCreatePayload | CompanyUpdatePayload) => {
    const form = new FormData();
    Object.entries(data).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        if (key === 'logoFile') return;
        if (key === 'vat_enabled') {
            form.append('vat_enabled', value ? '1' : '0');
            return;
        }
        if (key === 'remove_logo') {
            if (value) form.append('remove_logo', '1');
            return;
        }
        form.append(key, String(value));
    });

    const logoFile = (data as CompanyCreatePayload).logoFile || (data as CompanyUpdatePayload).logoFile;
    if (logoFile) {
        form.append('logo', logoFile);
    }

    return form;
};

export function AppProvider({children}: { children: React.ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [authLoading, setAuthLoading] = useState(true);
    const [companies, setCompanies] = useState<Company[]>([]);
    const [clients, setClients] = useState<Client[]>([]);
    const [invoices, setInvoices] = useState<Invoice[]>([]);
    const [activeCompanyId, setActiveCompanyId] = useState<number | null>(null);
    const [meta, setMeta] = useState<MetaData | null>(null);
    const [metaLoading, setMetaLoading] = useState(true);
    const [appSettings, setAppSettings] = useState<AppSettings | null>(null);
    const [appSettingsLoading, setAppSettingsLoading] = useState(false);

    const refreshUser = async () => {
        setAuthLoading(true);
        try {
            const response = await api.get('/api/user');
            const nextUser = normalizeUser(response.data);
            setUser(nextUser);
            setActiveCompanyId(nextUser.activeCompanyId ?? null);
        } catch (error: any) {
            if (error?.response?.status !== 401) {
                throw error;
            }
            setUser(null);
            setActiveCompanyId(null);
        } finally {
            setAuthLoading(false);
        }
    };

    const loadCompanies = async () => {
        const response = await api.get('/api/companies');
        const data = Array.isArray(response.data) ? response.data : [];
        setCompanies(data.map(normalizeCompany));
    };

    const loadClients = async () => {
        try {
            const response = await api.get('/api/clients');
            const data = Array.isArray(response.data) ? response.data : [];
            setClients(data.map(normalizeClient));
        } catch (error: any) {
            if (error?.response?.status === 422) {
                setClients([]);
                return;
            }
            throw error;
        }
    };

    const loadInvoices = async () => {
        try {
            const response = await api.get('/api/invoices');
            const data = Array.isArray(response.data) ? response.data : [];
            setInvoices(data.map(normalizeInvoice));
        } catch (error: any) {
            if (error?.response?.status === 422) {
                setInvoices([]);
                return;
            }
            throw error;
        }
    };

    const loadKpo = async (filters?: KpoFilters): Promise<KpoListResponse> => {
        const response = await api.get('/api/kpo', {params: filters});
        const entries = Array.isArray(response.data?.entries)
            ? response.data.entries.map(normalizeKpoEntry)
            : [];
        const totals = response.data?.totals ?? {};

        return {
            entries,
            totals: {
                amountRsd: Number(totals.amount_rsd ?? 0),
                adjustmentRsd: Number(totals.adjustment_rsd ?? 0),
                totalRsd: Number(totals.total_rsd ?? 0),
            },
        };
    };

    const downloadKpoPdf = async (filters?: KpoFilters): Promise<void> => {
        const response = await api.get('/api/kpo/pdf', {params: filters, responseType: 'blob'});
        const disposition = response.headers['content-disposition'] as string | undefined;
        const filename = disposition?.match(/filename="?([^";]+)"?/i)?.[1] ?? `kpo-evidencija-${filters?.year ?? 'svi-periodi'}.pdf`;
        const url = window.URL.createObjectURL(new Blob([response.data], {type: 'application/pdf'}));
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    };

    const getKpoEntry = async (id: number): Promise<KpoEntry> => {
        const response = await api.get(`/api/kpo/${id}`);
        return normalizeKpoEntry(response.data);
    };

    const updateKpoEntry = async (id: number, payload: KpoUpdatePayload): Promise<KpoEntry> => {
        const response = await api.put(`/api/kpo/${id}`, payload);
        return normalizeKpoEntry(response.data);
    };

    const previewKpoGeneration = async (year: number, includeHistorical = false): Promise<KpoGenerationPreview> => {
        const response = await api.get('/api/kpo/generation-preview', {
            params: {year, include_historical: includeHistorical ? 1 : 0},
        });

        return {
            year: Number(response.data?.year ?? year),
            includeHistorical: Boolean(response.data?.include_historical),
            dateRule: response.data?.date_rule ?? 'manual_entry_date_required',
            entries: Array.isArray(response.data?.entries) ? response.data.entries : [],
        };
    };

    const generateKpoEntries = async (year: number, entries: KpoGenerationSelection[]): Promise<KpoGenerationResult> => {
        const response = await api.post('/api/kpo/generate', {year, entries});
        return {
            created: response.data?.created ?? [],
            skipped: response.data?.skipped ?? [],
            failed: response.data?.failed ?? [],
        };
    };

    const loadAll = async () => {
        await Promise.all([loadCompanies(), loadClients(), loadInvoices()]);
    };

    const loadMeta = async () => {
        setMetaLoading(true);
        try {
            const response = await api.get('/api/meta');
            setMeta(response.data);
        } finally {
            setMetaLoading(false);
        }
    };

    const loadAppSettings = async () => {
        setAppSettingsLoading(true);
        try {
            const response = await api.get('/api/settings');
            setAppSettings(response.data);
        } catch (error: any) {
            if (error?.response?.status === 401) {
                setAppSettings(null);
                return;
            }
            throw error;
        } finally {
            setAppSettingsLoading(false);
        }
    };

    useEffect(() => {
        refreshUser().catch(() => undefined);
        loadMeta().catch(() => undefined);
    }, []);

    useEffect(() => {
        if (user) {
            loadAll().catch(() => undefined);
        } else {
            setCompanies([]);
            setClients([]);
            setInvoices([]);
        }
    }, [user]);

    useEffect(() => {
        if (user) {
            loadClients().catch(() => undefined);
            loadInvoices().catch(() => undefined);
        }
    }, [activeCompanyId, user]);

    useEffect(() => {
        if (user) {
            loadAppSettings().catch(() => undefined);
        } else {
            setAppSettings(null);
        }
    }, [user]);

    const login = async (payload: LoginPayload) => {
        await ensureCsrf();
        const response = await api.post('/api/login', payload);
        const nextUser = normalizeUser(response.data.user);
        setUser(nextUser);
        setActiveCompanyId(nextUser.activeCompanyId ?? null);
    };

    const register = async (payload: RegisterPayload) => {
        await ensureCsrf();
        const response = await api.post('/api/register', payload);
        const nextUser = normalizeUser(response.data.user);
        setUser(nextUser);
        setActiveCompanyId(nextUser.activeCompanyId ?? null);
    };

    const logout = async () => {
        await api.post('/api/logout');
        setUser(null);
        setActiveCompanyId(null);
        setCompanies([]);
        setClients([]);
        setInvoices([]);
        setAppSettings(null);
    };

    const updateUser = async (updates: Partial<User>) => {
        const response = await api.put('/api/profile', updates);
        const updatedUser = normalizeUser(response.data.user);

        setUser(current => (current ? {...current, ...updatedUser} : updatedUser));
    };

    const setActiveCompany = async (id: number) => {
        await api.post('/api/companies/switch', {company_id: id});
        setActiveCompanyId(id);
    };

    const addCompany = async (company: CompanyCreatePayload) => {
        const form = buildCompanyFormData(company);
        const response = await api.post('/api/companies', form, {
            headers: {'Content-Type': 'multipart/form-data'},
        });

        const created = normalizeCompany(response.data);
        setCompanies(current => [...current, created]);
        if (!activeCompanyId) {
            setActiveCompanyId(created.id);
        }
    };

    const updateCompany = async (id: number, data: CompanyUpdatePayload) => {
        const form = buildCompanyFormData(data);
        const response = await api.post(`/api/companies/${id}?_method=PUT`, form, {
            headers: {'Content-Type': 'multipart/form-data'},
        });

        const updated = normalizeCompany(response.data);
        setCompanies(current => current.map(c => (c.id === id ? updated : c)));
    };

    const deleteCompany = async (id: number) => {
        await api.delete(`/api/companies/${id}`);
        setCompanies(current => current.filter(c => c.id !== id));
        if (activeCompanyId === id) {
            setActiveCompanyId(null);
        }
    };

    const addClient = async (client: ClientCreatePayload) => {
        const response = await api.post('/api/clients', client);
        const created = normalizeClient(response.data);
        setClients(current => [...current, created]);
    };

    const updateClient = async (id: number, data: ClientCreatePayload) => {
        const response = await api.put(`/api/clients/${id}`, data);
        const updated = normalizeClient(response.data);
        setClients(current => current.map(c => (c.id === id ? updated : c)));
    };

    const deleteClient = async (id: number) => {
        await api.delete(`/api/clients/${id}`);
        setClients(current => current.filter(c => c.id !== id));
    };

    const addInvoice = async (invoice: InvoiceCreatePayload) => {
        const payload = {
            client_id: invoice.clientId,
            invoice_number: invoice.number,
            issue_date: invoice.date || undefined,
            due_date: invoice.dueDate || undefined,
            currency: invoice.currency,
            payment_method: invoice.paymentMethod,
            note: invoice.note,
            include_in_kpo: invoice.includeInKpo !== false,
            items: invoice.items.map(item => ({
                name: item.description,
                description: item.description,
                quantity: item.quantity,
                price: item.price,
            })),
        };

        const response = await api.post('/api/invoices', payload);
        const created = normalizeInvoice(response.data);
        setInvoices(current => [...current, created]);
    };

    const deleteInvoice = async (id: number) => {
        await api.delete(`/api/invoices/${id}`);
        setInvoices(current => current.filter(c => c.id !== id));
    };

    const getNextInvoiceNumber = async () => {
        const response = await api.get('/api/invoices/next-number');
        return response.data?.invoice_number ?? '';
    };

    const updateInvoiceStatus = async (id: number, status: InvoiceStatus) => {
        const response = await api.put(`/api/invoices/${id}`, {status});
        const updated = normalizeInvoice(response.data);
        setInvoices(current => current.map(inv => (inv.id === id ? updated : inv)));
    };

    const downloadInvoicePdf = async (id: number, invoiceNumber?: string, options?: { force?: boolean }) => {
        const response = await api.get(`/api/invoices/${id}/pdf`, {
            responseType: 'blob',
            params: options?.force ? {refresh: 1} : undefined,
        });
        const contentDisposition = response.headers?.['content-disposition'] as string | undefined;
        const match = contentDisposition?.match(/filename=\"?([^\";]+)\"?/i);
        const filename = match?.[1] || `faktura-${invoiceNumber ?? id}.pdf`;
        const blob = new Blob([response.data], {type: 'application/pdf'});
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    };

    const sendInvoiceEmail = async (id: number) => {
        await api.post(`/api/invoices/${id}/email`);
    };

    const resetPassword = async (payload: PasswordPayload) => {
        try {
            const response = await api.put('/api/password/', payload);
            const message = response.data?.message || 'Lozinka uspešno promenjena.';
            toast.success(message);
            return response.data;
        } catch (err: any) {
            const apiData = err?.response?.data;
            const fieldErrors = apiData?.errors ? Object.values(apiData.errors).flat() : [];
            const message =
                apiData?.message ||
                apiData?.error ||
                fieldErrors[0] ||
                'Došlo je do greške. Pokušaj ponovo.';
            toast.error(message);
            throw err;
        }
    };

    const saveAppSettings = async (payload: AppSettings) => {
        try {
            const response = await api.put('/api/settings', payload);
            const nextSettings = response.data?.settings ?? payload;
            setAppSettings(nextSettings);
            const message = response.data?.message || 'Podešavanja sačuvana.';
            toast.success(message);
        } catch (err: any) {
            const apiData = err?.response?.data;
            const fieldErrors = apiData?.errors ? Object.values(apiData.errors).flat() : [];
            const message =
                apiData?.message ||
                apiData?.error ||
                fieldErrors[0] ||
                'Došlo je do greške. Pokušaj ponovo.';
            toast.error(message);
            throw err;
        }
    };

    return (
        <AppContext.Provider value={{
            user,
            authLoading,
            activeCompanyId,
            companies,
            clients,
            invoices,
            meta,
            metaLoading,
            appSettings,
            appSettingsLoading,
            login,
            register,
            logout,
            refreshUser,
            updateUser,
            resetPassword,
            loadAppSettings,
            saveAppSettings,
            setActiveCompany,
            addCompany,
            updateCompany,
            deleteCompany,
            addClient,
            updateClient,
            deleteClient,
            addInvoice,
            deleteInvoice,
            getNextInvoiceNumber,
            updateInvoiceStatus,
            downloadInvoicePdf,
            sendInvoiceEmail,
            loadKpo,
            downloadKpoPdf,
            getKpoEntry,
            updateKpoEntry,
            previewKpoGeneration,
            generateKpoEntries,
        }}>
            {children}
        </AppContext.Provider>
    );
}

export function useApp() {
    const context = useContext(AppContext);
    if (context === undefined) {
        throw new Error('useApp must be used within an AppProvider');
    }
    return context;
}
