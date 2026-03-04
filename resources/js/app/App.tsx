import React from 'react';
import { RouterProvider } from 'react-router';
import { AppProvider } from './context/AppContext';
import { router } from './routes';
import { Toaster } from 'sonner';

export default function App() {
  return (
    <AppProvider>
      <Toaster position="top-right" richColors />
      <RouterProvider router={router} />
    </AppProvider>
  );
}
