import type { FormDataType } from '@inertiajs/core';
import { router, useForm } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import { useState } from 'react';
import type { ComponentProps, FormEvent, ReactNode } from 'react';
import { ActionError } from '@/components/kertai/fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

/** The message a RuleViolation sends back, if any. */
export function actionError(errors: object): string | undefined {
    return (errors as Record<string, string | undefined>).action;
}

export function fieldError(errors: object, key: string): string | undefined {
    return (errors as Record<string, string | undefined>)[key];
}

/**
 * A button that opens a dialog holding a small form. The dialog closes on
 * success and stays open (showing the reason) when the server refuses.
 */
export function FormDialog<TForm extends FormDataType<TForm>>({
    trigger,
    title,
    description,
    initial,
    action,
    method = 'post',
    submitLabel = 'Save',
    destructive = false,
    wide = false,
    children,
    onSuccess,
}: {
    trigger: ReactNode;
    title: string;
    description?: ReactNode;
    initial: TForm;
    action: string;
    method?: 'post' | 'put' | 'delete';
    submitLabel?: string;
    destructive?: boolean;
    wide?: boolean;
    children: (form: InertiaFormProps<TForm>) => ReactNode;
    onSuccess?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<TForm>(initial);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form[method](action, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
                onSuccess?.();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent
                className={cn(
                    'max-h-[90vh] overflow-y-auto',
                    wide && 'sm:max-w-2xl',
                )}
                {...(description ? {} : { 'aria-describedby': undefined })}
            >
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>
                    <ActionError message={actionError(form.errors)} />
                    {children(form)}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * One-click action (approve, publish, run reconciliation). Asks first when
 * `confirm` is given.
 */
export function ActionButton({
    url,
    method = 'post',
    data,
    confirm,
    children,
    ...props
}: Omit<ComponentProps<typeof Button>, 'onClick'> & {
    url: string;
    method?: 'post' | 'put' | 'delete';
    data?: Record<
        string,
        string | number | boolean | null | (string | number)[]
    >;
    confirm?: string;
}) {
    const [busy, setBusy] = useState(false);

    return (
        <Button
            {...props}
            disabled={busy || props.disabled}
            onClick={() => {
                if (confirm && !window.confirm(confirm)) {
                    return;
                }

                router.visit(url, {
                    method,
                    data,
                    preserveScroll: true,
                    onStart: () => setBusy(true),
                    onFinish: () => setBusy(false),
                });
            }}
        >
            {busy && <Spinner />}
            {children}
        </Button>
    );
}

export function Tabs<T extends string>({
    tabs,
    value,
    onChange,
}: {
    tabs: { value: T; label: string; count?: number }[];
    value: T;
    onChange: (value: T) => void;
}) {
    return (
        <div className="-mx-3 flex gap-1 overflow-x-auto border-b px-3 sm:mx-0 sm:flex-wrap sm:px-0">
            {tabs.map((tab) => (
                <button
                    key={tab.value}
                    type="button"
                    onClick={() => onChange(tab.value)}
                    className={cn(
                        '-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                        value === tab.value
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                    {tab.count !== undefined && (
                        <span className="ml-1.5 rounded bg-muted px-1.5 py-0.5 text-xs">
                            {tab.count}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}
