import React from 'react';
import {
    Building2,
    Users,
    FileText,
    TrendingUp,
    ArrowUpRight,
    ArrowDownRight,
    DollarSign
} from 'lucide-react';
import {useApp} from '../context/AppContext';
import {BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell} from 'recharts';
import {formatCurrency} from "../utils/format";
import {getInvoiceStatusLabelMap, getInvoiceStatusOptions, invoiceStatusBadgeClass} from '../utils/invoiceStatus';

function StatsCard({title, value, icon: Icon, trend, trendValue}: { title: string, value: string, icon: any, trend?: 'up' | 'down', trendValue?: string }) {
    return (
        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-start justify-between">
            <div>
                <p className="text-sm font-medium text-gray-500">{title}</p>
                <h3 className="text-2xl font-bold text-gray-900 mt-2">{value}</h3>
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
    const today = new Date();

    // Filter data for active company
    const activeClients: Client[] = activeCompanyId ? clients.filter(c => c.companyId === activeCompanyId) : [];
    const activeInvoices: Invoice[] = activeCompanyId ? invoices.filter(i => i.companyId === activeCompanyId) : [];

    const startOfCurrentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const startOfNextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
    const startOfPreviousMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);

    const isWithinRange = (dateValue: string | undefined, start: Date, end: Date) => {
        if (!dateValue) return false;
        const parsed = new Date(dateValue);
        if (Number.isNaN(parsed.getTime())) return false;
        return parsed >= start && parsed < end;
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

    // Calculate stats
    const totalRevenue = activeInvoices.reduce((acc, inv) => acc + inv.total, 0);
    const paidRevenue = activeInvoices.filter(i => i.status === 'paid').reduce((acc, inv) => acc + inv.total, 0);
    const pendingRevenue = activeInvoices.filter(i => i.status !== 'paid').reduce((acc, inv) => acc + inv.total, 0);

    const data = [
        {name: 'Jan', uv: 4000, pv: 2400, amt: 2400},
        {name: 'Feb', uv: 3000, pv: 1398, amt: 2210},
        {name: 'Mar', uv: 2000, pv: 9800, amt: 2290},
        {name: 'Apr', uv: 2780, pv: 3908, amt: 2000},
        {name: 'May', uv: 1890, pv: 4800, amt: 2181},
        {name: 'Jun', uv: 2390, pv: 3800, amt: 2500},
        {name: 'Jul', uv: 3490, pv: 4300, amt: 2100},
    ];

    const pieData = [
        {name: 'Plaćeno', value: paidRevenue},
        {name: 'Neplaćeno', value: pendingRevenue},
    ];
    const COLORS = ['#10b981', '#f59e0b'];
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
                    value={`€${totalRevenue.toLocaleString()}`}
                    icon={DollarSign}
                    trend="up"
                    trendValue="+8.2%"
                />
                <StatsCard
                    title="Poslate Fakture"
                    value={activeInvoices.length.toString()}
                    icon={FileText}
                />
            </div>

            {/* Charts Section */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Revenue Chart */}
                <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
                    <h3 className="text-lg font-bold text-gray-900 mb-4">Prihod kroz vreme</h3>
                    <div className="h-80 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={data}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false}/>
                                <XAxis dataKey="name" axisLine={false} tickLine={false}/>
                                <YAxis axisLine={false} tickLine={false} tickFormatter={(value) => `€${value}`}/>
                                <Tooltip/>
                                <Bar dataKey="pv" fill="#4f46e5" radius={[4, 4, 0, 0]}/>
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
                                    paddingAngle={5}
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
                        <div className="flex justify-between items-center text-sm">
                            <span className="flex items-center"><span className="w-3 h-3 bg-emerald-500 rounded-full mr-2"></span>Plaćeno</span>
                            <span className="font-bold">€{paidRevenue.toLocaleString()}</span>
                        </div>
                        <div className="flex justify-between items-center text-sm">
                            <span className="flex items-center"><span className="w-3 h-3 bg-amber-500 rounded-full mr-2"></span>Neplaćeno</span>
                            <span className="font-bold">€{pendingRevenue.toLocaleString()}</span>
                        </div>
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
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#{invoice.number}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{client?.name || 'Nepoznat'}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(invoice.date).toLocaleDateString()}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(invoice.dueDate).toLocaleDateString()}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold">{formatCurrency(invoice.total, invoice.currency)}</td>
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
