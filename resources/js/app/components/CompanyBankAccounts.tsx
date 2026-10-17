import React, {useEffect, useState} from 'react';
import {Copy, Pencil, Plus, Star} from 'lucide-react';
import {toast} from 'sonner';
import {useApp, type CompanyBankAccount, type CompanyBankAccountPayload} from '../context/AppContext';
import {LoadingButton} from './Loading';

const emptyDraft: CompanyBankAccountPayload = {
    label: '', bank_name: '', account_number: '', iban: '', swift_bic: '', currency: 'RSD',
    account_type: 'domestic', payment_instructions: '', is_default: false, is_active: true,
};

export default function CompanyBankAccounts({companyId, accounts}: {companyId: number; accounts: CompanyBankAccount[]}) {
    const {createBankAccount, updateBankAccount, setDefaultBankAccount, deactivateBankAccount} = useApp();
    const [draft, setDraft] = useState<CompanyBankAccountPayload>(emptyDraft);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [copyMode, setCopyMode] = useState(false);
    const [formOpen, setFormOpen] = useState(accounts.length === 0);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!editingId) return;
        const account = accounts.find(item => item.id === editingId);
        if (account) setDraft(toDraft(account));
    }, [accounts, editingId]);

    const reset = () => {
        setDraft(emptyDraft);
        setEditingId(null);
        setCopyMode(false);
        setFormOpen(false);
    };

    const beginCopy = (account: CompanyBankAccount) => {
        setDraft(toDraft(account));
        setEditingId(null);
        setCopyMode(true);
        setFormOpen(true);
    };

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (saving) return;
        if (!draft.bank_name.trim() || !draft.account_number.trim() || !draft.currency) {
            toast.error('Banka, broj računa i valuta su obavezni.');
            return;
        }
        setSaving(true);
        try {
            if (editingId) {
                await updateBankAccount(companyId, editingId, draft);
                toast.success('Bankovni račun je ažuriran.');
            } else {
                await createBankAccount(companyId, draft);
                toast.success(copyMode ? 'Kopija računa je sačuvana.' : 'Bankovni račun je dodat.');
            }
            reset();
        } catch (error: any) {
            toast.error(error?.response?.data?.message ?? 'Čuvanje bankovnog računa nije uspelo.');
        } finally {
            setSaving(false);
        }
    };

    return <section className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div className="flex items-center justify-between gap-3">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">Bankovni računi</h2>
                <p className="text-sm text-gray-500">Računi se biraju prema valuti fakture; stari računi ostaju sačuvani na istorijskim fakturama.</p>
            </div>
            {!editingId && !copyMode && <button type="button" onClick={() => {setDraft({...emptyDraft}); setFormOpen(true);}} className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white"><Plus className="mr-2 h-4 w-4"/> Dodaj račun</button>}
        </div>

        {accounts.length === 0 && <p className="mt-4 text-sm text-gray-500">Još nema dodatih računa.</p>}
        <div className="mt-4 grid gap-3 md:grid-cols-2">
            {accounts.map(account => <div key={account.id} className={`rounded-md border p-4 ${account.isActive ? 'border-gray-200' : 'border-gray-200 bg-gray-50 opacity-70'}`}>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="font-medium text-gray-900">{account.label || account.bankName}</p>
                        <p className="text-sm text-gray-500">{account.currency} · {account.accountType === 'foreign' ? 'Devizni' : 'Domaći'}</p>
                    </div>
                    {account.isDefault && <span className="inline-flex items-center text-xs font-medium text-amber-700"><Star className="mr-1 h-3 w-3 fill-current"/> Podrazumevani</span>}
                </div>
                <p className="mt-2 text-sm text-gray-700">{account.bankName} · {account.accountNumber}</p>
                {account.iban && <p className="text-xs text-gray-500">IBAN: {account.iban}</p>}
                {account.swiftBic && <p className="text-xs text-gray-500">SWIFT/BIC: {account.swiftBic}</p>}
                <div className="mt-3 flex flex-wrap gap-2">
                    {account.isActive && !account.isDefault && <button type="button" onClick={() => void setDefaultBankAccount(companyId, account.id)} className="text-xs text-indigo-700">Postavi kao podrazumevani</button>}
                    <button type="button" onClick={() => {setEditingId(account.id); setCopyMode(false); setFormOpen(true);}} className="inline-flex items-center text-xs text-gray-700"><Pencil className="mr-1 h-3 w-3"/> Izmeni</button>
                    <button type="button" onClick={() => beginCopy(account)} className="inline-flex items-center text-xs text-gray-700"><Copy className="mr-1 h-3 w-3"/> Kopiraj račun</button>
                    {account.isActive
                        ? <button type="button" onClick={() => void deactivateBankAccount(companyId, account.id)} className="text-xs text-red-700">Deaktiviraj</button>
                        : <button type="button" onClick={() => void updateBankAccount(companyId, account.id, {...toDraft(account), is_active: true})} className="text-xs text-green-700">Aktiviraj</button>}
                </div>
            </div>)}
        </div>

        {(formOpen || editingId || copyMode) && <form onSubmit={submit} className="mt-6 grid gap-3 border-t pt-5 md:grid-cols-2">
            <h3 className="md:col-span-2 font-medium text-gray-900">{editingId ? 'Izmeni račun' : copyMode ? 'Kopiraj račun' : 'Novi račun'}</h3>
            <input value={draft.label ?? ''} onChange={e => setDraft({...draft, label: e.target.value})} placeholder="Naziv / oznaka" className="rounded-md border px-3 py-2 text-sm" />
            <input disabled={copyMode} value={draft.bank_name} onChange={e => setDraft({...draft, bank_name: e.target.value})} placeholder="Naziv banke *" className="rounded-md border px-3 py-2 text-sm disabled:bg-gray-100" />
            <input disabled={copyMode} value={draft.account_number} onChange={e => setDraft({...draft, account_number: e.target.value})} placeholder="Broj računa *" className="rounded-md border px-3 py-2 text-sm disabled:bg-gray-100" />
            <input disabled={copyMode} value={draft.iban ?? ''} onChange={e => setDraft({...draft, iban: e.target.value})} placeholder="IBAN" className="rounded-md border px-3 py-2 text-sm disabled:bg-gray-100" />
            <input value={draft.swift_bic ?? ''} onChange={e => setDraft({...draft, swift_bic: e.target.value})} placeholder="SWIFT/BIC" className="rounded-md border px-3 py-2 text-sm" />
            <select disabled={copyMode} value={draft.currency} onChange={e => setDraft({...draft, currency: e.target.value})} className="rounded-md border px-3 py-2 text-sm disabled:bg-gray-100"><option>RSD</option><option>EUR</option><option>USD</option><option>GBP</option><option>CAD</option></select>
            <select disabled={copyMode} value={draft.account_type} onChange={e => setDraft({...draft, account_type: e.target.value as 'domestic' | 'foreign'})} className="rounded-md border px-3 py-2 text-sm disabled:bg-gray-100"><option value="domestic">Domaći</option><option value="foreign">Devizni</option></select>
            <textarea disabled={copyMode} value={draft.payment_instructions ?? ''} onChange={e => setDraft({...draft, payment_instructions: e.target.value})} placeholder="Dodatne instrukcije za plaćanje" className="rounded-md border px-3 py-2 text-sm md:col-span-2 disabled:bg-gray-100" rows={3}/>
            {!copyMode && <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={draft.is_default === true} onChange={e => setDraft({...draft, is_default: e.target.checked})}/> Podrazumevani za ovu valutu</label>}
            <div className="flex gap-2 md:col-span-2"><LoadingButton type="submit" loading={saving} loadingLabel="Čuvanje..." className="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white">Sačuvaj</LoadingButton><button type="button" onClick={reset} className="rounded-md border px-3 py-2 text-sm">Otkaži</button></div>
        </form>}
    </section>;
}

function toDraft(account: CompanyBankAccount): CompanyBankAccountPayload {
    return {
        label: account.label ?? '', bank_name: account.bankName, account_number: account.accountNumber,
        iban: account.iban ?? '', swift_bic: account.swiftBic ?? '', currency: account.currency,
        account_type: account.accountType, payment_instructions: account.paymentInstructions ?? '',
        is_default: account.isDefault, is_active: account.isActive,
    };
}
