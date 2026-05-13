import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

export function formatMoney(value, currency = 'BDT', options = {}) {
    if (value === null || value === undefined || value === '') return '—';

    const amount = Number(value);
    if (!Number.isFinite(amount)) return String(value);

    const code = String(currency || 'BDT').trim().toUpperCase();
    const locale = options.locale || (code === 'BDT' ? 'en-BD' : 'en-US');
    const currencyDisplay = options.currencyDisplay || 'narrowSymbol';

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: code,
            currencyDisplay,
            minimumFractionDigits: options.minimumFractionDigits ?? 2,
            maximumFractionDigits: options.maximumFractionDigits ?? 2,
        }).format(amount);
    } catch {
        return `${code} ${amount.toLocaleString(locale, {
            minimumFractionDigits: options.minimumFractionDigits ?? 2,
            maximumFractionDigits: options.maximumFractionDigits ?? 2,
        })}`;
    }
}
