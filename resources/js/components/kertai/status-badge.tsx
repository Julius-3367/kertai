import { titleCase } from '@/lib/format';
import { cn } from '@/lib/utils';

const TONES: Record<string, string> = {
    // good
    succeeded: 'good',
    paid: 'good',
    active: 'good',
    approved: 'good',
    sent: 'good',
    present: 'good',
    held: 'good',
    done: 'good',
    returned: 'good',
    completed: 'good',
    cleared: 'good',
    accepted: 'good',
    // in progress
    pending: 'warn',
    processing: 'warn',
    queued: 'warn',
    open: 'warn',
    partial: 'warn',
    booked: 'warn',
    out: 'warn',
    requested: 'warn',
    submitted: 'warn',
    upcoming: 'neutral',
    scheduled: 'neutral',
    planning: 'neutral',
    draft: 'neutral',
    exiting: 'warn',
    late: 'warn',
    apology: 'neutral',
    pending_member: 'warn',
    pending_chair: 'warn',
    payout_requested: 'warn',
    unpaid: 'bad',
    // bad
    failed: 'bad',
    rejected: 'bad',
    expired: 'bad',
    cancelled: 'neutral',
    declined: 'bad',
    suspended: 'bad',
    absent: 'bad',
    overdue: 'bad',
    exited: 'neutral',
    deceased: 'neutral',
    closed: 'neutral',
    waived: 'neutral',
    released: 'neutral',
    written_off: 'neutral',
};

const CLASSES: Record<string, string> = {
    good: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    warn: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    bad: 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    neutral: 'border-border bg-muted text-muted-foreground dark:bg-muted/40',
};

const LABELS: Record<string, string> = {
    pending_member: 'Waiting for member',
    pending_chair: 'Waiting for chair',
    payout_requested: 'Payout requested',
    submitted: 'Awaiting approval',
    written_off: 'Written off',
    brought_forward: 'From before Kertai',
};

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label?: string;
    className?: string;
}) {
    const tone = TONES[status] ?? 'neutral';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                CLASSES[tone],
                className,
            )}
        >
            {label ?? LABELS[status] ?? titleCase(status)}
        </span>
    );
}
