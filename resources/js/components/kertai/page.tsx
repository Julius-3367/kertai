import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Page({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-3 sm:p-4 md:gap-6 md:p-6',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="space-y-1">
                <h1 className="text-xl font-semibold tracking-tight md:text-2xl">
                    {title}
                </h1>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}

export function Section({
    title,
    description,
    actions,
    children,
    className,
}: {
    title?: string;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'rounded-xl border bg-card text-card-foreground shadow-xs',
                className,
            )}
        >
            {(title || actions) && (
                <div className="flex flex-wrap items-center justify-between gap-2 border-b px-3 py-3 sm:px-4">
                    <div>
                        {title && (
                            <h2 className="text-base font-semibold">{title}</h2>
                        )}
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && (
                        <div className="flex flex-wrap items-center gap-2">
                            {actions}
                        </div>
                    )}
                </div>
            )}
            <div className="p-3 sm:p-4">{children}</div>
        </section>
    );
}

export function StatCard({
    label,
    value,
    hint,
    tone = 'default',
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    tone?: 'default' | 'good' | 'warn' | 'bad';
}) {
    const tones = {
        default: '',
        good: 'text-emerald-700 dark:text-emerald-400',
        warn: 'text-amber-700 dark:text-amber-400',
        bad: 'text-red-700 dark:text-red-400',
    };

    return (
        <div className="min-w-0 rounded-xl border bg-card p-3 shadow-xs sm:p-4">
            <div className="text-[11px] leading-tight font-medium tracking-wide text-muted-foreground uppercase sm:text-xs">
                {label}
            </div>
            <div
                className={cn(
                    'mt-1 truncate text-lg font-semibold tabular-nums sm:text-xl',
                    tones[tone],
                )}
            >
                {value}
            </div>
            {hint && (
                <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}

export function EmptyState({
    title,
    children,
}: {
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-6 py-10 text-center">
            <p className="font-medium">{title}</p>
            {children && (
                <div className="max-w-md text-sm text-muted-foreground">
                    {children}
                </div>
            )}
        </div>
    );
}

export function Progress({ value }: { value: number }) {
    return (
        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
            <div
                className="h-full rounded-full bg-emerald-600 transition-all"
                style={{ width: `${Math.max(0, Math.min(100, value))}%` }}
            />
        </div>
    );
}

export function DefinitionList({
    items,
}: {
    items: { label: string; value: ReactNode }[];
}) {
    return (
        <dl className="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            {items.map((item) => (
                <div key={item.label}>
                    <dt className="text-muted-foreground">{item.label}</dt>
                    <dd className="font-medium break-words">
                        {item.value ?? '—'}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
