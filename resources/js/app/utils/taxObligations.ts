import {api, ensureCsrf} from '../context/AppContext';

export const standardTaxCategories = ['Porez', 'PIO', 'Zdravstveno', 'Nezaposlenost'];
export const taxFrequencyLabels = {once: 'Jednokratno', monthly: 'Mesečno', quarterly: 'Kvartalno', yearly: 'Godišnje'};
export type TaxFrequency = keyof typeof taxFrequencyLabels;
export type PaymentTag = 'R' | 'N' | 'P' | 'SF' | 'S' | 'RO';
export type PaymentDetails = Partial<Record<PaymentTag, string | null>>;

export interface TaxVersion {
    id: number;
    effective_on: string;
    amount_minor: number | null;
    payment_details: PaymentDetails;
}

export interface TaxAttachment {
    id: number;
    name: string;
}

export interface TaxSchedule {
    id: number;
    name: string;
    category: string;
    starts_on: string;
    frequency: TaxFrequency;
    period_count: number | null;
    due_on: string | null;
    due_day: number | null;
    due_month_offset: number | null;
    roll_forward: boolean;
    reminder_days: number | null;
    note: string | null;
    versions: TaxVersion[];
    attachments: TaxAttachment[];
}

export interface TaxObligation {
    id: number;
    period_start: string;
    period_end: string;
    due_on: string;
    remind_on: string | null;
    amount_minor: number | null;
    paid_minor: number;
    outstanding_minor: number | null;
    schedule: Pick<TaxSchedule, 'id' | 'name' | 'category'>;
}

export interface TaxObligationDetail extends TaxObligation {
    note: string | null;
    version: TaxVersion;
    payments: {id: number; amount_minor: number; paid_on: string; note: string | null}[];
    attachments: TaxAttachment[];
    qr: {png: string | null; payload: string | null; errors: Record<string, string[]>};
}

interface TaxOverview {
    today: string;
    categories: string[];
    schedules: TaxSchedule[];
    obligations: TaxObligation[];
    reminders: TaxObligation[];
    totals: {
        assessed_minor: number;
        paid_minor: number;
        outstanding_minor: number;
        overdue_minor: number;
        unassessed_count: number;
    };
    non_working_days: string[];
}

type TaxVersionInput = Pick<TaxVersion, 'amount_minor' | 'payment_details'>;
interface TaxScheduleInput {
    request_id: string;
    starts_on: string;
    frequency: TaxFrequency;
    period_count: string | null;
    due_on: string | null;
    due_day: string | null;
    due_month_offset: string | null;
    roll_forward: boolean;
    reminder_days: string | null;
    note: string | null;
    items: (TaxVersionInput & {name: string; category: string})[];
}

const path = (company: number) => `/api/companies/${company}/taxes`;
const attachmentPath = (company: number, schedule: number) => `${path(company)}/schedules/${schedule}/attachments`;

export const taxesApi = {
    async overview(company: number, year: number, signal: AbortSignal) {
        return (await api.get<TaxOverview>(path(company), {params: {year}, signal})).data;
    },
    async obligation(company: number, id: number, signal: AbortSignal) {
        return (await api.get<TaxObligationDetail>(`${path(company)}/obligations/${id}`, {signal})).data;
    },
    async create(company: number, payload: TaxScheduleInput) {
        await ensureCsrf();
        await api.post(`${path(company)}/schedules`, payload);
    },
    async revise(company: number, schedule: number, payload: TaxVersionInput & {effective_on: string}) {
        await ensureCsrf();
        await api.post(`${path(company)}/schedules/${schedule}/versions`, payload);
    },
    async generate(company: number, year: number) {
        await ensureCsrf();
        return (await api.post<{created: number}>(`${path(company)}/generate`, {year})).data;
    },
    async calendar(company: number, days: string[]) {
        await ensureCsrf();
        await api.put(`${path(company)}/calendar`, {days});
    },
    async update(company: number, id: number, payload: {amount_minor?: number; note?: string}) {
        await ensureCsrf();
        await api.patch(`${path(company)}/obligations/${id}`, payload);
    },
    async pay(company: number, id: number, payload: {request_id: string; amount_minor: number; paid_on: string; note: string}) {
        await ensureCsrf();
        await api.post(`${path(company)}/obligations/${id}/payments`, payload);
    },
    async attach(company: number, schedule: number, file: File, obligation?: number) {
        const data = new FormData();
        data.set('file', file);
        if (obligation !== undefined) data.set('obligation_id', String(obligation));
        await ensureCsrf();
        await api.post(attachmentPath(company, schedule), data);
    },
    attachmentUrl(company: number, schedule: number, id: number) {
        return `${attachmentPath(company, schedule)}/${id}`;
    },
};

export function parseTaxAmount(value: string): number | null {
    if (value.trim() === '') return null;
    const match = value.trim().match(/^([0-9]{1,12})(?:[.,]([0-9]{1,2}))?$/);
    if (!match) throw new Error('Unesite iznos u RSD sa najviše dve decimale, bez separatora hiljada.');
    return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
}

export function taxError(error: unknown): string {
    const failure = error as {response?: {data?: {errors?: Record<string, string[]>; message?: string}}; message?: string};
    const data = failure?.response?.data;
    return Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? failure?.message ?? 'Zahtev nije uspeo.';
}

export const taxInput = 'mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm';
export const taxButton = 'rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50';
export const taxSecondaryButton = 'rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50 disabled:opacity-50';
