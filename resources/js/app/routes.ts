import { createBrowserRouter, Navigate } from "react-router";
import Auth from "./pages/Auth";
import DashboardLayout from "./layout/DashboardLayout";
import Dashboard from "./pages/Dashboard";
import Clients from "./pages/Clients";
import ClientForm from "./pages/ClientForm";
import Companies from "./pages/Companies";
import CompanyForm from "./pages/CompanyForm";
import CompanyDetails from "./pages/CompanyDetails";
import Invoices from "./pages/Invoices";
import InvoiceForm from "./pages/InvoiceForm";
import InvoiceDetails from "./pages/InvoiceDetails";
import NotFound from "./pages/NotFound";
import Settings from "./pages/Settings";

export const router = createBrowserRouter([
  {
    path: "/",
    Component: Auth,
  },
  {
    path: "/register",
    Component: Auth,
  },
  {
    path: "/dashboard",
    Component: DashboardLayout,
    children: [
      { index: true, Component: Dashboard },
      { path: "clients", Component: Clients },
      { path: "clients/new", Component: ClientForm },
      { path: "clients/:id", Component: ClientForm },
      { path: "invoices", Component: Invoices },
      { path: "invoices/new", Component: InvoiceForm },
      { path: "invoices/:id", Component: InvoiceDetails },
      { path: "companies", Component: Companies },
      { path: "companies/new", Component: CompanyForm },
      { path: "companies/:id", Component: CompanyDetails },
      { path: "settings", Component: Settings },
    ],
  },
  {
    path: "*",
    Component: NotFound,
  },
]);
