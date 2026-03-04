import React, { createContext, useContext, useEffect, useState } from 'react';
import axios from 'axios';
import { v4 as uuidv4 } from 'uuid';

// Types
export interface Company {
  id: string;
  name: string;
  pib: string;
  address: string;
  logoUrl?: string;
}

export interface Client {
  id: string;
  companyId: string; // The company that owns this client
  name: string;
  email: string;
  address: string;
  pib?: string;
}

export interface InvoiceItem {
  id: string;
  description: string;
  quantity: number;
  price: number;
}

export interface Invoice {
  id: string;
  companyId: string;
  clientId: string;
  number: string;
  date: string;
  dueDate: string;
  items: InvoiceItem[];
  status: 'draft' | 'sent' | 'paid' | 'overdue';
  total: number;
}

interface User {
  id: number | string;
  name: string;
  email: string;
  avatarUrl?: string;
}

interface LoginPayload {
  login: string;
  password: string;
  remember?: boolean;
}

interface RegisterPayload {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
}

interface AppContextType {
  user: User | null;
  authLoading: boolean;
  activeCompanyId: string | null;
  companies: Company[];
  clients: Client[];
  invoices: Invoice[];
  login: (payload: LoginPayload) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  updateUser: (updates: Partial<User>) => void;
  setActiveCompany: (id: string) => void;
  addCompany: (company: Omit<Company, 'id'>) => void;
  updateCompany: (id: string, data: Omit<Company, 'id'>) => void;
  deleteCompany: (id: string) => void;
  addClient: (client: Omit<Client, 'id'>) => void;
  addInvoice: (invoice: Omit<Invoice, 'id' | 'total'>) => void;
  updateInvoiceStatus: (id: string, status: Invoice['status']) => void;
}

const AppContext = createContext<AppContextType | undefined>(undefined);

const api = axios.create({
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
  },
});

const ensureCsrf = async () => {
  await api.get('/sanctum/csrf-cookie');
};

const normalizeUser = (apiUser: any): User => {
  const name =
    apiUser?.display_name ||
    [apiUser?.first_name, apiUser?.last_name].filter(Boolean).join(' ') ||
    apiUser?.username ||
    apiUser?.email ||
    'User';

  return {
    id: apiUser?.id ?? '',
    name,
    email: apiUser?.email ?? '',
    avatarUrl: apiUser?.avatar_url ?? apiUser?.avatarUrl,
  };
};

const MOCK_COMPANIES: Company[] = [
  { id: 'c1', name: 'Tech Solutions d.o.o.', pib: '101010101', address: 'Bulevar Oslobođenja 10, Novi Sad' },
  { id: 'c2', name: 'Marko Design PR', pib: '202020202', address: 'Kneza Miloša 5, Beograd' },
];

const MOCK_CLIENTS: Client[] = [
  { id: 'cl1', companyId: 'c1', name: 'Alpha Corp', email: 'contact@alpha.com', address: 'London, UK', pib: 'UK123456' },
  { id: 'cl2', companyId: 'c1', name: 'Beta Ltd', email: 'info@beta.com', address: 'Berlin, DE', pib: 'DE987654' },
  { id: 'cl3', companyId: 'c2', name: 'Gamma Shop', email: 'shop@gamma.com', address: 'Novi Beograd, SRB', pib: '123123123' },
];

const MOCK_INVOICES: Invoice[] = [
  {
    id: 'inv1',
    companyId: 'c1',
    clientId: 'cl1',
    number: '2023-001',
    date: '2023-10-01',
    dueDate: '2023-10-15',
    status: 'paid',
    items: [{ id: 'i1', description: 'Web Development', quantity: 1, price: 1000 }],
    total: 1000,
  },
  {
    id: 'inv2',
    companyId: 'c1',
    clientId: 'cl2',
    number: '2023-002',
    date: '2023-10-20',
    dueDate: '2023-11-04',
    status: 'sent',
    items: [{ id: 'i2', description: 'Consulting', quantity: 5, price: 100 }],
    total: 500,
  },
   {
    id: 'inv3',
    companyId: 'c2',
    clientId: 'cl3',
    number: '2023-101',
    date: '2023-11-01',
    dueDate: '2023-11-15',
    status: 'overdue',
    items: [{ id: 'i3', description: 'Logo Design', quantity: 1, price: 300 }],
    total: 300,
  },
];

