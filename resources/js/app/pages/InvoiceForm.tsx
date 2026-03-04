import React, { useState, useEffect } from 'react';
import { useForm, useFieldArray, Controller } from 'react-hook-form';
import { useNavigate } from 'react-router';
import { useApp, InvoiceItem } from '../context/AppContext';
import { toast } from 'sonner';
import { ArrowLeft, Plus, Trash2, Calendar, User } from 'lucide-react';
import { format } from 'date-fns';

interface InvoiceFormData {
  clientId: string;
  number: string;
  date: string;
  dueDate: string;
  items: { description: string; quantity: number; price: number }[];
}

export default function InvoiceForm() {
  const { clients, addInvoice, activeCompanyId, invoices } = useApp();
  const navigate = useNavigate();
  
  // Auto-generate invoice number (mock logic)
  const nextInvoiceNumber = `${new Date().getFullYear()}-${String(invoices.length + 101).padStart(3, '0')}`;

  const { register, control, handleSubmit, watch, setValue, formState: { errors } } = useForm<InvoiceFormData>({
    defaultValues: {
      number: nextInvoiceNumber,
      date: new Date().toISOString().split('T')[0],
      dueDate: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      items: [{ description: 'Usluga', quantity: 1, price: 100 }]
    }
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "items"
  });

  const watchItems = watch("items");
  const totalAmount = watchItems.reduce((sum, item) => sum + (Number(item.quantity) * Number(item.price)), 0);

  const onSubmit = async (data: InvoiceFormData) => {
    if (!activeCompanyId) {
      toast.error('Molimo izaberite aktivnu firmu');
      return;
    }

    await addInvoice({
      clientId: Number(data.clientId),
      date: data.date,
      dueDate: data.dueDate,
      items: data.items.map((item) => ({ ...item }))
    });
    
    toast.success('Faktura uspešno kreirana');
    navigate('/dashboard/invoices');
  };

  const activeClients = activeCompanyId ? clients.filter(c => c.companyId === activeCompanyId) : [];

  return (
    <div className="max-w-4xl mx-auto space-y-6 pb-12">
      <div className="flex items-center space-x-4">
        <button 
          onClick={() => navigate('/dashboard/invoices')}
          className="p-2 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-900 transition-colors"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="text-2xl font-bold text-gray-900">Nova Faktura</h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        
        {/* Header Info */}
        <div className="bg-white shadow rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="space-y-4">
             <div>
              <label className="block text-sm font-medium text-gray-700">Klijent</label>
              <div className="mt-1 relative rounded-md shadow-sm">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <User className="h-5 w-5 text-gray-400" />
                </div>
                <select
                  {...register("clientId", { required: "Izaberite klijenta" })}
                  className="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md py-2 border"
                >
                  <option value="">Izaberi klijenta...</option>
                  {activeClients.map(client => (
                    <option key={client.id} value={client.id}>{client.name}</option>
                  ))}
                </select>
              </div>
              {errors.clientId && <p className="mt-1 text-sm text-red-600">{errors.clientId.message}</p>}
             </div>
             
             <div>
               <label className="block text-sm font-medium text-gray-700">Broj Fakture</label>
               <input
                type="text"
                {...register("number", { required: true })}
                className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md py-2 px-3 border"
               />
             </div>
          </div>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">Datum Izdavanja</label>
              <div className="mt-1 relative rounded-md shadow-sm">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Calendar className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="date"
                  {...register("date", { required: true })}
                  className="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md py-2 border"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">Rok Plaćanja</label>
              <div className="mt-1 relative rounded-md shadow-sm">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Calendar className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="date"
                  {...register("dueDate", { required: true })}
                  className="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md py-2 border"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Line Items */}
        <div className="bg-white shadow rounded-lg overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 className="text-lg font-medium leading-6 text-gray-900">Stavke</h3>
            <button
              type="button"
              onClick={() => append({ description: '', quantity: 1, price: 0 })}
              className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              <Plus className="h-4 w-4 mr-1" />
              Dodaj stavku
            </button>
          </div>
          
          <div className="p-6">
            <div className="space-y-4">
              {fields.map((field, index) => (
                <div key={field.id} className="flex gap-4 items-start">
                   <div className="flex-1">
                      <label className="block text-xs font-medium text-gray-500 mb-1">Opis</label>
                      <input
                        {...register(`items.${index}.description` as const, { required: true })}
                        className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm py-2 px-3 border"
                        placeholder="Naziv usluge ili proizvoda"
                      />
                   </div>
                   <div className="w-24">
                      <label className="block text-xs font-medium text-gray-500 mb-1">Količina</label>
                      <input
                        type="number"
                        min="1"
                        {...register(`items.${index}.quantity` as const, { required: true, min: 1 })}
                        className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm py-2 px-3 border"
                      />
                   </div>
                   <div className="w-32">
                      <label className="block text-xs font-medium text-gray-500 mb-1">Cena (€)</label>
                      <input
                        type="number"
                        step="0.01"
                        {...register(`items.${index}.price` as const, { required: true, min: 0 })}
                        className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm py-2 px-3 border"
                      />
                   </div>
                   <div className="pt-6">
                     <button
                        type="button"
                        onClick={() => remove(index)}
                        className="text-red-400 hover:text-red-600 p-2"
                        disabled={fields.length === 1}
                     >
                       <Trash2 className="h-5 w-5" />
                     </button>
                   </div>
                </div>
              ))}
            </div>

            <div className="mt-8 border-t border-gray-200 pt-8">
              <div className="flex justify-end text-right">
                <div>
                  <p className="text-sm text-gray-500">Ukupno za plaćanje</p>
                  <p className="text-3xl font-bold text-gray-900">€{totalAmount.toFixed(2)}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate('/dashboard/invoices')}
            className="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          >
            Otkaži
          </button>
          <button
            type="submit"
            className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-6 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          >
            Sačuvaj Fakturu
          </button>
        </div>
      </form>
    </div>
  );
}
