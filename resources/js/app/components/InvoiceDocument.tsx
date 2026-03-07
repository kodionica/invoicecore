import React from 'react';
import clsx from 'clsx';
import {format} from 'date-fns';
import {srLatn} from 'date-fns/locale';
import {formatCurrency} from '../utils/format';
import {MetaData} from "../context/AppContext";

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
}

export interface InvoiceDocumentMeta {

}

export interface InvoiceDocumentProps {
    invoice: InvoiceDocumentInvoice;
    client: InvoiceDocumentClient;
    company: InvoiceDocumentCompany;
    currency?: string | null;
    className?: string;
    meta: MetaData
}

export default function InvoiceDocument({
                                            invoice,
                                            client,
                                            company,
                                            currency,
                                            className,
                                            meta
                                        }: InvoiceDocumentProps) {
    const resolvedCurrency = currency || invoice.currency || company.currency || 'RSD';
    const vatRate = 0.2;
    const vatAmount = company.vat_enabled ? invoice.total * vatRate : 0;
    const totalWithVat = company.vat_enabled ? invoice.total + vatAmount : invoice.total;

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
                        <h2 className="text-xl font-bold text-gray-900">{company.name}</h2>
                        <div className="text-gray-500 text-sm mt-2 space-y-1">
                            <p>
                                {company.address}
                                {company.address && company.city ? ',' : ''} {company.city}
                            </p>
                            {company.tax_id && <p>PIB: {company.tax_id}</p>}
                            {company.registration_number && <p>MB: {company.registration_number}</p>}
                            {company.iban && <p>IBAN: {company.iban}</p>}
                            {company.swift && <p>SWIFT: {company.swift}</p>}
                            {company.email && <p>Email: {company.email}</p>}
                            {company.phone && <p>Telefon: {company.phone}</p>}
                        </div>
                    </div>
                    <div className="text-right sm:text-right">
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">FAKTURA</h1>
                        <p className="text-lg font-medium text-gray-600">#{invoice.number}</p>
                        <div className="mt-4 space-y-1 text-sm text-gray-500">
                            <div className="flex justify-between gap-8">
                                <span>Datum izdavanja:</span>
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
                        <div className="text-gray-900 font-medium text-lg">{client.name}</div>
                        <div className="text-gray-500 text-sm mt-1 space-y-1">
                            <p>
                                {client.address}
                                {client.address && client.city ? ',' : ''} {client.city}
                                {client.country ? `, ${client.country}` : ''}
                            </p>
                            {client.email && <p>{client.email}</p>}
                            {client.phone && <p>{client.phone}</p>}
                            {client.tax_id && <p>PIB: {client.tax_id}</p>}
                            {client.registration_number && <p>MB: {client.registration_number}</p>}
                        </div>
                    </div>
                </div>

                <div className="mt-8">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead>
                        <tr>
                            <th
                                scope="col"
                                className="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0"
                            >
                                Opis
                            </th>
                            <th
                                scope="col"
                                className="py-3.5 px-3 text-right text-sm font-semibold text-gray-900"
                            >
                                Količina
                            </th>
                            <th
                                scope="col"
                                className="py-3.5 px-3 text-right text-sm font-semibold text-gray-900"
                            >
                                Cena
                            </th>
                            <th
                                scope="col"
                                className="py-3.5 pl-3 pr-4 text-right text-sm font-semibold text-gray-900 sm:pr-0"
                            >
                                Ukupno
                            </th>
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
                                    {formatCurrency(item.price, resolvedCurrency)}
                                </td>
                                <td className="py-4 pl-3 pr-4 text-sm text-right text-gray-900 font-medium sm:pr-0">
                                    {formatCurrency(item.quantity * item.price, resolvedCurrency)}
                                </td>
                            </tr>
                        ))}
                        </tbody>
                        <tfoot>
                        <tr>
                            <th
                                scope="row"
                                colSpan={3}
                                className="hidden pl-4 pr-3 pt-6 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0"
                            >
                                Međuzbir
                            </th>
                            <th
                                scope="row"
                                className="pl-4 pr-3 pt-6 text-left text-sm font-normal text-gray-500 sm:hidden"
                            >
                                Međuzbir
                            </th>
                            <td className="pl-3 pr-4 pt-6 text-right text-sm text-gray-500 sm:pr-0">
                                {formatCurrency(invoice.total, resolvedCurrency)}
                            </td>
                        </tr>
                        <tr>
                            <th
                                scope="row"
                                colSpan={3}
                                className="hidden pl-4 pr-3 pt-6 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0"
                            >
                                Način plaćanja
                            </th>
                            <th
                                scope="row"
                                className="pl-4 pr-3 pt-6 text-left text-sm font-normal text-gray-500 sm:hidden"
                            >
                                Način plaćanja
                            </th>
                            <td className="pl-3 pr-4 pt-6 text-right text-sm text-gray-500 sm:pr-0">
                                {meta.payment_methods?.[invoice.paymentMethod]}
                            </td>
                        </tr>
                        {company.vat_enabled && (
                            <tr>
                                <th
                                    scope="row"
                                    colSpan={3}
                                    className="hidden pl-4 pr-3 pt-4 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0"
                                >
                                    PDV (20%)
                                </th>
                                <th
                                    scope="row"
                                    className="pl-4 pr-3 pt-4 text-left text-sm font-normal text-gray-500 sm:hidden"
                                >
                                    PDV (20%)
                                </th>
                                <td className="pl-3 pr-4 pt-4 text-right text-sm text-gray-500 sm:pr-0">
                                    {formatCurrency(vatAmount, resolvedCurrency)}
                                </td>
                            </tr>
                        )}
                        <tr>
                            <th
                                scope="row"
                                colSpan={3}
                                className="hidden pl-4 pr-3 pt-4 text-right text-base font-bold text-gray-900 sm:table-cell sm:pl-0"
                            >
                                Ukupno za plaćanje
                            </th>
                            <th
                                scope="row"
                                className="pl-4 pr-3 pt-4 text-left text-base font-bold text-gray-900 sm:hidden"
                            >
                                Ukupno
                            </th>
                            <td className="pl-3 pr-4 pt-4 text-right text-base font-bold text-gray-900 sm:pr-0">
                                {formatCurrency(totalWithVat, resolvedCurrency)}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                <div className="mt-12 pt-8 border-t border-gray-100">
                    <p className="text-gray-500 text-sm">
                        Hvala vam na poslovanju! Molimo vas da iznos uplatite u roku od{' '}
                        {format(new Date(invoice.dueDate), 'dd. MMM yyyy', {locale: srLatn})}.
                    </p>
                </div>
            </div>
        </div>
    );
}
