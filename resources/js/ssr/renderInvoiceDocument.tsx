import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import InvoiceDocument, { type InvoiceDocumentProps } from '../app/components/InvoiceDocument';

export function render(props: InvoiceDocumentProps): string {
    return renderToStaticMarkup(<InvoiceDocument {...props} />);
}