const generateId = () => Math.random().toString(36).substr(2, 9);

export function AppProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [authLoading, setAuthLoading] = useState(true);
  const [companies, setCompanies] = useState<Company[]>(MOCK_COMPANIES);
  const [clients, setClients] = useState<Client[]>(MOCK_CLIENTS);
  const [invoices, setInvoices] = useState<Invoice[]>(MOCK_INVOICES);
  const [activeCompanyId, setActiveCompanyId] = useState<string | null>(MOCK_COMPANIES[0]?.id || null);

  const refreshUser = async () => {
    setAuthLoading(true);
    try {
      const response = await api.get('/api/user');
      setUser(normalizeUser(response.data));
    } catch (error: any) {
      if (error?.response?.status !== 401) {
        throw error;
      }
      setUser(null);
    } finally {
      setAuthLoading(false);
    }
  };

  useEffect(() => {
    refreshUser().catch(() => undefined);
  }, []);

  const login = async (payload: LoginPayload) => {
    await ensureCsrf();
    const response = await api.post('/api/login', payload);
    setUser(normalizeUser(response.data.user));
  };

  const register = async (payload: RegisterPayload) => {
    await ensureCsrf();
    const response = await api.post('/api/register', payload);
    setUser(normalizeUser(response.data.user));
  };

  const logout = async () => {
    await api.post('/api/logout');
    setUser(null);
  };

  const updateUser = (updates: Partial<User>) => {
    setUser(current => (current ? { ...current, ...updates } : current));
  };

  const setActiveCompany = (id: string) => {
    setActiveCompanyId(id);
  };

  const addCompany = (company: Omit<Company, 'id'>) => {
    const newCompany = { ...company, id: generateId() };
    setCompanies([...companies, newCompany]);
    if (!activeCompanyId) setActiveCompanyId(newCompany.id);
  };

  const updateCompany = (id: string, data: Omit<Company, 'id'>) => {
    setCompanies(companies.map(comp => comp.id === id ? { ...comp, ...data } : comp));
  };

  const deleteCompany = (id: string) => {
    setCompanies(companies.filter(comp => comp.id !== id));
    if (activeCompanyId === id) setActiveCompanyId(null);
  };

  const addClient = (client: Omit<Client, 'id'>) => {
    const newClient = { ...client, id: generateId() };
    setClients([...clients, newClient]);
  };

  const addInvoice = (invoice: Omit<Invoice, 'id' | 'total'>) => {
    const total = invoice.items.reduce((acc, item) => acc + (item.quantity * item.price), 0);
    const newInvoice = { ...invoice, id: generateId(), total };
    setInvoices([...invoices, newInvoice]);
  };

  const updateInvoiceStatus = (id: string, status: Invoice['status']) => {
    setInvoices(invoices.map(inv => inv.id === id ? { ...inv, status } : inv));
  };

  return (
    <AppContext.Provider value={{
      user,
      authLoading,
      activeCompanyId,
      companies,
      clients,
      invoices,
      login,
      register,
      logout,
      refreshUser,
      updateUser,
      setActiveCompany,
      addCompany,
      updateCompany,
      deleteCompany,
      addClient,
      addInvoice,
      updateInvoiceStatus
    }}>
      {children}
    </AppContext.Provider>
  );
}

export function useApp() {
  const context = useContext(AppContext);
  if (context === undefined) {
    throw new Error('useApp must be used within an AppProvider');
  }
  return context;
}
