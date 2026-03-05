import React, {createContext, useContext, useEffect, useState} from 'react';
import axios from 'axios';
import {createAvatar} from "@dicebear/core";
import {glass} from "@dicebear/collection";

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
    total: number;
}

interface User {
    id: number;
    name: string;
    email: string;
    avatarUrl?: string;
    activeCompanyId?: number | null;
}

interface LoginPayload {
    login: string;
    password: string;
    remember?: boolean;
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
}

interface MetaData {
    countries: Record<string, string> | Array<{ code: string; name: string }>;
    currencies: Record<string, { name: string; symbol?: string }> | Array<{ code: string; name: string; symbol?: string }>;
    payment_methods: Record<string, string> | Array<{ key: string; label: string }>;
    invoice_statuses?: Array<{ key: string; label: string }>;
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
    login: (payload: LoginPayload) => Promise<void>;
    register: (payload: RegisterPayload) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
    updateUser: (updates: Partial<User>) => void;
    setActiveCompany: (id: number) => Promise<void>;
    addCompany: (company: CompanyCreatePayload) => Promise<void>;
    updateCompany: (id: number, data: CompanyUpdatePayload) => Promise<void>;
    deleteCompany: (id: number) => Promise<void>;
    addClient: (client: ClientCreatePayload) => Promise<void>;
    updateClient: (id: number, data: ClientCreatePayload) => Promise<void>;
    deleteClient: (id: number) => Promise<void>;
    addInvoice: (invoice: InvoiceCreatePayload) => Promise<void>;
    getNextInvoiceNumber: () => Promise<string>;
    updateInvoiceStatus: (id: number, status: InvoiceStatus) => Promise<void>;
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
        name,
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
    };
};

const normalizeInvoice = (invoice: any): Invoice => {
    const items = Array.isArray(invoice.items) ? invoice.items : [];
    return {
        id: Number(invoice.id),
        companyId: Number(invoice.company_id ?? 0),
        clientId: Number(invoice.client_id ?? 0),
        number: invoice.invoice_number ?? '',
        date: invoice.issue_date ?? '',
        dueDate: invoice.due_date ?? '',
        currency: invoice.currency ?? 'RSD',
        status: invoice.status as InvoiceStatus,
        total: Number(invoice.total ?? 0),
        items: items.map((item: any) => ({
            id: Number(item.id ?? 0),
            description: item.name ?? item.description ?? '',
            quantity: Number(item.quantity ?? 0),
            price: Number(item.price ?? 0),
        })),
    };
};

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
    };

    const updateUser = (updates: Partial<User>) => {
        setUser(current => (current ? {...current, ...updates} : current));
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
        const issueDate = new Date(invoice.date);
        const dueDate = new Date(invoice.dueDate);
        const msDiff = dueDate.getTime() - issueDate.getTime();
        const dueDays = Number.isNaN(msDiff) ? 0 : Math.max(0, Math.round(msDiff / 86400000));

        const payload = {
            client_id: invoice.clientId,
            invoice_number: invoice.number,
            due_date: dueDays,
            currency: invoice.currency,
            payment_method: invoice.paymentMethod,
            note: invoice.note,
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

    const getNextInvoiceNumber = async () => {
        const response = await api.get('/api/invoices/next-number');
        return response.data?.invoice_number ?? '';
    };

    const updateInvoiceStatus = async (id: number, status: InvoiceStatus) => {
        const response = await api.put(`/api/invoices/${id}`, {status});
        const updated = normalizeInvoice(response.data);
        setInvoices(current => current.map(inv => (inv.id === id ? updated : inv)));
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
            login,
            register,
            logout,
            refreshUser,
            updateUser,
            setActiveCompany,
            addCompany,
            updateCompany,
            deleteCompany,
            addClient,
            updateClient,
            deleteClient,
            addInvoice,
            getNextInvoiceNumber,
            updateInvoiceStatus,
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
