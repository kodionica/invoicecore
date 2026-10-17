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
    defaultTaxPercent?: number;
    vat_enabled: boolean;
    payment_due_days?: number;
    logoUrl?: string;
    otherSettings?: { invoice_pdf?: { max_unprotected_versions?: number } };
    bankAccounts?: CompanyBankAccount[];
}

export interface CompanyBankAccount {
    id: number;
    companyId: number;
    label?: string | null;
    bankName: string;
    accountNumber: string;
    iban?: string | null;
    swiftBic?: string | null;
    currency: string;
    accountType: 'domestic' | 'foreign';
    paymentInstructions?: string | null;
    isDefault: boolean;
    isActive: boolean;
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
    incomeCategory: InvoiceIncomeCategory | null;
}

export type InvoiceIncomeCategory = 'product' | 'service' | 'unclassified';

export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';

export interface Invoice {
    id: number;
    companyId: number;
    clientId: number;
    number: string;
    date: string;
    dueDate: string;
    isOverdue?: boolean;
    overdueDays?: number;
    currency: string;
    items: InvoiceItem[];
    status: InvoiceStatus;
    paidAt?: string | null;
    paymentNote?: string | null;
    paymentMethod: string;
    total: number;
    subtotalOriginal?: number;
    taxOriginal?: number;
    totalOriginal: number;
    totalRsd: number;
    fxRateToRsd?: number;
    includeInKpo: boolean;
    vatEnabledAtIssue?: boolean | null;
    note?: string | null;
    pdfPath?: string | null;
    bankAccountId?: number | null;
    bankAccountBankName?: string | null;
    bankAccountNumber?: string | null;
    bankAccountIban?: string | null;
    bankAccountSwiftBic?: string | null;
    bankAccountCurrency?: string | null;
    bankAccountPaymentInstructions?: string | null;
}

