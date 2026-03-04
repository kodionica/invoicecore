import React from 'react';
import {NavLink, Outlet, useNavigate} from 'react-router';
import {
    LayoutDashboard,
    Users,
    FileText,
    Building2,
    Settings,
    LogOut,
    Plus,
    ChevronDown,
    Bell,
    Search,
    Menu
} from 'lucide-react';
import {useApp} from '../context/AppContext';
import clsx from 'clsx';

function SimpleDropdown({
                            trigger,
                            children,
                            align = 'right'
                        }: {
    trigger: React.ReactNode,
    children: React.ReactNode,
    align?: 'left' | 'right'
}) {
    const [isOpen, setIsOpen] = React.useState(false);
    const dropdownRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }

        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, [dropdownRef]);

    return (
        <div className="relative" ref={dropdownRef}>
            <div onClick={() => setIsOpen(!isOpen)} className="cursor-pointer">
                {trigger}
            </div>
            {isOpen && (
                <div className={clsx(
                    "absolute top-full mt-2 w-48 bg-white rounded-md shadow-lg py-1 ring-1 ring-black ring-opacity-5 z-50",
                    align === 'right' ? "right-0" : "left-0"
                )}>
                    {children}
                </div>
            )}
        </div>
    );
}

export default function DashboardLayout() {
    const {user, authLoading, companies, activeCompanyId, setActiveCompany, logout} = useApp();
    const navigate = useNavigate();
    const activeCompany = activeCompanyId ? companies.find(c => c.id === activeCompanyId) : undefined;
    const [mobileMenuOpen, setMobileMenuOpen] = React.useState(false);

    React.useEffect(() => {
        if (!authLoading && !user) {
            navigate('/');
        }
    }, [authLoading, user, navigate]);

    if (authLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center text-sm text-gray-500">
                Učitavanje...
            </div>
        );
    }

    if (!user) {
        return null;
    }

    const navItems = [
        {to: "/dashboard", icon: LayoutDashboard, label: "Kontrolna Tabla", end: true},
        {to: "/dashboard/clients", icon: Users, label: "Klijenti"},
        {to: "/dashboard/invoices", icon: FileText, label: "Fakture"},
        {to: "/dashboard/companies", icon: Building2, label: "Moje Firme"},
    ];

    return (
        <div className="min-h-screen bg-gray-50 flex">
            {/* Sidebar - Desktop */}
            <aside className="hidden md:flex flex-col w-64 bg-slate-900 text-white fixed h-full z-20">
                <div className="p-6 flex items-center space-x-3">
                    <div className="h-8 w-8 bg-indigo-500 rounded-lg flex items-center justify-center">
                        <span className="font-bold text-xl">IC</span>
                    </div>
                    <span className="text-xl font-bold tracking-tight">InvoiceCore</span>
                </div>

                <nav className="flex-1 px-4 space-y-1 mt-6">
                    {navItems.map((item) => (
                        <NavLink
                            key={item.to}
                            to={item.to}
                            end={item.end}
                            className={({isActive}) => clsx(
                                "flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors",
                                isActive
                                    ? "bg-indigo-600 text-white"
                                    : "text-slate-300 hover:bg-slate-800 hover:text-white"
                            )}
                        >
                            <item.icon className="mr-3 h-5 w-5"/>
                            {item.label}
                        </NavLink>
                    ))}
                </nav>

                <div className="p-4 border-t border-slate-800">
                    <NavLink
                        to="/dashboard/settings"
                        className={({isActive}) => clsx(
                            "flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors",
                            isActive ? "bg-indigo-600 text-white" : "text-slate-300 hover:bg-slate-800 hover:text-white"
                        )}
                    >
                        <Settings className="mr-3 h-5 w-5"/>
                        Podešavanja
                    </NavLink>
                </div>
            </aside>

            {/* Main Content */}
            <div className="flex-1 md:ml-64 flex flex-col min-w-0 overflow-hidden">
                {/* Header */}
                <header className="bg-white shadow-sm z-10 sticky top-0 print:hidden">
                    <div className="px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">

                        {/* Left: Mobile Menu & Company Switcher */}
                        <div className="flex items-center gap-4">
                            <button
                                className="md:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100"
                                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            >
                                <Menu className="h-6 w-6"/>
                            </button>

                            <SimpleDropdown
                                align="left"
                                trigger={
                                    <button className="flex items-center gap-2 px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors border border-gray-200">
                                        <div className="h-6 w-6 rounded bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs">
                                            {activeCompany?.logoUrl ? <img src={activeCompany.logoUrl} alt="logo"/> : activeCompany?.name.substring(0, 2).toUpperCase()}
                                        </div>
                                        <span className="text-sm font-medium text-gray-700 hidden sm:block">
                      {activeCompany?.name || "Izaberi firmu"}
                    </span>
                                        <ChevronDown className="h-4 w-4 text-gray-500"/>
                                    </button>
                                }
                            >
                                <div className="py-1">
                                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Moje Firme
                                    </div>
                                    {companies.map(company => (
                                        <button
                                            key={company.id}
                                            onClick={() => setActiveCompany(company.id)}
                                            className={clsx(
                                                "w-full text-left px-4 py-2 text-sm",
                                                activeCompanyId === company.id ? "bg-indigo-50 text-indigo-700 font-medium" : "text-gray-700 hover:bg-gray-100"
                                            )}
                                        >
                                            {company.name}
                                        </button>
                                    ))}
                                    <div className="border-t border-gray-100 mt-1 pt-1">
                                        <button
                                            onClick={() => navigate('/dashboard/companies')}
                                            className="w-full text-left px-4 py-2 text-sm text-indigo-600 hover:bg-indigo-50 flex items-center"
                                        >
                                            <Plus className="h-3 w-3 mr-2"/> Nova Firma
                                        </button>
                                    </div>
                                </div>
                            </SimpleDropdown>
                        </div>

                        {/* Right: Actions & User Profile */}
                        <div className="flex items-center gap-3 sm:gap-4">

                            {/* Quick Actions */}
                            <SimpleDropdown
                                trigger={
                                    <button className="hidden sm:flex items-center gap-2 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors shadow-sm text-sm font-medium">
                                        <Plus className="h-4 w-4"/>
                                        <span className="hidden md:inline">Brza Akcija</span>
                                    </button>
                                }
                            >
                                <div className="py-1">
                                    <button
                                        onClick={() => navigate('/dashboard/invoices/new')}
                                        className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    >
                                        Nova Faktura
                                    </button>
                                    <button
                                        onClick={() => navigate('/dashboard/clients/new')}
                                        className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    >
                                        Novi Klijent
                                    </button>
                                </div>
                            </SimpleDropdown>

                            <button className="p-2 text-gray-400 hover:text-gray-500 relative">
                                <Bell className="h-5 w-5"/>
                                <span className="absolute top-1.5 right-1.5 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                            </button>

                            <div className="h-6 w-px bg-gray-200 mx-1"></div>

                            {/* User Profile */}
                            <SimpleDropdown
                                trigger={
                                    <div className="flex items-center gap-2">
                                        <img
                                            className="h-8 w-8 rounded-full border border-gray-200"
                                            src={user.avatarUrl}
                                            alt={user.name}
                                        />
                                        <div className="hidden md:block text-left">
                                            <p className="text-sm font-medium text-gray-700 leading-none">{user.name}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">Admin</p>
                                        </div>
                                        <ChevronDown className="h-4 w-4 text-gray-400"/>
                                    </div>
                                }
                            >
                                <div className="py-1">
                                    <div className="px-4 py-2 border-b border-gray-100">
                                        <p className="text-sm font-medium text-gray-900">{user.name}</p>
                                        <p className="text-xs text-gray-500 truncate">{user.email}</p>
                                    </div>
                                    <button onClick={() => navigate('/dashboard/settings')} className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                                        <Settings className="h-4 w-4 mr-2 text-gray-400"/> Podešavanja
                                    </button>
                                    <button
                                        onClick={async () => {
                                            try {
                                                await logout();
                                            } finally {
                                                navigate('/');
                                            }
                                        }}
                                        className="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center"
                                    >
                                        <LogOut className="h-4 w-4 mr-2"/> Odjavi se
                                    </button>
                                </div>
                            </SimpleDropdown>

                        </div>
                    </div>
                </header>

                {/* Mobile Sidebar Overlay */}
                {mobileMenuOpen && (
                    <div className="fixed inset-0 z-40 md:hidden" role="dialog" aria-modal="true">
                        <div className="fixed inset-0 bg-gray-600 bg-opacity-75 transition-opacity" onClick={() => setMobileMenuOpen(false)}></div>
                        <div className="relative flex-1 flex flex-col max-w-xs w-full bg-slate-900 h-full">
                            <div className="p-6 flex items-center justify-between">
                                <div className="flex items-center space-x-3 text-white">
                                    <div className="h-8 w-8 bg-indigo-500 rounded-lg flex items-center justify-center">
                                        <span className="font-bold text-xl">IC</span>
                                    </div>
                                    <span className="text-xl font-bold">InvoiceCore</span>
                                </div>
                                <button onClick={() => setMobileMenuOpen(false)} className="text-slate-400 hover:text-white">
                                    <ChevronDown className="h-6 w-6 rotate-90"/>
                                </button>
                            </div>
                            <nav className="flex-1 px-2 space-y-1 mt-6">
                                {navItems.map((item) => (
                                    <NavLink
                                        key={item.to}
                                        to={item.to}
                                        end={item.end}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={({isActive}) => clsx(
                                            "flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors",
                                            isActive
                                                ? "bg-indigo-600 text-white"
                                                : "text-slate-300 hover:bg-slate-800 hover:text-white"
                                        )}
                                    >
                                        <item.icon className="mr-3 h-5 w-5"/>
                                        {item.label}
                                    </NavLink>
                                ))}
                            </nav>
                        </div>
                    </div>
                )}

                {/* Page Content */}
                <main className="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                    <Outlet/>
                </main>
            </div>
        </div>
    );
}
