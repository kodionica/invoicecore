import React from 'react';
import { RouterProvider } from 'react-router';
import { AppProvider } from './context/AppContext';
import { router } from './routes';
import { Toaster } from 'sonner';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const queryClient = new QueryClient();

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AppProvider>
        <Toaster position="top-right" richColors />
        <RouterProvider router={router} />
      </AppProvider>
    </QueryClientProvider>
  );
}
