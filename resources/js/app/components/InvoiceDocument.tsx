import React from 'react';
import clsx from 'clsx';
import {format} from 'date-fns';
import {srLatn} from 'date-fns/locale';
import {formatCurrency, formatCurrencyAlt} from '../utils/format';
import {MetaData} from "../context/AppContext";
import {useQuery} from "@tanstack/react-query";
import {toast} from "sonner";

export interface InvoiceDocumentCompany {
    id: number;
    name: string;
    tax_id?: string | null;
    registration_number?: string | null;
    address?: string | null;
    city?: string | null;
    country?: string | null;
    email?: string | null;
    phone?: string | null;
    bank_account: string;
    iban?: string | null;
    swift?: string | null;
    currency?: string | null;
    vat_enabled: boolean;
    logoUrl?: string | null;
}

export interface InvoiceDocumentClient {
    id: number;
    name: string;
    email?: string | null;
    address?: string | null;
    city?: string | null;
    country?: string | null;
    phone?: string | null;
    tax_id?: string | null;
    registration_number?: string | null;
    client_type: string;
}

export interface InvoiceDocumentItem {
    id: number;
    description: string;
    quantity: number;
    price: number;
}

export interface InvoiceDocumentInvoice {
    id: number;
    number: string;
    date: string;
    dueDate: string;
    currency?: string | null;
    paymentMethod: string;
    items: InvoiceDocumentItem[];
    total: number;
    totalOriginal?: number;
    totalRsd?: number;
    fxRateToRsd?: number;
}

export interface InvoiceDocumentMeta {

}

export interface InvoiceDocumentProps {
    invoice: InvoiceDocumentInvoice;
    client: InvoiceDocumentClient;
    company: InvoiceDocumentCompany;
    currency?: string | null;
    className?: string;
    meta?: MetaData | null
}

