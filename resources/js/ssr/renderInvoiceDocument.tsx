import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import InvoiceDocument, { type InvoiceDocumentProps } from '../app/components/InvoiceDocument';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { buildIpsQrBodyParams, fetchIpsQrCode } from '../app/utils/ipsQrCode';
import { resolveInvoiceDocumentState } from '../app/utils/invoiceDocumentState';

export async function resolveQrCode(props: InvoiceDocumentProps): Promise<string | null> {
    if (props.qrCode !== undefined) {
        return props.qrCode;
    }
    const state = resolveInvoiceDocumentState(props);
    if (!state.canGenerateQrCode) {
        return null;
    }

    try {
        return await fetchIpsQrCode(buildIpsQrBodyParams({
            company: props.company,
            client: props.client,
            invoice: props.invoice,
            amountInRSD: state.amountInRSD,
            clientTypeCode: state.clientTypeCode,
        }));
    } catch {
        return null;
    }
}

export async function render(props: InvoiceDocumentProps): Promise<string> {
    const queryClient = new QueryClient({defaultOptions: {queries: {retry: false}}});
    const qrCode = await resolveQrCode(props);

    return renderToStaticMarkup(
        <QueryClientProvider client={queryClient}>
            <InvoiceDocument {...props} qrCode={qrCode} />
        </QueryClientProvider>
    );
}
