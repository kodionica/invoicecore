export const formatCurrency = (amount: number, currency?: string, locale = 'sr-RS') => {
  const code = currency || 'RSD';
  const value = Number.isFinite(amount) ? amount : 0;

  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: code,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value);
  } catch {
    return `${code} ${value.toFixed(2)}`;
  }
};
