import { useNavigate } from 'react-router';
import { Plus }        from 'lucide-react';

export default function SectionHeader() {
    const navigate = useNavigate();

    return (
        <div className="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Fakture</h1>
                <p className="mt-2 text-sm text-gray-700">Pregled svih izdatih faktura.</p>
            </div>
            <div className="mt-4 sm:mt-0">
                <button
                    onClick={ () => navigate( '/dashboard/invoices/new' ) }
                    className="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                >
                    <Plus className="h-4 w-4 mr-2"/>
                    Nova Faktura
                </button>
            </div>
        </div>
    );
}
