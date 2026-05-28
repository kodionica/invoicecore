import React, {useState} from 'react';
import {
    Building2,
    Users,
    FileText,
    ArrowUpRight,
    ArrowDownRight,
    DollarSign
} from 'lucide-react';
import {Client, Invoice, useApp} from '../context/AppContext';
import {BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend} from 'recharts';
import {formatCurrency} from "../utils/format";
import {getInvoiceStatusLabelMap, getInvoiceStatusOptions, invoiceStatusBadgeClass} from '../utils/invoiceStatus';
import {useNavigate} from "react-router";

function StatsCard({title, value, subValue, icon: Icon, trend, trendValue}: { title: string, value: string, subValue?: string, icon: any, trend?: 'up' | 'down', trendValue?: string }) {
    return (
        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-start justify-between">
            <div>
                <p className="text-sm font-medium text-gray-500">{title}</p>
                <h3 className="text-2xl font-bold text-gray-900 mt-2">{value}</h3>
                {subValue && (
                    <p className="text-xs text-gray-500 mt-2">{subValue}</p>
                )}
                {trend && (
                    <div className={`flex items-center mt-2 text-sm ${trend === 'up' ? 'text-green-600' : 'text-red-600'}`}>
                        {trend === 'up' ? <ArrowUpRight className="h-4 w-4 mr-1"/> : <ArrowDownRight className="h-4 w-4 mr-1"/>}
                        <span className="font-medium">{trendValue}</span>
                        <span className="text-gray-400 ml-1">vs prošli mesec</span>
                    </div>
                )}
            </div>
            <div className="p-3 bg-indigo-50 rounded-lg">
                <Icon className="h-6 w-6 text-indigo-600"/>
            </div>
        </div>
    );
}