export interface InvoicePdfVersion {
    id: number;
    invoiceId: number | null;
    version: number;
    path: string;
    createdAt?: string;
    protectedAt?: string | null;
    sentAt?: string | null;
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
    incomeCategory?: 'product' | 'service' | 'unclassified' | string | null;
    currency: string;
    amountOriginal: number;
    amountRsd: number;
    adjustmentRsd: number;
    totalRsd: number;
    description?: string | null;
    deletedAt?: string | null;
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
    income_category?: 'product' | 'service' | 'unclassified' | null;
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
    manualOverlapWarning?: {count: number; year: number; requiresConfirmation: boolean} | null;
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

export interface KpoRegenerationEntry {
    id: number;
    entryNumber: number;
    entryDate: string;
    incomeCategory: string | null;
    incomeType?: string | null;
    amountOriginal: string | number;
    amountRsd: string | number;
    adjustmentRsd: string | number;
    totalRsd: string | number;
    origin?: string | null;
    manuallyEdited: boolean;
}

export interface KpoRegenerationProposal {
    incomeCategory: string;
    amountOriginal: string | number;
    amountRsd: string | number;
    totalRsd: string | number;
    existingEntryId?: number | null;
    action: 'create' | 'retain' | 'update' | string;
    manuallyEdited: boolean;
}

export interface KpoRegenerationInvoice {
    invoiceId: number;
    invoiceNumber: string;
    issueDate: string;
    status: 'ready' | 'partial' | 'blocked' | string;
    actionable: boolean;
    requiresAttention: boolean;
    blockingReasons: string[];
    existingEntries: KpoRegenerationEntry[];
    proposedEntries: KpoRegenerationProposal[];
    amountDifferences: Array<{
        incomeCategory: string;
        existingEntryId?: number | null;
        existingTotalRsd?: string | number | null;
        proposedTotalRsd: string | number;
        differenceRsd: string | number;
        manuallyEdited: boolean;
    }>;
    legacyClassificationConflicts: Array<{entryId: number; reason: string; totalRsd: string | number}>;
    missingCategories: string[];
    deletedCategories: string[];
    obsoleteCategories: Array<{entryId: number; entryNumber: number; incomeCategory: string; totalRsd: string | number; manuallyEdited: boolean}>;
    unresolved: {amountOriginal: string | number; amountRsd: string | number};
    rounding: {
        categoryResidualRsd: string | number;
        snapshotDiscrepancyRsd: string | number;
        reconciliationStatus: string;
        hasUnallocatedRoundingResidual: boolean;
    };
}

export interface KpoRegenerationPreview {
    year: number;
    includeHistorical: boolean;
    readOnly: boolean;
    invoices: KpoRegenerationInvoice[];
    actionableCount: number;
    unchangedCount: number;
    previewFingerprint: string;
}

export interface KpoRegenerationResult {
    operationId?: string;
    created: any[];
    updated: any[];
    unchanged: any[];
    protected: any[];
    blocked: any[];
    obsolete: any[];
    partial: any[];
    unresolved: any[];
    roundingResiduals: any[];
}

export interface InvoiceKpoConflict {
    reason: string;
    invoiceId: number;
    invoiceNumber: string;
    existingEntries: KpoRegenerationEntry[];
    proposedEntries: KpoRegenerationProposal[];
    amountDifferences: KpoRegenerationInvoice['amountDifferences'];
    obsoleteCategories: KpoRegenerationInvoice['obsoleteCategories'];
    warnings: string[];
    requiresManualOverwrite: boolean;
    requiresRegeneration: boolean;
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
    other_settings?: Record<string, unknown>;
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
    items: { id?: number; description: string; quantity: number; price: number; incomeCategory: InvoiceIncomeCategory }[];
    includeInKpo?: boolean;
    vatEnabledAtIssue?: boolean;
    bankAccountId?: number | null;
}

export interface CompanyBankAccountPayload {
    label?: string;
    bank_name: string;
    account_number: string;
    iban?: string;
    swift_bic?: string;
    currency: string;
    account_type: 'domestic' | 'foreign';
    payment_instructions?: string;
    is_default?: boolean;
    is_active?: boolean;
}

export interface InvoiceKpoConfirmation {
    confirmed: boolean;
    previewFingerprint: string;
    confirmManualOverwrite?: boolean;
    confirmVatStatusChange?: boolean;
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
    createBankAccount: (companyId: number, payload: CompanyBankAccountPayload) => Promise<CompanyBankAccount>;
    updateBankAccount: (companyId: number, accountId: number, payload: CompanyBankAccountPayload) => Promise<CompanyBankAccount>;
    setDefaultBankAccount: (companyId: number, accountId: number) => Promise<CompanyBankAccount>;
    deactivateBankAccount: (companyId: number, accountId: number) => Promise<void>;
    deleteCompany: (id: number) => Promise<void>;
    addClient: (client: ClientCreatePayload) => Promise<void>;
    updateClient: (id: number, data: ClientCreatePayload) => Promise<void>;
    deleteClient: (id: number) => Promise<void>;
    addInvoice: (invoice: InvoiceCreatePayload) => Promise<void>;
    updateInvoice: (id: number, invoice: InvoiceCreatePayload, confirmation?: InvoiceKpoConfirmation) => Promise<void>;
    refreshInvoice: (id: number) => Promise<Invoice>;
    getInvoiceHistory: (id: number) => Promise<{invoice: any; revisions: any[]} >;
    getInvoicePdfVersions: (id: number) => Promise<InvoicePdfVersion[]>;
    downloadInvoicePdfVersion: (id: number, version: number, invoiceNumber?: string) => Promise<void>;
    deleteInvoice: (id: number, confirmKpoRemoval?: boolean) => Promise<void>;
    getNextInvoiceNumber: () => Promise<string>;
    updateInvoiceStatus: (id: number, status: InvoiceStatus, payment?: {paidAt?: string | null; paymentNote?: string | null}) => Promise<void>;
    loadInvoices: (overdue?: boolean) => Promise<void>;
    updateInvoiceItemClassifications: (id: number, items: { id: number; incomeCategory: InvoiceIncomeCategory }[]) => Promise<void>;
    downloadInvoicePdf: (id: number, invoiceNumber?: string, options?: { force?: boolean }) => Promise<void>;
    sendInvoiceEmail: (id: number, payload?: { recipient?: string; subject?: string; message?: string }) => Promise<any>;
    getInvoiceEmailHistory: (id: number) => Promise<any[]>;
    loadKpo: (filters?: KpoFilters) => Promise<KpoListResponse>;
    downloadKpoPdf: (filters?: KpoFilters) => Promise<void>;
    getKpoEntry: (id: number) => Promise<KpoEntry>;
    updateKpoEntry: (id: number, payload: KpoUpdatePayload) => Promise<KpoEntry>;
    deleteKpoEntry: (id: number) => Promise<void>;
    removeGeneratedKpoEntry: (id: number, reason: string) => Promise<void>;
    getKpoHistory: (id: number) => Promise<{entry: any; revisions: any[]}>;
    previewKpoGeneration: (year: number, includeHistorical?: boolean) => Promise<KpoGenerationPreview>;
    generateKpoEntries: (year: number, entries: KpoGenerationSelection[], confirmManualOverlap?: boolean, includeHistorical?: boolean) => Promise<KpoGenerationResult>;
    previewKpoRegeneration: (year: number, invoiceIds?: number[], includeHistorical?: boolean) => Promise<KpoRegenerationPreview>;
    regenerateKpoEntries: (year: number, invoiceIds: number[], previewFingerprint: string, confirmManualOverwrite?: boolean, includeHistorical?: boolean) => Promise<KpoRegenerationResult>;
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
        defaultTaxPercent: company.default_tax_percent !== undefined ? Number(company.default_tax_percent) : 20,
        vat_enabled: Boolean(company.vat_enabled),
        payment_due_days: company.payment_due_days !== undefined ? Number(company.payment_due_days) : undefined,
        otherSettings: company.other_settings ?? undefined,
        bankAccounts: Array.isArray(company.bank_accounts)
            ? company.bank_accounts.map(normalizeBankAccount)
            : undefined,
        logoUrl,
    };
};

