import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function Table({ className, ...props }: ComponentProps<'table'>) {
    return (
        <div className="w-full overflow-x-auto">
            <table
                className={cn('w-full caption-bottom text-sm', className)}
                {...props}
            />
        </div>
    );
}

export function THead(props: ComponentProps<'thead'>) {
    return <thead className="[&_tr]:border-b" {...props} />;
}

export function TBody(props: ComponentProps<'tbody'>) {
    return <tbody className="[&_tr:last-child]:border-0" {...props} />;
}

export function TR({ className, ...props }: ComponentProps<'tr'>) {
    return (
        <tr
            className={cn(
                'border-b transition-colors hover:bg-muted/40',
                className,
            )}
            {...props}
        />
    );
}

export function TH({ className, ...props }: ComponentProps<'th'>) {
    return (
        <th
            className={cn(
                'h-9 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-muted-foreground uppercase',
                className,
            )}
            {...props}
        />
    );
}

export function TD({ className, ...props }: ComponentProps<'td'>) {
    return (
        <td className={cn('px-2 py-2 align-middle', className)} {...props} />
    );
}

export function Num({ className, ...props }: ComponentProps<'td'>) {
    return (
        <td
            className={cn(
                'px-2 py-2 text-right align-middle whitespace-nowrap tabular-nums',
                className,
            )}
            {...props}
        />
    );
}
