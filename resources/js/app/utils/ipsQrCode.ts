import {formatCurrencyAlt} from './format';
import type {InvoiceDocumentClient, InvoiceDocumentCompany, InvoiceDocumentInvoice} from '../components/InvoiceDocument';

export interface IpsQrCodeInput {
    company: Pick<InvoiceDocumentCompany, 'name' | 'bank_account'>;
    client: Pick<InvoiceDocumentClient, 'name' | 'address' | 'city'>;
    invoice: Pick<InvoiceDocumentInvoice, 'number' | 'items'>;
    amountInRSD: number;
    clientTypeCode?: string;
}

export function buildIpsQrBodyParams({
    company,
    client,
    invoice,
    amountInRSD,
    clientTypeCode,
}: IpsQrCodeInput): string[] {
    const caddress = client.address ?? '';
    const ccity = client.city ?? '';

    return [
        'K:PR',
        'V:01',
        'C:1',
        `R:${company.bank_account.replaceAll('-', '').trim()}`,
        `N:${company.name}`,
        `I:${formatCurrencyAlt({amount: amountInRSD})}`,
        `P:${client.name}\n ${caddress}, ${ccity}`,
        `SF:${clientTypeCode}`,
        `S:${invoice.items[0]?.description.substring(0, 35)}`,
        `RO:00${invoice.number}`,
    ];
}

export async function fetchIpsQrCodeResponse(bodyParams: string[]) {
    const response = await fetch('https://nbs.rs/QRcode/api/qr/v1/generate', {
        method: 'POST',
        body: bodyParams.join('|'),
    });

    return response.json();
}

export async function fetchIpsQrCode(bodyParams: string[]): Promise<string | null> {
    const data = await fetchIpsQrCodeResponse(bodyParams);

    return data?.s?.code === 0 ? data.i : null;
}
