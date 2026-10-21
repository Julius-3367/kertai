import {
    Children,
    cloneElement,
    createContext,
    isValidElement,
    useContext,
} from 'react';
import type { ComponentProps, ReactElement, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Column headings of the surrounding table, when it shows as cards on
 * phones. Each cell prints its heading next to its value there.
 */
const Labels = createContext<string[] | null>(null);

function textOf(node: ReactNode): string {
    if (typeof node === 'string' || typeof node === 'number') {
        return String(node);
    }

    if (Array.isArray(node)) {
        return node.map(textOf).join('');
    }

    if (isValidElement(node)) {
        return textOf((node.props as { children?: ReactNode }).children);
    }

    return '';
}

function headingsOf(children: ReactNode): string[] | null {
    let labels: string[] | null = null;

    Children.forEach(children, (child) => {
        if (!isValidElement(child) || child.type !== THead) {
            return;
        }

        const row = Children.toArray(
            (child.props as { children?: ReactNode }).children,
        )[0];

        if (isValidElement(row)) {
            labels = Children.toArray(
                (row.props as { children?: ReactNode }).children,
            ).map((cell) => textOf(cell));
        }
    });

    return labels;
}

/**
 * A table that becomes a stack of cards below the `sm` breakpoint, so
 * members on phones never scroll sideways. Pass `stack={false}` for grids
 * that only make sense as a grid (contribution grid, journal).
 */
export function Table({
    className,
    children,
    stack = true,
    ...props
}: ComponentProps<'table'> & { stack?: boolean }) {
    const labels = stack ? headingsOf(children) : null;

    return (
        <Labels value={labels}>
            <div
                className={cn(
                    'w-full',
                    labels ? 'sm:overflow-x-auto' : 'overflow-x-auto',
                )}
            >
                <table
                    className={cn(
                        'w-full caption-bottom text-sm',
                        labels && 'max-sm:block',
                        className,
                    )}
                    {...props}
                >
                    {children}
                </table>
            </div>
        </Labels>
    );
}

export function THead(props: ComponentProps<'thead'>) {
    const labels = useContext(Labels);

    return (
        <thead
            className={cn('[&_tr]:border-b', labels && 'max-sm:hidden')}
            {...props}
        />
    );
}

export function TBody(props: ComponentProps<'tbody'>) {
    const labels = useContext(Labels);

    return (
        <tbody
            className={
                labels
                    ? 'max-sm:block max-sm:space-y-3 sm:[&_tr:last-child]:border-0'
                    : '[&_tr:last-child]:border-0'
            }
            {...props}
        />
    );
}

export function TR({ className, children, ...props }: ComponentProps<'tr'>) {
    const labels = useContext(Labels);

    const cells = labels
        ? Children.toArray(children).map((cell, i) =>
              isValidElement(cell)
                  ? cloneElement(
                        cell as ReactElement<{ 'data-label'?: string }>,
                        {
                            'data-label': labels[i] ?? '',
                        },
                    )
                  : cell,
          )
        : children;

    return (
        <tr
            className={cn(
                'border-b transition-colors hover:bg-muted/40',
                labels &&
                    'max-sm:block max-sm:rounded-lg max-sm:border max-sm:bg-card max-sm:p-3 max-sm:hover:bg-card',
                className,
            )}
            {...props}
        >
            {cells}
        </tr>
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

// On phones each cell is a "Label ......... value" line inside its card.
const stackedCell =
    'max-sm:flex max-sm:items-start max-sm:justify-between max-sm:gap-4 max-sm:px-0 max-sm:py-1 max-sm:text-right max-sm:empty:hidden max-sm:before:shrink-0 max-sm:before:pt-0.5 max-sm:before:text-left max-sm:before:text-xs max-sm:before:font-medium max-sm:before:text-muted-foreground max-sm:before:uppercase max-sm:before:content-[attr(data-label)]';

export function TD({ className, ...props }: ComponentProps<'td'>) {
    const labels = useContext(Labels);

    return (
        <td
            className={cn(
                'px-2 py-2 align-middle',
                labels && stackedCell,
                className,
            )}
            {...props}
        />
    );
}

export function Num({ className, ...props }: ComponentProps<'td'>) {
    const labels = useContext(Labels);

    return (
        <td
            className={cn(
                'px-2 py-2 text-right align-middle whitespace-nowrap tabular-nums',
                labels && stackedCell,
                className,
            )}
            {...props}
        />
    );
}
