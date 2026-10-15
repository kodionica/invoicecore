import { Search, Filter, Download } from 'lucide-react';

interface FiltersProps {
    searchTerm: string;
    onSearchTermChange: ( searchTerm: string ) => void;
}

export default function Filters( { searchTerm, onSearchTermChange }: FiltersProps ) {
    return (
        <div className="p-4 border-b border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="relative rounded-md shadow-sm max-w-xs w-full">
                <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <Search className="h-4 w-4 text-gray-400" aria-hidden="true"/>
                </div>
                <input
                    type="text"
                    className="block w-full rounded-md border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2"
                    placeholder="Pretraži fakture..."
                    value={ searchTerm }
                    onChange={ ( e ) => onSearchTermChange( e.target.value ) }
                />
            </div>
            <div className="flex gap-2">
                <button
                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <Filter className="h-4 w-4 mr-2 text-gray-500"/>
                    Filteri
                </button>
                <button
                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <Download className="h-4 w-4 mr-2 text-gray-500"/>
                    Izvoz
                </button>
            </div>
        </div>
    );
}
