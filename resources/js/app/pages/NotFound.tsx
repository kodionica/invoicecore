import React from 'react';
import { Link } from 'react-router';

export default function NotFound() {
  return (
    <div className="min-h-screen bg-white flex flex-col justify-center py-12 sm:px-6 lg:px-8 items-center">
      <h1 className="text-4xl font-bold text-indigo-600">404</h1>
      <p className="mt-2 text-lg text-gray-900">Stranica nije pronađena.</p>
      <Link to="/dashboard" className="mt-4 text-indigo-600 hover:text-indigo-500">
        Nazad na kontrolnu tablu
      </Link>
    </div>
  );
}