export default function InvoiceDocument({
                                            invoice,
                                            client,
                                            company,
                                            currency,
                                            className,
                                            meta
                                        }: InvoiceDocumentProps) {
    const safeMeta = meta ?? {
        countries: {},
        currencies: {},
        payment_methods: {},
        client_types: [],
    };
    const {name: cname, address: caddress = '', city: ccity = '', country: ccountry = '', email: cemail, phone: cphone, tax_id: ctax_id, registration_number: cregistration_number, client_type} = client;
    const resolvedCurrency = currency || invoice.currency || company.currency || 'RSD';
    const vatRate = 0.2;
    const vatAmount = company.vat_enabled ? invoice.total * vatRate : 0;
    const totalWithVat = company.vat_enabled ? invoice.total + vatAmount : invoice.total;
    const isDomesticPayment = company.country === 'Srbija' && ccountry === 'Srbija';
    const isExternalPayment = company.country !== ccountry;
    const shouldConvertToRSD = isDomesticPayment && resolvedCurrency !== 'RSD';
    const getPaymentCode = (currentClientType: string) => safeMeta.client_types?.find(type => type.value === currentClientType);
    const paymentMethodLabel = Array.isArray(safeMeta.payment_methods)
        ? safeMeta.payment_methods.find(method => method.key === invoice.paymentMethod)?.label
        : safeMeta.payment_methods?.[invoice.paymentMethod];

    const hasStoredFxRate = typeof invoice.fxRateToRsd === 'number' && invoice.fxRateToRsd > 0;
    const {data: exchangeRateData, error: exchangeRateError} = useQuery({
        queryKey: ['currency', resolvedCurrency],
        queryFn: async () => {
            const response = await fetch(`/api/currency-rates/${resolvedCurrency}/today`);
            if (!response.ok) {
                throw new Error('Neuspesno preuzimanje dnevnog kursa.');
            }

            return response.json();
        },
        enabled: shouldConvertToRSD && !hasStoredFxRate
    });

    const exchangeRate = hasStoredFxRate ? invoice.fxRateToRsd : exchangeRateData?.exchange_middle;
    const amountInRSD = shouldConvertToRSD && exchangeRate
        ? invoice.total * exchangeRate
        : invoice.total;
    const displayCurrency = shouldConvertToRSD ? 'RSD' : resolvedCurrency;
    const convertToDisplayAmount = (amount: number) => (
        shouldConvertToRSD && exchangeRate ? amount * exchangeRate : amount
    );
    const renderAmount = (amount: number) => {
        const primary = formatCurrency(convertToDisplayAmount(amount), displayCurrency);
        if (!shouldConvertToRSD) {
            return primary;
        }

        return (
            <div className="flex flex-col items-end">
                <span>{primary}</span>
                <span className="text-xs text-gray-500">{formatCurrency(amount, resolvedCurrency)}</span>
            </div>
        );
    };

    const bodyParams = [
        "K:PR",
        "V:01",
        "C:1",
        `R:${company.bank_account.replaceAll('-', '').trim()}`,
        `N:${company.name}\n${company.address}, ${company.city}`,
        `I:${formatCurrencyAlt({amount: amountInRSD})}`,
        `P:${cname}\n ${caddress}, ${ccity}`,
        `SF:${getPaymentCode(client_type)?.code}`,
        `S:${invoice.items[0]?.description}`,
        `RO:00${invoice.number}`
    ];

    const {data, isLoading, error} = useQuery({
        queryKey: ['qrCode', invoice.number],
        queryFn: async () => {
            const response = await fetch('https://nbs.rs/QRcode/api/qr/v1/generate', {
                method: 'POST',
                body: bodyParams.join('|'),
            });
            return response.json();
        },
        enabled: isDomesticPayment && (!shouldConvertToRSD || Boolean(exchangeRate))
    });

    const qrCode = data?.s?.code === 0 ? data.i : null;

    if (error) {
        toast.error(error.message);
    }

    if (exchangeRateError) {
        toast.error(exchangeRateError.message);
    }

    if (isDomesticPayment && !isLoading && data?.s?.code !== 0) {
        toast.error("Nije moguće generisati IPS QR kod: ", {description: data?.e?.join('\n')})
    }

    return (
        <div className={clsx('bg-white shadow-lg rounded-lg overflow-hidden print:shadow-none', className)}>
            <div className="p-8 sm:p-12">
                <div className="flex flex-col sm:flex-row justify-between items-start gap-8 border-b border-gray-100 pb-8 mb-8">
                    <div>
                        {company.logoUrl ? (
                            <img src={company.logoUrl} alt="logo" className="h-12 w-12"/>
                        ) : (
                            <div className="h-12 w-12 bg-indigo-600 rounded-lg flex items-center justify-center mb-4">
                                <span className="font-bold text-2xl text-white">{company.name.substring(0, 1)}</span>
                            </div>
                        )}
                        <h2 className="text-lg font-bold text-gray-900">{company.name}</h2>
                        <div className="text-gray-500 text-sm mt-2 space-y-1">
                            <p>
                                {company.address}
                                {company.address && company.city ? ',' : ''} {company.city}
                            </p>
                            {company.tax_id && <p>PIB: {company.tax_id}</p>}
                            {company.registration_number && <p>MB: {company.registration_number}</p>}
                            {company.bank_account && <p>BROJ RAČUNA: {company.bank_account}</p>}
                            {isExternalPayment && company.iban && <p>IBAN: {company.iban}</p>}
                            {isExternalPayment && company.swift && <p>SWIFT: {company.swift}</p>}
                            {company.email && <p>Email: {company.email}</p>}
                            {company.phone && <p>Telefon: {company.phone}</p>}
                        </div>
                    </div>
                    <div className="text-right sm:text-right">
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">FAKTURA</h1>
                        <p className="text-lg font-medium text-gray-600">{invoice.number}</p>
                        <div className="mt-4 space-y-1 text-sm text-gray-500">
                            <div className="flex justify-between gap-8">
                                <span>Datum izdavanja:</span>
                                <span className="font-medium text-gray-900">
                                    {format(new Date(invoice.date), 'dd. MMM yyyy', {locale: srLatn})}
                                </span>
                            </div>
                            <div className="flex justify-between gap-8">
                                <span>Datum prometa:</span>
                                <span className="font-medium text-gray-900">
                                    {format(new Date(invoice.date), 'dd. MMM yyyy', {locale: srLatn})}
                                </span>
                            </div>
                            <div className="flex justify-between gap-8">
                                <span>Rok plaćanja:</span>
                                <span className="font-medium text-gray-900">
                                    {format(new Date(invoice.dueDate), 'dd. MMM yyyy', {locale: srLatn})}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Za klijenta:</h3>
                        <div className="text-gray-900 font-medium text-lg">{cname}</div>
                        <div className="text-gray-500 text-sm mt-1 space-y-1">
                            <p>{[caddress, ccity, ccountry].join(', ')}</p>
                            {cemail && <p>{cemail}</p>}
                            {cphone && <p>{cphone}</p>}
                            {ctax_id && <p>PIB: {ctax_id}</p>}
                            {cregistration_number && <p>MB: {cregistration_number}</p>}
                        </div>
                    </div>
                </div>

                <div className="mt-8">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead>
                        <tr>
                            <th scope="col" className="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Opis</th>
                            <th scope="col" className="py-3.5 px-3 text-right text-sm font-semibold text-gray-900">Količina</th>
                            <th scope="col" className="py-3.5 px-3 text-right text-sm font-semibold text-gray-900">Cena</th>
                            <th scope="col" className="py-3.5 pl-3 pr-4 text-right text-sm font-semibold text-gray-900 sm:pr-0">Ukupno</th>
                        </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                        {invoice.items.map((item) => (
                            <tr key={item.id}>
                                <td className="py-4 pl-4 pr-3 text-sm sm:pl-0">
                                    <div className="font-medium text-gray-900">{item.description}</div>
                                </td>
                                <td className="py-4 px-3 text-sm text-right text-gray-500">{item.quantity}</td>
                                <td className="py-4 px-3 text-sm text-right text-gray-500">
                                    {renderAmount(item.price)}
                                </td>
                                <td className="py-4 pl-3 pr-4 text-sm text-right text-gray-900 font-medium sm:pr-0">
                                    {renderAmount(item.quantity * item.price)}
                                </td>
                            </tr>
                        ))}
                        </tbody>
                        <tfoot>
                        <tr>
                            <td rowSpan={3}>
                                {isDomesticPayment && qrCode && (
                                    <div>
                                        <img src={`data:image/png;base64, ${qrCode}`} alt="IPS QR Code" className="w-32 h-32"/>
                                    </div>
                                )}
                            </td>
                            <th scope="row" colSpan={2} className="hidden pl-4 pr-3 pt-6 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0">Međuzbir</th>
                            <th scope="row" className="pl-4 pr-3 pt-6 text-left text-sm font-normal text-gray-500 sm:hidden">Međuzbir</th>
                            <td className="pl-3 pr-4 pt-6 text-right text-sm text-gray-500 sm:pr-0">
                                {renderAmount(invoice.total)}
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" colSpan={2} className="hidden pl-4 pr-3 pt-6 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0">Način plaćanja</th>
                            <th scope="row" className="pl-4 pr-3 pt-6 text-left text-sm font-normal text-gray-500 sm:hidden">Način plaćanja</th>
                            <td className="pl-3 pr-4 pt-6 text-right text-sm text-gray-500 sm:pr-0">
                                {paymentMethodLabel ?? invoice.paymentMethod}
                            </td>
                        </tr>
                        {company.vat_enabled && (
                            <tr>
                                <th scope="row" colSpan={2} className="hidden pl-4 pr-3 pt-4 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0">PDV (20%)</th>
                                <th scope="row" className="pl-4 pr-3 pt-4 text-left text-sm font-normal text-gray-500 sm:hidden">PDV (20%)</th>
                                <td className="pl-3 pr-4 pt-4 text-right text-sm text-gray-500 sm:pr-0">
                                    {renderAmount(vatAmount)}
                                </td>
                            </tr>
                        )}
                        <tr>
                            <th scope="row" colSpan={2} className="hidden pl-4 pr-3 pt-4 text-right text-base font-bold text-gray-900 sm:table-cell sm:pl-0">Ukupno za plaćanje</th>
                            <th scope="row" className="pl-4 pr-3 pt-4 text-left text-base font-bold text-gray-900 sm:hidden">Ukupno</th>
                            <td className="pl-3 pr-4 pt-4 text-right text-base font-bold text-gray-900 sm:pr-0">
                                {renderAmount(totalWithVat)}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                <div className="mt-12 pt-6 border-t border-gray-100">
                    {!company.vat_enabled && <p className="text-gray-500 text-sm">Poreski obveznik nije u sistemu PDV-a. PDV nije obračunat na fakturi u skladu sa članom 33. Zakona o porezu na dodatu vrednost.</p>}
                </div>
            </div>
        </div>
    );
}
