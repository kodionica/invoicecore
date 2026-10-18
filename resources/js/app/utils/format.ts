export const formatCurrency = ( amount: number | null | undefined, currency?: string, locale = 'sr-RS' ) => {
    const code = currency || 'RSD';
    const value = Number.isFinite( amount ) ? amount : 0;

    try {
        return new Intl.NumberFormat( locale, {
            style:                 'currency',
            currency:              code,
            currencyDisplay:       'code',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        } ).format( value );
    } catch {
        return `${ code } ${ value.toFixed( 2 ) }`;
    }
};

export interface InvoiceRsdSnapshot {
    currency?: string | null;
    total: number;
    totalRsd: number;
    fxRateToRsd?: number;
    hasRsdSnapshot?: boolean;
}

/** Returns the persisted final invoice amount in RSD, or null when the snapshot is unusable. */
export const getInvoiceAmountInRsd = (invoice: InvoiceRsdSnapshot): number | null => {
    if ((invoice.currency ?? 'RSD').toUpperCase() === 'RSD') {
        return Number.isFinite(invoice.totalRsd) ? invoice.totalRsd : invoice.total;
    }

    if (
        invoice.hasRsdSnapshot === false
        || !Number.isFinite(invoice.fxRateToRsd)
        || (invoice.fxRateToRsd ?? 0) <= 0
        || !Number.isFinite(invoice.totalRsd)
    ) {
        return null;
    }

    return invoice.totalRsd;
};

interface formatCurrencyAltParameters {
    amount: number;
    currency?: string;
    locale?: string;
    currencySpace?: CurrencySpace;
}

enum CurrencySpace {
    Left        = 'left',
    Left_Space  = 'left_space',
    Right       = 'right',
    Right_Space = 'right_space'
}

export const formatCurrencyAlt = ( {
                                       amount,
                                       currency = 'RSD',
                                       locale = 'sr-RS',
                                       currencySpace = CurrencySpace.Left
                                   }: formatCurrencyAltParameters ): string => {
    const value = Number.isFinite( amount ) ? amount : 0;
    const formattedNumber = new Intl.NumberFormat( locale, {
        style:                 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    } ).format( value ).replace( '.', '' );

    const hasSpace = currencySpace.includes( 'space' );

    if ( currencySpace.startsWith( 'left' ) ) {
        return hasSpace ? `${ currency } ${ formattedNumber }` : `${ currency }${ formattedNumber }`;
    } else {
        return hasSpace ? `${ formattedNumber } ${ currency }` : `${ formattedNumber }${ currency }`;
    }
};

export const formatLocalDate = ( date: Date ) => {
    const year = date.getFullYear();
    const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
    const day = String( date.getDate() ).padStart( 2, '0' );

    return `${ year }-${ month }-${ day }`;
};

export const addDaysToDate = ( date: Date, days: number ) => {
    const result = new Date( date );
    result.setDate( result.getDate() + days );

    return result;
};