const normalizeBankAccount = (account: any): CompanyBankAccount => ({
    id: Number(account.id),
    companyId: Number(account.company_id),
    label: account.label ?? null,
    bankName: account.bank_name ?? '',
    accountNumber: account.account_number ?? '',
    iban: account.iban ?? null,
    swiftBic: account.swift_bic ?? null,
    currency: String(account.currency ?? '').toUpperCase(),
    accountType: account.account_type === 'foreign' ? 'foreign' : 'domestic',
    paymentInstructions: account.payment_instructions ?? null,
    isDefault: Boolean(account.is_default),
    isActive: Boolean(account.is_active),
});

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

const normalizeDateInput = (value: unknown): string => {
    if (value === null || value === undefined || value === '') return '';
    const text = String(value);
    if (/^\d{4}-\d{2}-\d{2}/.test(text)) return text.slice(0, 10);
    return '';
};

const normalizeInvoice = (invoice: any): Invoice => {
    const items = Array.isArray(invoice.items) ? invoice.items : [];
    const total = Number(invoice.total ?? 0);
    const subtotalOriginal = Number(invoice.subtotal_original ?? 0);
    const taxOriginal = Number(invoice.tax_original ?? 0);
    const totalOriginal = Number(invoice.total_original ?? 0);
    const totalRsd = Number(invoice.total_rsd ?? 0);

    return {
        id: Number(invoice.id),
        companyId: Number(invoice.company_id ?? 0),
        clientId: Number(invoice.client_id ?? 0),
        number: invoice.invoice_number ?? '',
        date: normalizeDateInput(invoice.issue_date),
        dueDate: normalizeDateInput(invoice.due_date),
        isOverdue: Boolean(invoice.is_overdue),
        overdueDays: Number(invoice.overdue_days ?? 0),
        currency: invoice.currency ?? 'RSD',
        status: invoice.status as InvoiceStatus,
        paidAt: normalizeDateInput(invoice.paid_at),
        paymentNote: invoice.payment_note ?? null,
        paymentMethod: invoice.payment_method,
        total,
        subtotalOriginal: subtotalOriginal > 0 ? subtotalOriginal : undefined,
        taxOriginal: taxOriginal >= 0 ? taxOriginal : undefined,
        totalOriginal: totalOriginal > 0 ? totalOriginal : total,
        totalRsd: totalRsd > 0 ? totalRsd : total,
        fxRateToRsd: invoice.fx_rate_to_rsd !== undefined && invoice.fx_rate_to_rsd !== null
            ? Number(invoice.fx_rate_to_rsd)
            : undefined,
        includeInKpo: invoice.include_in_kpo !== false,
        vatEnabledAtIssue: invoice.vat_enabled_at_issue ?? null,
        note: invoice.note ?? null,
        pdfPath: invoice.pdf_path ?? null,
        bankAccountId: invoice.bank_account_id ? Number(invoice.bank_account_id) : null,
        bankAccountBankName: invoice.bank_account_bank_name ?? null,
        bankAccountNumber: invoice.bank_account_number ?? null,
        bankAccountIban: invoice.bank_account_iban ?? null,
        bankAccountSwiftBic: invoice.bank_account_swift_bic ?? null,
        bankAccountCurrency: invoice.bank_account_currency ?? null,
        bankAccountPaymentInstructions: invoice.bank_account_payment_instructions ?? null,
        items: items.map((item: any) => ({
            id: Number(item.id ?? 0),
            description: item.name ?? item.description ?? '',
            quantity: Number(item.quantity ?? 0),
            price: Number(item.price ?? 0),
            incomeCategory: item.income_category ?? null,
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
    incomeCategory: entry.income_category ?? null,
    currency: entry.currency ?? 'RSD',
    amountOriginal: Number(entry.amount_original ?? 0),
    amountRsd: Number(entry.amount_rsd ?? 0),
    adjustmentRsd: Number(entry.adjustment_rsd ?? 0),
    totalRsd: Number(entry.total_rsd ?? 0),
    description: entry.description ?? null,
    deletedAt: entry.deleted_at ?? null,
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
        if (key === 'other_settings') {
            form.append(key, JSON.stringify(value));
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

    const loadInvoices = async (overdue = false) => {
        try {
            const response = await api.get('/api/invoices', {params: overdue ? {overdue: 1} : undefined});
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

    const deleteKpoEntry = async (id: number): Promise<void> => {
        await api.delete(`/api/kpo/${id}`);
    };

    const removeGeneratedKpoEntry = async (id: number, reason: string): Promise<void> => {
        await api.post(`/api/kpo/${id}/remove-generated`, {reason, confirm: true});
    };

    const getKpoHistory = async (id: number): Promise<{entry: any; revisions: any[]}> => {
        const response = await api.get(`/api/kpo/${id}/history`);
        return {
            entry: response.data?.entry ?? {},
            revisions: Array.isArray(response.data?.revisions) ? response.data.revisions : [],
        };
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
            manualOverlapWarning: response.data?.manual_overlap_warning ? {
                count: Number(response.data.manual_overlap_warning.count ?? 0),
                year: Number(response.data.manual_overlap_warning.year ?? year),
                requiresConfirmation: Boolean(response.data.manual_overlap_warning.requires_confirmation),
            } : null,
        };
    };

    const generateKpoEntries = async (year: number, entries: KpoGenerationSelection[], confirmManualOverlap = false, includeHistorical = false): Promise<KpoGenerationResult> => {
        const response = await api.post('/api/kpo/generate', {
            year,
            entries,
            confirm_manual_overlap: confirmManualOverlap,
            include_historical: includeHistorical,
        });
        return {
            created: response.data?.created ?? [],
            skipped: response.data?.skipped ?? [],
            failed: response.data?.failed ?? [],
        };
    };

    const previewKpoRegeneration = async (year: number, invoiceIds: number[] = [], includeHistorical = false): Promise<KpoRegenerationPreview> => {
        const response = await api.get('/api/kpo/regeneration-preview', {
            params: {
                year,
                include_historical: includeHistorical ? 1 : 0,
                ...(invoiceIds.length ? {invoice_ids: invoiceIds} : {}),
            },
        });
        const data = response.data ?? {};

        return {
            year: Number(data.year ?? year),
            includeHistorical: Boolean(data.include_historical),
            readOnly: Boolean(data.read_only),
            actionableCount: Number(data.actionable_count ?? 0),
            unchangedCount: Number(data.unchanged_count ?? 0),
            previewFingerprint: String(data.preview_fingerprint ?? ''),
            invoices: Array.isArray(data.invoices) ? data.invoices.map((invoice: any) => ({
                invoiceId: Number(invoice.invoice_id),
                invoiceNumber: String(invoice.invoice_number ?? invoice.invoice_id),
                issueDate: String(invoice.issue_date ?? ''),
                status: String(invoice.status ?? 'blocked'),
                actionable: Boolean(invoice.actionable),
                requiresAttention: Boolean(invoice.requires_attention),
                blockingReasons: Array.isArray(invoice.blocking_reasons) ? invoice.blocking_reasons : [],
                existingEntries: Array.isArray(invoice.existing_entries) ? invoice.existing_entries.map((entry: any) => ({
                    id: Number(entry.id),
                    entryNumber: Number(entry.entry_number),
                    entryDate: String(entry.entry_date ?? ''),
                    incomeCategory: entry.income_category ?? null,
                    incomeType: entry.income_type ?? null,
                    amountOriginal: entry.amount_original ?? 0,
                    amountRsd: entry.amount_rsd ?? 0,
                    adjustmentRsd: entry.adjustment_rsd ?? 0,
                    totalRsd: entry.total_rsd ?? 0,
                    origin: entry.origin ?? null,
                    manuallyEdited: Boolean(entry.manually_edited),
                })) : [],
                proposedEntries: Array.isArray(invoice.proposed_entries) ? invoice.proposed_entries.map((proposal: any) => ({
                    incomeCategory: String(proposal.income_category ?? 'unknown'),
                    amountOriginal: proposal.amount_original ?? 0,
                    amountRsd: proposal.amount_rsd ?? 0,
                    totalRsd: proposal.total_rsd ?? 0,
                    existingEntryId: proposal.existing_entry_id ?? null,
                    action: String(proposal.action ?? 'review'),
                    manuallyEdited: Boolean(proposal.manually_edited),
                })) : [],
                amountDifferences: Array.isArray(invoice.amount_differences) ? invoice.amount_differences.map((difference: any) => ({
                    incomeCategory: String(difference.income_category ?? 'unknown'),
                    existingEntryId: difference.existing_entry_id ?? null,
                    existingTotalRsd: difference.existing_total_rsd ?? null,
                    proposedTotalRsd: difference.proposed_total_rsd ?? 0,
                    differenceRsd: difference.difference_rsd ?? 0,
                    manuallyEdited: Boolean(difference.manually_edited),
                })) : [],
                legacyClassificationConflicts: Array.isArray(invoice.legacy_classification_conflicts) ? invoice.legacy_classification_conflicts.map((conflict: any) => ({
                    entryId: Number(conflict.entry_id),
                    reason: String(conflict.reason ?? 'legacy_conflict'),
                    totalRsd: conflict.total_rsd ?? 0,
                })) : [],
                missingCategories: Array.isArray(invoice.missing_categories) ? invoice.missing_categories : [],
                deletedCategories: Array.isArray(invoice.deleted_categories) ? invoice.deleted_categories : [],
                obsoleteCategories: Array.isArray(invoice.obsolete_categories) ? invoice.obsolete_categories.map((obsolete: any) => ({
                    entryId: Number(obsolete.entry_id),
                    entryNumber: Number(obsolete.entry_number),
                    incomeCategory: String(obsolete.income_category ?? 'unknown'),
                    totalRsd: obsolete.total_rsd ?? 0,
                    manuallyEdited: Boolean(obsolete.manually_edited),
                })) : [],
                unresolved: {
                    amountOriginal: invoice.unresolved?.amount_original ?? 0,
                    amountRsd: invoice.unresolved?.amount_rsd ?? 0,
                },
                rounding: {
                    categoryResidualRsd: invoice.rounding?.category_residual_rsd ?? 0,
                    snapshotDiscrepancyRsd: invoice.rounding?.snapshot_discrepancy_rsd ?? 0,
                    reconciliationStatus: String(invoice.rounding?.reconciliation_status ?? 'unknown'),
                    hasUnallocatedRoundingResidual: Boolean(invoice.rounding?.has_unallocated_rounding_residual),
                },
            })) : [],
        };
    };

    const regenerateKpoEntries = async (year: number, invoiceIds: number[], previewFingerprint: string, confirmManualOverwrite = false, includeHistorical = false): Promise<KpoRegenerationResult> => {
        const response = await api.post('/api/kpo/regenerate', {
            year,
            invoice_ids: invoiceIds,
            preview_fingerprint: previewFingerprint,
            confirm_regeneration: true,
            confirm_manual_overwrite: confirmManualOverwrite,
            include_historical: includeHistorical,
        });
        const data = response.data ?? {};
        return {
            operationId: data.operation_id,
            created: data.created ?? [],
            updated: data.updated ?? [],
            unchanged: data.unchanged ?? [],
            protected: data.protected ?? [],
            blocked: data.blocked ?? [],
            obsolete: data.obsolete ?? [],
            partial: data.partial ?? [],
            unresolved: data.unresolved ?? [],
            roundingResiduals: data.rounding_residuals ?? [],
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

    const updateBankAccounts = (companyId: number, account: CompanyBankAccount) => {
        setCompanies(current => current.map(company => company.id === companyId
            ? {...company, bankAccounts: (company.bankAccounts ?? []).some(item => item.id === account.id)
                ? (company.bankAccounts ?? []).map(item => item.id === account.id ? account : item)
                : [...(company.bankAccounts ?? []), account]}
            : company));
    };

    const createBankAccount = async (companyId: number, payload: CompanyBankAccountPayload) => {
        const response = await api.post(`/api/companies/${companyId}/bank-accounts`, payload);
        const account = normalizeBankAccount(response.data);
        updateBankAccounts(companyId, account);
        return account;
    };

    const updateBankAccount = async (companyId: number, accountId: number, payload: CompanyBankAccountPayload) => {
        const response = await api.put(`/api/companies/${companyId}/bank-accounts/${accountId}`, payload);
        const account = normalizeBankAccount(response.data);
        updateBankAccounts(companyId, account);
        return account;
    };

    const setDefaultBankAccount = async (companyId: number, accountId: number) => {
        const response = await api.post(`/api/companies/${companyId}/bank-accounts/${accountId}/default`);
        const account = normalizeBankAccount(response.data);
        const currency = account.currency;
        setCompanies(current => current.map(company => company.id === companyId
            ? {...company, bankAccounts: (company.bankAccounts ?? []).map(item => item.currency === currency
                ? {...item, isDefault: item.id === account.id}
                : item)}
            : company));
        return account;
    };

    const deactivateBankAccount = async (companyId: number, accountId: number) => {
        await api.delete(`/api/companies/${companyId}/bank-accounts/${accountId}`);
        setCompanies(current => current.map(company => company.id === companyId
            ? {...company, bankAccounts: (company.bankAccounts ?? []).map(item => item.id === accountId ? {...item, isActive: false, isDefault: false} : item)}
            : company));
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
            vat_enabled_at_issue: invoice.vatEnabledAtIssue,
            bank_account_id: invoice.bankAccountId ?? undefined,
            items: invoice.items.map(item => ({
                name: item.description,
                description: item.description,
                quantity: item.quantity,
                price: item.price,
                income_category: item.incomeCategory,
            })),
        };

        const response = await api.post('/api/invoices', payload);
        const created = normalizeInvoice(response.data);
        setInvoices(current => [...current, created]);
    };

    const updateInvoice = async (id: number, invoice: InvoiceCreatePayload, confirmation?: InvoiceKpoConfirmation) => {
        const response = await api.put(`/api/invoices/${id}`, {
            client_id: invoice.clientId,
            invoice_number: invoice.number,
            issue_date: invoice.date || undefined,
            due_date: invoice.dueDate || undefined,
            currency: invoice.currency,
            payment_method: invoice.paymentMethod,
            note: invoice.note,
            include_in_kpo: invoice.includeInKpo !== false,
            vat_enabled_at_issue: invoice.vatEnabledAtIssue,
            bank_account_id: invoice.bankAccountId ?? undefined,
            confirm_vat_status_change: confirmation?.confirmVatStatusChange ?? false,
            items: invoice.items.map((item: any) => ({
                id: item.id,
                name: item.description,
                description: item.description,
                quantity: item.quantity,
                price: item.price,
                income_category: item.incomeCategory,
            })),
            ...(confirmation?.confirmed ? {
                kpo_confirmation: {
                    confirmed: confirmation.confirmed,
                    preview_fingerprint: confirmation.previewFingerprint,
                    confirm_manual_overwrite: confirmation.confirmManualOverwrite ?? false,
                },
            } : {}),
        });
        const updated = normalizeInvoice(response.data);
        setInvoices(current => current.map(inv => (inv.id === id ? updated : inv)));
    };

    const deleteInvoice = async (id: number, confirmKpoRemoval = false) => {
        await api.delete(`/api/invoices/${id}`, {
            data: {confirm_kpo_removal: confirmKpoRemoval},
        });
        setInvoices(current => current.filter(c => c.id !== id));
    };

    const getNextInvoiceNumber = async () => {
        const response = await api.get('/api/invoices/next-number');
        return response.data?.invoice_number ?? '';
    };

    const refreshInvoice = async (id: number): Promise<Invoice> => {
        const response = await api.get(`/api/invoices/${id}`);
        const rawInvoice = response.data?.invoice ?? response.data;
        const updated = normalizeInvoice({
            ...rawInvoice,
            items: rawInvoice?.items ?? response.data?.invoice_items ?? [],
        });
        setInvoices(current => current.map(inv => (inv.id === id ? updated : inv)));
        return updated;
    };

    const updateInvoiceStatus = async (id: number, status: InvoiceStatus, payment?: {paidAt?: string | null; paymentNote?: string | null}) => {
        const response = await api.put(`/api/invoices/${id}`, {
            status,
            ...(payment ? {paid_at: payment.paidAt ?? null, payment_note: payment.paymentNote ?? null} : {}),
        });
        const updated = normalizeInvoice(response.data);
        setInvoices(current => current.map(inv => (inv.id === id ? updated : inv)));
    };

    const getInvoiceHistory = async (id: number) => {
        const response = await api.get(`/api/invoices/${id}/history`);
        return response.data;
    };

    const getInvoicePdfVersions = async (id: number): Promise<InvoicePdfVersion[]> => {
        const response = await api.get(`/api/invoices/${id}/pdf-versions`);
        return (Array.isArray(response.data) ? response.data : []).map((version: any) => ({
            id: Number(version.id),
            invoiceId: version.invoice_id === null ? null : Number(version.invoice_id),
            version: Number(version.version),
            path: version.path ?? '',
            createdAt: version.created_at,
            protectedAt: version.protected_at,
            sentAt: version.sent_at,
        }));
    };

    const updateInvoiceItemClassifications = async (id: number, items: { id: number; incomeCategory: InvoiceIncomeCategory }[]) => {
        const response = await api.patch(`/api/invoices/${id}/items/classifications`, {
            items: items.map(item => ({
                id: item.id,
                income_category: item.incomeCategory,
            })),
        });
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

    const downloadInvoicePdfVersion = async (id: number, version: number, invoiceNumber?: string) => {
        const response = await api.get(`/api/invoices/${id}/pdf-versions/${version}/download`, {responseType: 'blob'});
        const contentDisposition = response.headers?.['content-disposition'] as string | undefined;
        const match = contentDisposition?.match(/filename=\"?([^\";]+)\"?/i);
        const blob = new Blob([response.data], {type: 'application/pdf'});
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = match?.[1] || `faktura-${invoiceNumber ?? id}-v${version}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    };

    const sendInvoiceEmail = async (id: number, payload?: { recipient?: string; subject?: string; message?: string }) => {
        const response = await api.post(`/api/invoices/${id}/email`, payload ?? {});
        return response.data;
    };

    const getInvoiceEmailHistory = async (id: number): Promise<any[]> => {
        const response = await api.get(`/api/invoices/${id}/email-history`);
        return Array.isArray(response.data) ? response.data : [];
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
            createBankAccount,
            updateBankAccount,
            setDefaultBankAccount,
            deactivateBankAccount,
            deleteCompany,
            addClient,
            updateClient,
            deleteClient,
            addInvoice,
            updateInvoice,
            refreshInvoice,
            getInvoiceHistory,
            getInvoicePdfVersions,
            deleteInvoice,
            getNextInvoiceNumber,
            updateInvoiceStatus,
            loadInvoices,
            updateInvoiceItemClassifications,
            downloadInvoicePdf,
            downloadInvoicePdfVersion,
            sendInvoiceEmail,
            getInvoiceEmailHistory,
            loadKpo,
            downloadKpoPdf,
            getKpoEntry,
            updateKpoEntry,
            deleteKpoEntry,
            removeGeneratedKpoEntry,
            getKpoHistory,
            previewKpoGeneration,
            generateKpoEntries,
            previewKpoRegeneration,
            regenerateKpoEntries,
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
