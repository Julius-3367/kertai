import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/kertai';

export function Field({
    label,
    htmlFor,
    error,
    hint,
    children,
    className,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

const controlClass =
    'border-input bg-transparent dark:bg-input/30 flex w-full min-w-0 rounded-md border px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm aria-invalid:border-destructive';

export function Select({
    options,
    placeholder,
    className,
    ...props
}: ComponentProps<'select'> & {
    options: { value: string | number; label: string }[];
    placeholder?: string;
}) {
    return (
        <select className={cn(controlClass, 'h-9', className)} {...props}>
            {placeholder !== undefined && (
                <option value="">{placeholder}</option>
            )}
            {options.map((o) => (
                <option key={o.value} value={o.value}>
                    {o.label}
                </option>
            ))}
        </select>
    );
}

export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(controlClass, 'min-h-24 py-2', className)}
            {...props}
        />
    );
}

export function MoneyInput({ className, ...props }: ComponentProps<'input'>) {
    return (
        <div className="relative">
            <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                KES
            </span>
            <Input
                inputMode="decimal"
                autoComplete="off"
                className={cn('pl-12 tabular-nums', className)}
                {...props}
            />
        </div>
    );
}

export function MemberSelect({
    members,
    placeholder = 'Choose a member',
    ...props
}: Omit<ComponentProps<'select'>, 'children'> & {
    members: Option[];
    placeholder?: string;
}) {
    return (
        <Select
            options={members.map((m) => ({ value: m.id, label: m.label }))}
            placeholder={placeholder}
            {...props}
        />
    );
}

export function CheckField({
    id,
    label,
    checked,
    onChange,
    hint,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    hint?: string;
}) {
    return (
        <div className="flex items-start gap-2">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(v) => onChange(v === true)}
                className="mt-0.5"
            />
            <div className="grid gap-0.5">
                <Label htmlFor={id} className="font-normal">
                    {label}
                </Label>
                {hint && (
                    <span className="text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </div>
        </div>
    );
}

/** Shows a form-level error from a blocked action (RuleViolation). */
export function ActionError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
            {message}
        </div>
    );
}
