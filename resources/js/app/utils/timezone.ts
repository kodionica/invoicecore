export const DEFAULT_COMPANY_TIMEZONE = 'Europe/Belgrade';

export const supportedTimezones = (): string[] => {
    const intl = Intl as typeof Intl & {
        supportedValuesOf?: (key: string) => string[];
    };
    const values = typeof intl.supportedValuesOf === 'function'
        ? intl.supportedValuesOf('timeZone')
        : [];

    return Array.from(new Set([DEFAULT_COMPANY_TIMEZONE, ...values])).sort();
};

export const deviceTimezone = (): string => {
    try {
        const candidate = Intl.DateTimeFormat().resolvedOptions().timeZone;
        return supportedTimezones().includes(candidate) ? candidate : DEFAULT_COMPANY_TIMEZONE;
    } catch {
        return DEFAULT_COMPANY_TIMEZONE;
    }
};

export const companyToday = (timezone?: string | null): string => {
    const zone = timezone || DEFAULT_COMPANY_TIMEZONE;
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: zone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]));

    return values.year + '-' + values.month + '-' + values.day;
};
