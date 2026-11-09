import type {InvoiceDocumentProps} from '../components/InvoiceDocument';

export function resolveInvoiceDocumentState({
    invoice,
    client,
    company,
    currency,
}: InvoiceDocumentProps) {
    const resolvedCurrency = currency || invoice.currency || company.currency || 'RSD';
    const ccountry = client.country ?? '';
    const isDomesticPayment = ['RS', 'Srbija'].includes(company.country ?? '') && ['RS', 'Srbija'].includes(client.country_code ?? ccountry);
    const shouldConvertToRSD = isDomesticPayment && resolvedCurrency !== 'RSD';
    const hasStoredFxRate = typeof invoice.fxRateToRsd === 'number' && invoice.fxRateToRsd > 0;

    const exchangeRate = hasStoredFxRate ? invoice.fxRateToRsd : undefined;
    const invoiceTotal = invoice.totalOriginal ?? invoice.total;
    const amountInRSD = shouldConvertToRSD && exchangeRate
        ? (invoice.totalRsd ?? invoiceTotal * exchangeRate)
        : invoiceTotal;

    return {
        resolvedCurrency,
        isDomesticPayment,
        shouldConvertToRSD,
        hasStoredFxRate,
        exchangeRate,
        amountInRSD,
    };
}
