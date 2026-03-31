import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import InvoiceDocument, { type InvoiceDocumentProps } from '../app/components/InvoiceDocument';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

export function render(props: InvoiceDocumentProps): string {
    const queryClient = new QueryClient();

    return renderToStaticMarkup(
        <QueryClientProvider client={queryClient}>
            <InvoiceDocument {...props} />
        </QueryClientProvider>
    );
}
