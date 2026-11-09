import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import InvoiceDocument, {type InvoiceDocumentProps} from '../app/components/InvoiceDocument';

export async function render(props: InvoiceDocumentProps): Promise<string> {
    return renderToStaticMarkup(<InvoiceDocument {...props}/>);
}
