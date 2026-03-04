import type {InvoiceStatus} from '../context/AppContext';

export type InvoiceStatusOption = { key: InvoiceStatus; label: string };

export const fallbackInvoiceStatuses: InvoiceStatusOption[] = [
    {key: 'draft', label: 'Nacrt'},
    {key: 'sent', label: 'Poslato'},
    {key: 'paid', label: 'Plaćeno'},
    {key: 'overdue', label: 'Kasni'},
    {key: 'cancelled', label: 'Otkazano'},
];

export const getInvoiceStatusOptions = (
    metaStatuses?: Array<{ key: string; label: string }>
): InvoiceStatusOption[] => {
    if (!metaStatuses?.length) {
        return fallbackInvoiceStatuses;
    }

    const options = metaStatuses
        .filter(status => typeof status?.key === 'string' && typeof status?.label === 'string')
        .map(status => ({key: status.key as InvoiceStatus, label: status.label}));

    return options.length ? options : fallbackInvoiceStatuses;
};

export const getInvoiceStatusLabelMap = (options: InvoiceStatusOption[]) => {
    const labelMap: Record<InvoiceStatus, string> = {
        draft: 'Nacrt',
        sent: 'Poslato',
        paid: 'Plaćeno',
        overdue: 'Kasni',
        cancelled: 'Otkazano',
    };

    options.forEach(option => {
        labelMap[option.key] = option.label;
    });

    return labelMap;
};

export const invoiceStatusBadgeClass: Record<InvoiceStatus, string> = {
    paid: 'bg-green-100 text-green-800',
    sent: 'bg-blue-100 text-blue-800',
    overdue: 'bg-red-100 text-red-800',
    draft: 'bg-gray-100 text-gray-800',
    cancelled: 'bg-gray-200 text-gray-700',
};

export const invoiceStatusSelectClass: Record<InvoiceStatus, string> = {
    paid: 'bg-green-100 text-green-800 ring-green-200',
    sent: 'bg-blue-100 text-blue-800 ring-blue-200',
    overdue: 'bg-red-100 text-red-800 ring-red-200',
    draft: 'bg-gray-100 text-gray-800 ring-gray-200',
    cancelled: 'bg-gray-200 text-gray-700 ring-gray-300',
};
