import { jsx, jsxs } from "react/jsx-runtime";
import { renderToStaticMarkup } from "react-dom/server";
import clsx from "clsx";
import { format } from "date-fns";
import { srLatn } from "date-fns/locale";
const formatCurrency = (amount, currency, locale = "sr-RS") => {
  const code = currency;
  const value = Number.isFinite(amount) ? amount : 0;
  try {
    return new Intl.NumberFormat(locale, {
      style: "currency",
      currency: code,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(value);
  } catch {
    return `${code} ${value.toFixed(2)}`;
  }
};
function InvoiceDocument({
  invoice,
  client,
  company,
  currency,
  className
}) {
  const resolvedCurrency = currency || invoice.currency || company.currency || "RSD";
  const vatRate = 0.2;
  const vatAmount = company.vat_enabled ? invoice.total * vatRate : 0;
  const totalWithVat = company.vat_enabled ? invoice.total + vatAmount : invoice.total;
  return /* @__PURE__ */ jsx("div", { className: clsx("bg-white shadow-lg rounded-lg overflow-hidden print:shadow-none", className), children: /* @__PURE__ */ jsxs("div", { className: "p-8 sm:p-12", children: [
    /* @__PURE__ */ jsxs("div", { className: "flex flex-col sm:flex-row justify-between items-start gap-8 border-b border-gray-100 pb-8 mb-8", children: [
      /* @__PURE__ */ jsxs("div", { children: [
        company.logoUrl ? /* @__PURE__ */ jsx("img", { src: company.logoUrl, alt: "logo", className: "h-12 w-12" }) : /* @__PURE__ */ jsx("div", { className: "h-12 w-12 bg-indigo-600 rounded-lg flex items-center justify-center mb-4", children: /* @__PURE__ */ jsx("span", { className: "font-bold text-2xl text-white", children: company.name.substring(0, 1) }) }),
        /* @__PURE__ */ jsx("h2", { className: "text-xl font-bold text-gray-900", children: company.name }),
        /* @__PURE__ */ jsxs("div", { className: "text-gray-500 text-sm mt-2 space-y-1", children: [
          /* @__PURE__ */ jsxs("p", { children: [
            company.address,
            company.address && company.city ? "," : "",
            " ",
            company.city
          ] }),
          company.tax_id && /* @__PURE__ */ jsxs("p", { children: [
            "PIB: ",
            company.tax_id
          ] }),
          company.registration_number && /* @__PURE__ */ jsxs("p", { children: [
            "MB: ",
            company.registration_number
          ] }),
          company.iban && /* @__PURE__ */ jsxs("p", { children: [
            "IBAN: ",
            company.iban
          ] }),
          company.swift && /* @__PURE__ */ jsxs("p", { children: [
            "SWIFT: ",
            company.swift
          ] }),
          company.email && /* @__PURE__ */ jsxs("p", { children: [
            "Email: ",
            company.email
          ] }),
          company.phone && /* @__PURE__ */ jsxs("p", { children: [
            "Telefon: ",
            company.phone
          ] })
        ] })
      ] }),
      /* @__PURE__ */ jsxs("div", { className: "text-right sm:text-right", children: [
        /* @__PURE__ */ jsx("h1", { className: "text-3xl font-bold text-gray-900 mb-2", children: "FAKTURA" }),
        /* @__PURE__ */ jsxs("p", { className: "text-lg font-medium text-gray-600", children: [
          "#",
          invoice.number
        ] }),
        /* @__PURE__ */ jsxs("div", { className: "mt-4 space-y-1 text-sm text-gray-500", children: [
          /* @__PURE__ */ jsxs("div", { className: "flex justify-between gap-8", children: [
            /* @__PURE__ */ jsx("span", { children: "Datum izdavanja:" }),
            /* @__PURE__ */ jsx("span", { className: "font-medium text-gray-900", children: format(new Date(invoice.date), "dd. MMM yyyy", { locale: srLatn }) })
          ] }),
          /* @__PURE__ */ jsxs("div", { className: "flex justify-between gap-8", children: [
            /* @__PURE__ */ jsx("span", { children: "Rok plaćanja:" }),
            /* @__PURE__ */ jsx("span", { className: "font-medium text-gray-900", children: format(new Date(invoice.dueDate), "dd. MMM yyyy", { locale: srLatn }) })
          ] })
        ] })
      ] })
    ] }),
    /* @__PURE__ */ jsx("div", { className: "grid grid-cols-1 sm:grid-cols-2 gap-8 mb-8", children: /* @__PURE__ */ jsxs("div", { children: [
      /* @__PURE__ */ jsx("h3", { className: "text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3", children: "Za klijenta:" }),
      /* @__PURE__ */ jsx("div", { className: "text-gray-900 font-medium text-lg", children: client.name }),
      /* @__PURE__ */ jsxs("div", { className: "text-gray-500 text-sm mt-1 space-y-1", children: [
        /* @__PURE__ */ jsxs("p", { children: [
          client.address,
          client.address && client.city ? "," : "",
          " ",
          client.city,
          client.country ? `, ${client.country}` : ""
        ] }),
        client.email && /* @__PURE__ */ jsx("p", { children: client.email }),
        client.phone && /* @__PURE__ */ jsx("p", { children: client.phone }),
        client.tax_id && /* @__PURE__ */ jsxs("p", { children: [
          "PIB: ",
          client.tax_id
        ] }),
        client.registration_number && /* @__PURE__ */ jsxs("p", { children: [
          "MB: ",
          client.registration_number
        ] })
      ] })
    ] }) }),
    /* @__PURE__ */ jsx("div", { className: "mt-8", children: /* @__PURE__ */ jsxs("table", { className: "min-w-full divide-y divide-gray-200", children: [
      /* @__PURE__ */ jsx("thead", { children: /* @__PURE__ */ jsxs("tr", { children: [
        /* @__PURE__ */ jsx(
          "th",
          {
            scope: "col",
            className: "py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0",
            children: "Opis"
          }
        ),
        /* @__PURE__ */ jsx(
          "th",
          {
            scope: "col",
            className: "py-3.5 px-3 text-right text-sm font-semibold text-gray-900",
            children: "Količina"
          }
        ),
        /* @__PURE__ */ jsx(
          "th",
          {
            scope: "col",
            className: "py-3.5 px-3 text-right text-sm font-semibold text-gray-900",
            children: "Cena"
          }
        ),
        /* @__PURE__ */ jsx(
          "th",
          {
            scope: "col",
            className: "py-3.5 pl-3 pr-4 text-right text-sm font-semibold text-gray-900 sm:pr-0",
            children: "Ukupno"
          }
        )
      ] }) }),
      /* @__PURE__ */ jsx("tbody", { className: "divide-y divide-gray-200", children: invoice.items.map((item) => /* @__PURE__ */ jsxs("tr", { children: [
        /* @__PURE__ */ jsx("td", { className: "py-4 pl-4 pr-3 text-sm sm:pl-0", children: /* @__PURE__ */ jsx("div", { className: "font-medium text-gray-900", children: item.description }) }),
        /* @__PURE__ */ jsx("td", { className: "py-4 px-3 text-sm text-right text-gray-500", children: item.quantity }),
        /* @__PURE__ */ jsx("td", { className: "py-4 px-3 text-sm text-right text-gray-500", children: formatCurrency(item.price, resolvedCurrency) }),
        /* @__PURE__ */ jsx("td", { className: "py-4 pl-3 pr-4 text-sm text-right text-gray-900 font-medium sm:pr-0", children: formatCurrency(item.quantity * item.price, resolvedCurrency) })
      ] }, item.id)) }),
      /* @__PURE__ */ jsxs("tfoot", { children: [
        /* @__PURE__ */ jsxs("tr", { children: [
          /* @__PURE__ */ jsx(
            "th",
            {
              scope: "row",
              colSpan: 3,
              className: "hidden pl-4 pr-3 pt-6 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0",
              children: "Međuzbir"
            }
          ),
          /* @__PURE__ */ jsx(
            "th",
            {
              scope: "row",
              className: "pl-4 pr-3 pt-6 text-left text-sm font-normal text-gray-500 sm:hidden",
              children: "Međuzbir"
            }
          ),
          /* @__PURE__ */ jsx("td", { className: "pl-3 pr-4 pt-6 text-right text-sm text-gray-500 sm:pr-0", children: formatCurrency(invoice.total, resolvedCurrency) })
        ] }),
        company.vat_enabled && /* @__PURE__ */ jsxs("tr", { children: [
          /* @__PURE__ */ jsx(
            "th",
            {
              scope: "row",
              colSpan: 3,
              className: "hidden pl-4 pr-3 pt-4 text-right text-sm font-normal text-gray-500 sm:table-cell sm:pl-0",
              children: "PDV (20%)"
            }
          ),
          /* @__PURE__ */ jsx(
            "th",
            {
              scope: "row",
              className: "pl-4 pr-3 pt-4 text-left text-sm font-normal text-gray-500 sm:hidden",
              children: "PDV (20%)"
            }
          ),
          /* @__PURE__ */ jsx("td", { className: "pl-3 pr-4 pt-4 text-right text-sm text-gray-500 sm:pr-0", children: formatCurrency(vatAmount, resolvedCurrency) })
        ] }),
        /* @__PURE__ */ jsxs("tr", { children: [
          /* @__PURE__ */ jsx(
            "th",
            {
              scope: "row",
              colSpan: 3,
              className: "hidden pl-4 pr-3 pt-4 text-right text-base font-bold text-gray-900 sm:table-cell sm:pl-0",
              children: "Ukupno za plaćanje"
            }
          ),
          /* @__PURE__ */ jsx(
            "th",
            {
              scope: "row",
              className: "pl-4 pr-3 pt-4 text-left text-base font-bold text-gray-900 sm:hidden",
              children: "Ukupno"
            }
          ),
          /* @__PURE__ */ jsx("td", { className: "pl-3 pr-4 pt-4 text-right text-base font-bold text-gray-900 sm:pr-0", children: formatCurrency(totalWithVat, resolvedCurrency) })
        ] })
      ] })
    ] }) }),
    /* @__PURE__ */ jsx("div", { className: "mt-12 pt-8 border-t border-gray-100", children: /* @__PURE__ */ jsxs("p", { className: "text-gray-500 text-sm", children: [
      "Hvala vam na poslovanju! Molimo vas da iznos uplatite u roku od",
      " ",
      format(new Date(invoice.dueDate), "dd. MMM yyyy", { locale: srLatn }),
      "."
    ] }) })
  ] }) });
}
function render(props) {
  return renderToStaticMarkup(/* @__PURE__ */ jsx(InvoiceDocument, { ...props }));
}
export {
  render
};
