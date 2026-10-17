import type {InvoiceDocumentProps} from '../components/InvoiceDocument';

export function resolveInvoiceDocumentState({
    invoice,
    client,
    company,
    currency,
    meta,
}: InvoiceDocumentProps) {
    const safeMeta = meta ?? {
        countries: {},
        currencies: {},
        payment_methods: {},
        client_types: [],
    };
    const resolvedCurrency = currency || invoice.currency || company.currency || 'RSD';
    const ccountry = client.country ?? '';
    const isDomesticPayment = company.country === 'Srbija' && ccountry === 'Srbija';
    const shouldConvertToRSD = isDomesticPayment && resolvedCurrency !== 'RSD';
    const hasStoredFxRate = typeof invoice.fxRateToRsd === 'number' && invoice.fxRateToRsd > 0;
    const clientTypeCode = safeMeta.client_types?.find((type) => type.value === client.client_type)?.code;

    const exchangeRate = hasStoredFxRate ? invoice.fxRateToRsd : undefined;
    const invoiceTotal = invoice.totalOriginal ?? invoice.total;
    const amountInRSD = shouldConvertToRSD && exchangeRate
        ? invoiceTotal * exchangeRate
        : invoiceTotal;
    const canGenerateQrCode = isDomesticPayment && (!shouldConvertToRSD || Boolean(exchangeRate));

    return {
        resolvedCurrency,
        isDomesticPayment,
        shouldConvertToRSD,
        hasStoredFxRate,
        clientTypeCode,
        exchangeRate,
        amountInRSD,
        canGenerateQrCode,
    };
}
