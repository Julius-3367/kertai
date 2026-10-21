// Money arrives from the server as integer cents.

const kes = new Intl.NumberFormat('en-KE', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

export function formatKes(cents: number | null | undefined): string {
    if (cents === null || cents === undefined) {
        return '—';
    }

    const sign = cents < 0 ? '-' : '';

    return `${sign}KES ${kes.format(Math.abs(cents) / 100)}`;
}

/** Cents to a plain shillings string for an input box. */
export function toShillings(cents: number | null | undefined): string {
    if (cents === null || cents === undefined) {
        return '';
    }

    return (cents / 100).toFixed(cents % 100 === 0 ? 0 : 2);
}

// Times are the group's (Nairobi), whatever the phone or server is set to.
// This also keeps the server-rendered page and the browser in agreement.
const TIME_ZONE = 'Africa/Nairobi';

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    // A bare date (2026-11-05) is a calendar day, not an instant.
    const dateOnly = value.length === 10;
    const date = new Date(dateOnly ? `${value}T00:00:00Z` : value);

    return date.toLocaleDateString('en-KE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: dateOnly ? 'UTC' : TIME_ZONE,
    });
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-KE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: TIME_ZONE,
    });
}

/** Today's date in Nairobi as YYYY-MM-DD. */
export function today(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: TIME_ZONE }).format(
        new Date(),
    );
}

export function thisMonth(): string {
    return today().slice(0, 7);
}

export function percent(part: number, whole: number): number {
    if (whole <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((part / whole) * 100));
}

export function titleCase(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function fileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
