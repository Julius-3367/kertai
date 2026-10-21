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

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value.length === 10 ? `${value}T00:00:00` : value);

    return date.toLocaleDateString('en-KE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
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
    });
}

export function today(): string {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
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
