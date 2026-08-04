import React, {useState} from 'react';
import {Invoice, useApp} from '../context/AppContext';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
    PieChart,
    Pie,
    Cell,
} from 'recharts';
import {formatCurrency} from '../utils/format';

type PeriodView = 'last12' | 'year';
type RevenueView = 'rsd' | 'original';

type MonthBucket = {key: string; name: string; date: Date};

const TOP_CLIENTS_IN_PIE = 6;
const currencyColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#22c55e', '#f97316'];
const clientColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#94a3b8'];

export default function Turnover() {
    const {companies, clients, invoices, activeCompanyId} = useApp();
    const [revenueView, setRevenueView] = useState<RevenueView>('rsd');
    const [periodView, setPeriodView] = useState<PeriodView>('last12');
    const today = new Date();

    const activeCompany = activeCompanyId ? companies.find(company => company.id === activeCompanyId) : undefined;
    const defaultCurrency = activeCompany?.currency ?? 'RSD';
    const activeInvoices: Invoice[] = activeCompanyId ? invoices.filter(i => i.companyId === activeCompanyId) : [];

    const getInvoiceAmount = (invoice: Invoice) => (
        revenueView === 'rsd' ? (invoice.totalRsd ?? invoice.total) : invoice.total
    );
    // Učešće klijenata uvek računamo u RSD da bi procenti bili uporedivi.
    const getShareAmount = (invoice: Invoice) => invoice.totalRsd ?? invoice.total;

    const buildLastMonths = (count: number): MonthBucket[] => {
        const months: MonthBucket[] = [];
        for (let i = count - 1; i >= 0; i -= 1) {
            const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
            const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const name = d.toLocaleString('sr-RS', {month: 'short', year: '2-digit'});
            months.push({key, name, date: d});
        }
        return months;
    };

    const buildCurrentYearMonths = (): MonthBucket[] => {
        const year = today.getFullYear();
        const months: MonthBucket[] = [];
        for (let month = 0; month < 12; month += 1) {
            const d = new Date(year, month, 1);
            const key = `${year}-${String(month + 1).padStart(2, '0')}`;
            const name = d.toLocaleString('sr-RS', {month: 'short'});
            months.push({key, name, date: d});
        }
        return months;
    };

    const monthBuckets = periodView === 'last12' ? buildLastMonths(12) : buildCurrentYearMonths();
    const periodStart = monthBuckets[0].date;
    const periodEnd = new Date(
        monthBuckets[monthBuckets.length - 1].date.getFullYear(),
        monthBuckets[monthBuckets.length - 1].date.getMonth() + 1,
        1
    );

    const invoicesInPeriod = activeInvoices.filter(invoice => {
        if (!invoice.date) return false;
        const parsed = new Date(invoice.date);
        if (Number.isNaN(parsed.getTime())) return false;
        return parsed >= periodStart && parsed < periodEnd;
    });

    const originalCurrencyCodes = Array.from(
        new Set(invoicesInPeriod.map(inv => inv.currency ?? defaultCurrency))
    ).sort();
    const currencyCodes = revenueView === 'rsd'
        ? ['RSD']
        : (originalCurrencyCodes.length > 0 ? originalCurrencyCodes : [defaultCurrency]);

    const revenueByMonth = monthBuckets.map(bucket => {
        const row: Record<string, string | number> = {name: bucket.name, key: bucket.key};
        currencyCodes.forEach(code => {
            row[code] = 0;
        });
        return row;
    });

    const monthIndexMap = new Map(monthBuckets.map((bucket, index) => [bucket.key, index]));
    invoicesInPeriod.forEach(invoice => {
        const parsed = new Date(invoice.date);
        const key = `${parsed.getFullYear()}-${String(parsed.getMonth() + 1).padStart(2, '0')}`;
        const index = monthIndexMap.get(key);
        if (index === undefined) return;
        const currency = revenueView === 'rsd' ? 'RSD' : (invoice.currency ?? defaultCurrency);
        const current = Number(revenueByMonth[index][currency] ?? 0);
        revenueByMonth[index][currency] = current + getInvoiceAmount(invoice);
    });

    const totalsByCurrency = currencyCodes.reduce<Record<string, number>>((acc, code) => {
        acc[code] = invoicesInPeriod
            .filter(invoice => (revenueView === 'rsd' ? true : (invoice.currency ?? defaultCurrency) === code))
            .reduce((sum, invoice) => sum + getInvoiceAmount(invoice), 0);
        return acc;
    }, {});

    const clientTotalsMap = invoicesInPeriod.reduce<Map<number, number>>((acc, invoice) => {
        const amount = getShareAmount(invoice);
        if (amount <= 0) return acc;
        acc.set(invoice.clientId, (acc.get(invoice.clientId) ?? 0) + amount);
        return acc;
    }, new Map());

    const clientTotalsSorted = Array.from(clientTotalsMap.entries())
        .map(([clientId, value]) => ({
            clientId,
            name: clients.find(c => c.id === clientId)?.name ?? 'Nepoznat klijent',
            value,
        }))
        .sort((a, b) => b.value - a.value);

    const clientShareTotal = clientTotalsSorted.reduce((sum, entry) => sum + entry.value, 0);
    const topClients = clientTotalsSorted.slice(0, TOP_CLIENTS_IN_PIE);
    const otherClients = clientTotalsSorted.slice(TOP_CLIENTS_IN_PIE);
    const otherClientsTotal = otherClients.reduce((sum, entry) => sum + entry.value, 0);
    const clientPieData = [
        ...topClients.map(entry => ({
            name: entry.name,
            value: entry.value,
            percent: clientShareTotal > 0 ? (entry.value / clientShareTotal) * 100 : 0,
        })),
        ...(otherClientsTotal > 0
            ? [{
                name: `Ostali (${otherClients.length})`,
                value: otherClientsTotal,
                percent: clientShareTotal > 0 ? (otherClientsTotal / clientShareTotal) * 100 : 0,
            }]
            : []),
    ];

    const periodLabel = periodView === 'last12'
        ? 'Poslednjih 12 meseci'
        : `Godina ${today.getFullYear()}`;

    const toggleButtonClass = (active: boolean) =>
        `rounded px-2.5 py-1 ${active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900'}`;

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Promet</h1>
                    <p className="text-sm text-gray-500 mt-1">Pregled za aktivnu firmu · {periodLabel}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <div className="inline-flex rounded-md border border-gray-200 p-1 text-xs">
                        <button
                            type="button"
                            onClick={() => setPeriodView('last12')}
                            className={toggleButtonClass(periodView === 'last12')}
                        >
                            Poslednjih 12 meseci
                        </button>
                        <button
                            type="button"
                            onClick={() => setPeriodView('year')}
                            className={toggleButtonClass(periodView === 'year')}
                        >
                            Trenutna godina
                        </button>
                    </div>
                    <div className="inline-flex rounded-md border border-gray-200 p-1 text-xs">
                        <button
                            type="button"
                            onClick={() => setRevenueView('rsd')}
                            className={toggleButtonClass(revenueView === 'rsd')}
                        >
                            RSD
                        </button>
                        <button
                            type="button"
                            onClick={() => setRevenueView('original')}
                            className={toggleButtonClass(revenueView === 'original')}
                        >
                            Original
                        </button>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {currencyCodes.map((code, index) => (
                    <div
                        key={code}
                        className="bg-white p-5 rounded-xl shadow-sm border border-gray-100"
                    >
                        <p className="text-sm font-medium text-gray-500">
                            Ukupan promet {currencyCodes.length > 1 ? `(${code})` : ''}
                        </p>
                        <h3 className="text-2xl font-bold text-gray-900 mt-2">
                            {formatCurrency(totalsByCurrency[code] ?? 0, code)}
                        </h3>
                        <div
                            className="mt-3 h-1 w-10 rounded-full"
                            style={{backgroundColor: currencyColors[index % currencyColors.length]}}
                        />
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
                    <div className="mb-4">
                        <h3 className="text-lg font-bold text-gray-900">Prihod po mesecima</h3>
                        <p className="text-sm text-gray-500 mt-1">
                            {revenueView === 'rsd'
                                ? 'Svi iznosi preračunati u dinare'
                                : 'Iznosi po originalnim valutama faktura'}
                        </p>
                    </div>
                    <div className="h-96 w-full">
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
                                <Legend/>
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

                <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div className="mb-4">
                        <h3 className="text-lg font-bold text-gray-900">Učešće klijenata</h3>
                        <p className="text-sm text-gray-500 mt-1">{periodLabel}</p>
                    </div>
                    {clientPieData.length === 0 ? (
                        <div className="h-64 flex items-center justify-center text-sm text-gray-500">
                            Nema prometa u izabranom periodu
                        </div>
                    ) : (
                        <>
                            <div className="h-64 w-full">
                                <ResponsiveContainer width="100%" height="100%">
                                    <PieChart>
                                        <Pie
                                            data={clientPieData}
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={55}
                                            outerRadius={85}
                                            paddingAngle={2}
                                            minAngle={3}
                                            dataKey="value"
                                            nameKey="name"
                                        >
                                            {clientPieData.map((entry, index) => (
                                                <Cell
                                                    key={entry.name}
                                                    fill={clientColors[index % clientColors.length]}
                                                />
                                            ))}
                                        </Pie>
                                        <Tooltip
                                            formatter={(value: number, _name, item) => {
                                                const percent = Number(item?.payload?.percent ?? 0);
                                                return [
                                                    `${formatCurrency(Number(value), 'RSD')} (${percent.toFixed(1)}%)`,
                                                    item?.payload?.name ?? '',
                                                ];
                                            }}
                                        />
                                    </PieChart>
                                </ResponsiveContainer>
                            </div>
                            <div className="mt-4 space-y-2 max-h-48 overflow-y-auto">
                                {clientPieData.map((entry, index) => (
                                    <div key={entry.name} className="flex justify-between items-center gap-3 text-sm">
                                        <span className="flex items-center min-w-0">
                                            <span
                                                className="w-3 h-3 rounded-full mr-2 shrink-0"
                                                style={{backgroundColor: clientColors[index % clientColors.length]}}
                                            />
                                            <span className="truncate">{entry.name}</span>
                                        </span>
                                        <span className="font-semibold text-gray-900 shrink-0">
                                            {entry.percent.toFixed(1)}%
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