export default function Dashboard() {
    const {companies, clients, invoices, activeCompanyId, meta} = useApp();
    const [revenueView, setRevenueView] = useState<'rsd' | 'original'>('rsd');
    const today = new Date();
    const navigate = useNavigate();

    // Filter data for active company
    const activeCompany = activeCompanyId ? companies.find(company => company.id === activeCompanyId) : undefined;
    const defaultCurrency = activeCompany?.currency ?? 'RSD';
    const activeClients: Client[] = activeCompanyId ? clients.filter(c => c.companyId === activeCompanyId) : [];
    const activeInvoices: Invoice[] = activeCompanyId ? invoices.filter(i => i.companyId === activeCompanyId) : [];
    const defaultCurrencyInvoices = activeInvoices.filter(invoice => (invoice.currency ?? defaultCurrency) === defaultCurrency);
    const getInvoiceAmount = (invoice: Invoice) => (
        revenueView === 'rsd' ? (invoice.totalRsd ?? invoice.total) : invoice.total
    );
    const primaryCurrency = revenueView === 'rsd' ? 'RSD' : defaultCurrency;

    const startOfCurrentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const startOfNextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
    const startOfPreviousMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);

    const isWithinRange = (dateValue: string | undefined, start: Date, end: Date) => {
        if (!dateValue) return false;
        const parsed = new Date(dateValue);
        if (Number.isNaN(parsed.getTime())) return false;
        return parsed >= start && parsed < end;
    };

    const buildLastMonths = (count: number) => {
        const months: Array<{ key: string; name: string; date: Date }> = [];
        for (let i = count - 1; i >= 0; i -= 1) {
            const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
            const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const name = d.toLocaleString('sr-RS', {month: 'short'});
            months.push({key, name, date: d});
        }
        return months;
    };

    const currentMonthClientsCount = activeClients.filter(client =>
        isWithinRange(client.createdAt, startOfCurrentMonth, startOfNextMonth)
    ).length;

    const previousMonthClientsCount = activeClients.filter(client =>
        isWithinRange(client.createdAt, startOfPreviousMonth, startOfCurrentMonth)
    ).length;

    const getPercentChange = (current: number, previous: number) => {
        if (previous === 0) {
            return current === 0 ? 0 : (current - previous) / 1 * 100;
        }
        return ((current - previous) / previous) * 100;
    };

    const clientPercentChange = getPercentChange(currentMonthClientsCount, previousMonthClientsCount);
    const clientTrend: 'up' | 'down' = clientPercentChange >= 0 ? 'up' : 'down';
    const showClientTrend = currentMonthClientsCount > 0 || previousMonthClientsCount > 0;
    const clientTrendValue = `${clientPercentChange >= 0 ? '+' : ''}${clientPercentChange.toFixed(1)}%`;

    const currentMonthSentInvoicesCount = activeInvoices.filter(invoice =>
        invoice.status === 'sent' && isWithinRange(invoice.date, startOfCurrentMonth, startOfNextMonth)
    ).length;
    const previousMonthSentInvoicesCount = activeInvoices.filter(invoice =>
        invoice.status === 'sent' && isWithinRange(invoice.date, startOfPreviousMonth, startOfCurrentMonth)
    ).length;
    const sentInvoicesPercentChange = getPercentChange(currentMonthSentInvoicesCount, previousMonthSentInvoicesCount);
    const sentInvoicesTrend: 'up' | 'down' = sentInvoicesPercentChange >= 0 ? 'up' : 'down';
    const showSentInvoicesTrend = currentMonthSentInvoicesCount > 0 || previousMonthSentInvoicesCount > 0;
    const sentInvoicesTrendValue = `${sentInvoicesPercentChange >= 0 ? '+' : ''}${sentInvoicesPercentChange.toFixed(1)}%`;

    // Calculate stats
    const currentMonthRevenue = (revenueView === 'rsd' ? activeInvoices : defaultCurrencyInvoices)
        .filter(invoice => isWithinRange(invoice.date, startOfCurrentMonth, startOfNextMonth))
        .reduce((acc, inv) => acc + getInvoiceAmount(inv), 0);
    const previousMonthRevenue = (revenueView === 'rsd' ? activeInvoices : defaultCurrencyInvoices)
        .filter(invoice => isWithinRange(invoice.date, startOfPreviousMonth, startOfCurrentMonth))
        .reduce((acc, inv) => acc + getInvoiceAmount(inv), 0);
    const revenuePercentChange = getPercentChange(currentMonthRevenue, previousMonthRevenue);
    const revenueTrend: 'up' | 'down' = revenuePercentChange >= 0 ? 'up' : 'down';
    const showRevenueTrend = currentMonthRevenue > 0 || previousMonthRevenue > 0;
    const revenueTrendValue = `${revenuePercentChange >= 0 ? '+' : ''}${revenuePercentChange.toFixed(1)}%`;

    const paidRevenue = (revenueView === 'rsd' ? activeInvoices : defaultCurrencyInvoices)
        .filter(i => i.status === 'paid')
        .reduce((acc, inv) => acc + getInvoiceAmount(inv), 0);
    const pendingRevenue = (revenueView === 'rsd' ? activeInvoices : defaultCurrencyInvoices)
        .filter(i => i.status === 'draft' || i.status === 'sent')
        .reduce((acc, inv) => acc + getInvoiceAmount(inv), 0);
    const overdueRevenue = (revenueView === 'rsd' ? activeInvoices : defaultCurrencyInvoices)
        .filter(i => i.status === 'overdue')
        .reduce((acc, inv) => acc + getInvoiceAmount(inv), 0);

    const otherCurrencyTotals = activeInvoices
        .filter(invoice => isWithinRange(invoice.date, startOfCurrentMonth, startOfNextMonth))
        .reduce<Record<string, number>>((acc, invoice) => {
        const currency = invoice.currency ?? defaultCurrency;
        if (currency === defaultCurrency) return acc;
        acc[currency] = (acc[currency] ?? 0) + invoice.total;
        return acc;
    }, {});
    const otherCurrencySummary = Object.entries(otherCurrencyTotals)
        .map(([currency, total]) => formatCurrency(total, currency))
        .join(' • ');
    const hasOtherCurrencies = revenueView === 'original' && otherCurrencySummary.length > 0;

    const monthBuckets = buildLastMonths(12);
    const currencyCodes = revenueView === 'rsd'
        ? ['RSD']
        : Array.from(new Set(activeInvoices.map(inv => inv.currency ?? defaultCurrency))).sort();
    const revenueByMonth = monthBuckets.map(bucket => {
        const row: Record<string, string | number> = {name: bucket.name, key: bucket.key};
        currencyCodes.forEach(code => {
            row[code] = 0;
        });
        return row;
    });
    const monthIndexMap = new Map(monthBuckets.map((bucket, index) => [bucket.key, index]));
    activeInvoices.forEach(invoice => {
        if (!invoice.date) return;
        const parsed = new Date(invoice.date);
        if (Number.isNaN(parsed.getTime())) return;
        const key = `${parsed.getFullYear()}-${String(parsed.getMonth() + 1).padStart(2, '0')}`;
        const index = monthIndexMap.get(key);
        if (index === undefined) return;
        const currency = revenueView === 'rsd' ? 'RSD' : (invoice.currency ?? defaultCurrency);
        const current = Number(revenueByMonth[index][currency] ?? 0);
        revenueByMonth[index][currency] = current + getInvoiceAmount(invoice);
    });
    const currencyColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#22c55e', '#f97316'];

    const pieData = [
        {name: 'Plaćeno', value: paidRevenue},
        {name: 'Neplaćeno', value: pendingRevenue},
        {name: 'Kasni', value: overdueRevenue},
    ];
    const COLORS = ['#10b981', '#f59e0b', '#fb2c36'];
    const statusOptions = getInvoiceStatusOptions(meta?.invoice_statuses);
    const statusLabelMap = getInvoiceStatusLabelMap(statusOptions);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-gray-900">Kontrolna Tabla</h1>
                <div className="flex space-x-2">
                    <span className="text-sm text-gray-500">Pregled za aktivnu firmu</span>
                </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <StatsCard
                    title="Ukupno Firmi"
                    value={companies.length.toString()}
                    icon={Building2}
                />
                <StatsCard
                    title="Klijenti (ovaj mesec)"
                    value={currentMonthClientsCount.toString()}
                    icon={Users}
                    trend={showClientTrend ? clientTrend : undefined}
                    trendValue={showClientTrend ? clientTrendValue : undefined}
                />
                <StatsCard
                    title="Ukupan Prihod"
                    value={formatCurrency(currentMonthRevenue, primaryCurrency)}
                    subValue={hasOtherCurrencies ? `Ostale valute (ovaj mesec): ${otherCurrencySummary}` : undefined}
                    icon={DollarSign}
                    trend={showRevenueTrend ? revenueTrend : undefined}
                    trendValue={showRevenueTrend ? revenueTrendValue : undefined}
                />
                <StatsCard
                    title="Poslate Fakture"
                    value={currentMonthSentInvoicesCount.toString()}
                    icon={FileText}
                    trend={showSentInvoicesTrend ? sentInvoicesTrend : undefined}
                    trendValue={showSentInvoicesTrend ? sentInvoicesTrendValue : undefined}
                />
            </div>

            {/* Charts Section */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Revenue Chart */}
                <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-bold text-gray-900">Prihod kroz vreme</h3>
                        <div className="inline-flex rounded-md border border-gray-200 p-1 text-xs">
                            <button
                                type="button"
                                onClick={() => setRevenueView('rsd')}
                                className={`rounded px-2 py-1 ${revenueView === 'rsd' ? 'bg-indigo-600 text-white' : 'text-gray-600'}`}
                            >
                                RSD
                            </button>
                            <button
                                type="button"
                                onClick={() => setRevenueView('original')}
                                className={`rounded px-2 py-1 ${revenueView === 'original' ? 'bg-indigo-600 text-white' : 'text-gray-600'}`}
                            >
                                Original
                            </button>
                        </div>
                    </div>
                    <div className="h-80 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={revenueByMonth}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false}/>
                                <XAxis dataKey="name" axisLine={false} tickLine={false}/>
                                <YAxis
                                    axisLine={false}
                                    tickLine={false}
                                    tickFormatter={(value) => Number(value).toLocaleString('sr-RS')}
                                />
                                <Tooltip
                                    formatter={(value: number, name: string) => formatCurrency(Number(value), name)}
                                />
                                <Legend />
                                {currencyCodes.map((code, index) => (
                                    <Bar
                                        key={code}
                                        dataKey={code}
                                        stackId="total"
                                        fill={currencyColors[index % currencyColors.length]}
                                        radius={[4, 4, 0, 0]}
                                    />
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Invoice Status */}
                <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 className="text-lg font-bold text-gray-900 mb-4">Status Faktura</h3>
                    <div className="h-64 w-full flex items-center justify-center">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={pieData}
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={60}
                                    outerRadius={80}
                                    paddingAngle={3}
                                    minAngle={3}
                                    dataKey="value"
                                >
                                    {pieData.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]}/>
                                    ))}
                                </Pie>
                                <Tooltip/>
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                    <div className="mt-4 space-y-2">
                        {pieData.map((entry, index) => (
                            <div key={entry.name} className="flex justify-between items-center text-sm">
                                <span className="flex items-center">
                                    <span
                                        className="w-3 h-3 rounded-full mr-2"
                                        style={{backgroundColor: COLORS[index % COLORS.length]}}
                                    ></span>
                                    {entry.name}
                                </span>
                                <span className="font-bold">{formatCurrency(entry.value, primaryCurrency)}</span>
                            </div>
                        ))}
                        {hasOtherCurrencies && (
                            <div className="text-xs text-gray-500 pt-2 border-t border-gray-100">
                                Ostale valute nisu uključene: {otherCurrencySummary}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Recent Invoices */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 className="text-lg font-bold text-gray-900">Nedavne Fakture</h3>
                    <button className="text-sm text-indigo-600 hover:text-indigo-700 font-medium">Vidi sve</button>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Broj</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Klijent</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum izdavanja</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rok za plaćanje</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Iznos</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                        {activeInvoices.slice(0, 5).map((invoice) => {
                            const client = clients.find(c => c.id === invoice.clientId);
                            return (
                                <tr key={invoice.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <button onClick={() => navigate(`/dashboard/invoices/${invoice.id}`)}>#{invoice.number}</button>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{client?.name || 'Nepoznat'}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(invoice.date).toLocaleDateString()}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(invoice.dueDate).toLocaleDateString()}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold">
                                        {formatCurrency(getInvoiceAmount(invoice), revenueView === 'rsd' ? 'RSD' : invoice.currency)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${invoiceStatusBadgeClass[invoice.status]}`}>
                        {statusLabelMap[invoice.status] ?? invoice.status}
                      </span>
                                    </td>
                                </tr>
                            );
                        })}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
