import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import InvoiceDocument, { type InvoiceDocumentProps } from '../app/components/InvoiceDocument';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { buildIpsQrBodyParams, fetchIpsQrCode } from '../app/utils/ipsQrCode';
import { resolveInvoiceDocumentState } from '../app/utils/invoiceDocumentState';

export async function render(props: InvoiceDocumentProps): Promise<string> {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
        },
    });

    const { invoice, client, company } = props;
    const state = resolveInvoiceDocumentState(props);
    let qrCode: string | null = null;

    if (state.canGenerateQrCode) {
        const bodyParams = buildIpsQrBodyParams({
            company,
            client,
            invoice,
            amountInRSD: state.amountInRSD,
            clientTypeCode: state.clientTypeCode,
        });

        qrCode = await fetchIpsQrCode(bodyParams);
    }

    return renderToStaticMarkup(
        <QueryClientProvider client={queryClient}>
            <InvoiceDocument {...props} qrCode={qrCode} />
        </QueryClientProvider>
    );
}
