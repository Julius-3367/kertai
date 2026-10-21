import { Head, useForm } from '@inertiajs/react';
import { Copy, PlugZap } from 'lucide-react';
import { toast } from 'sonner';
import { FormDialog, actionError } from '@/components/kertai/actions';
import {
    ActionError,
    CheckField,
    Field,
    Select,
} from '@/components/kertai/fields';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type FieldDef = {
    key: string;
    label: string;
    secret?: boolean;
    placeholder?: string;
};
type Drivers = Record<
    string,
    Record<string, { label: string; fields: FieldDef[] }>
>;
type Current = Record<
    string,
    {
        driver: string;
        source: string;
        is_active: boolean;
        values: Record<string, string | boolean | null>;
    }
>;

const KINDS: {
    kind: string;
    title: string;
    description: string;
    testLabel: string;
    testPlaceholder: string;
}[] = [
    {
        kind: 'sms',
        title: 'SMS',
        description:
            'Receipts, reminders and notices. Onfon is the default; any provider can be swapped in.',
        testLabel: 'Send a test SMS to',
        testPlaceholder: '0712 345 678',
    },
    {
        kind: 'email',
        title: 'Email',
        description:
            'The same messages by email, for members who gave an address.',
        testLabel: 'Send a test email to',
        testPlaceholder: 'you@example.com',
    },
    {
        kind: 'payments',
        title: 'M-Pesa (SimulinkPay)',
        description: 'STK push for members and the public fundraiser page.',
        testLabel: 'Look up a SimulinkPay payment ID',
        testPlaceholder: '57',
    },
];

export default function Integrations({
    drivers,
    current,
    webhooks,
}: {
    drivers: Drivers;
    current: Current;
    webhooks: { simulinkpay: string; inbound_sms: string | null };
}) {
    return (
        <>
            <Head title="Integrations" />
            <Page className="max-w-4xl">
                <PageHeader
                    title="Integrations"
                    description="Provider keys are encrypted. Secret fields are never shown again; leave them blank to keep what is saved."
                />

                {KINDS.map((k) => (
                    <IntegrationForm
                        key={k.kind}
                        meta={k}
                        drivers={drivers[k.kind]}
                        current={current[k.kind]}
                    />
                ))}

                <Section title="Addresses to give your providers">
                    <div className="space-y-3 text-sm">
                        <CopyRow
                            label="SimulinkPay webhook (events: payment.succeeded, payment.failed, payment.pending)"
                            value={webhooks.simulinkpay}
                        />
                        {webhooks.inbound_sms ? (
                            <CopyRow
                                label="Inbound SMS (members text BAL for their balance)"
                                value={webhooks.inbound_sms}
                            />
                        ) : (
                            <p className="text-muted-foreground">
                                Set INBOUND_SMS_TOKEN in the server's .env to
                                enable the BAL keyword.
                            </p>
                        )}
                    </div>
                </Section>
            </Page>
        </>
    );
}

function CopyRow({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="flex items-center gap-2">
                <code className="flex-1 truncate rounded bg-muted px-2 py-1">
                    {value}
                </code>
                <Button
                    size="sm"
                    variant="ghost"
                    type="button"
                    aria-label="Copy"
                    onClick={() => {
                        void navigator.clipboard
                            .writeText(value)
                            .then(() => toast.success('Copied'));
                    }}
                >
                    <Copy />
                </Button>
            </div>
        </div>
    );
}

function IntegrationForm({
    meta,
    drivers,
    current,
}: {
    meta: (typeof KINDS)[number];
    drivers: Record<string, { label: string; fields: FieldDef[] }>;
    current: Current[string];
}) {
    const form = useForm({
        driver:
            current.driver in drivers
                ? current.driver
                : Object.keys(drivers)[0],
        is_active: current.source === 'database' ? current.is_active : true,
        values: Object.fromEntries(
            Object.values(drivers)
                .flatMap((d) => d.fields)
                .map((f) => [
                    f.key,
                    f.secret ? '' : String(current.values[f.key] ?? ''),
                ]),
        ) as Record<string, string>,
    });

    const fields = drivers[form.data.driver]?.fields ?? [];

    return (
        <Section
            title={meta.title}
            description={meta.description}
            actions={
                <>
                    <StatusBadge
                        status={
                            current.source === 'database' ? 'active' : 'draft'
                        }
                        label={
                            current.source === 'database'
                                ? `Saved: ${drivers[current.driver]?.label ?? current.driver}`
                                : `From .env: ${current.driver}`
                        }
                    />
                    <FormDialog
                        title={`Test ${meta.title}`}
                        description="Uses the saved settings."
                        action={`/admin/integrations/${meta.kind}/test`}
                        submitLabel="Run test"
                        initial={{ to: '' }}
                        trigger={
                            <Button size="sm" variant="outline">
                                <PlugZap /> Test
                            </Button>
                        }
                    >
                        {(f) => (
                            <Field
                                label={meta.testLabel}
                                htmlFor={`test-${meta.kind}`}
                                error={f.errors.to}
                            >
                                <Input
                                    id={`test-${meta.kind}`}
                                    placeholder={meta.testPlaceholder}
                                    value={f.data.to}
                                    onChange={(e) =>
                                        f.setData('to', e.target.value)
                                    }
                                />
                            </Field>
                        )}
                    </FormDialog>
                </>
            }
        >
            <form
                className="grid gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put(`/admin/integrations/${meta.kind}`, {
                        preserveScroll: true,
                    });
                }}
            >
                <ActionError message={actionError(form.errors)} />
                <Field label="Provider" htmlFor={`${meta.kind}-driver`}>
                    <Select
                        id={`${meta.kind}-driver`}
                        value={form.data.driver}
                        onChange={(e) => form.setData('driver', e.target.value)}
                        options={Object.entries(drivers).map(([value, d]) => ({
                            value,
                            label: d.label,
                        }))}
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    {fields.map((f) => (
                        <Field
                            key={f.key}
                            label={f.label}
                            htmlFor={`${meta.kind}-${f.key}`}
                            hint={
                                f.secret
                                    ? current.values[`${f.key}__set`] &&
                                      current.driver === form.data.driver
                                        ? 'Saved. Leave blank to keep.'
                                        : 'Not set.'
                                    : undefined
                            }
                        >
                            <Input
                                id={`${meta.kind}-${f.key}`}
                                type={f.secret ? 'password' : 'text'}
                                autoComplete="off"
                                placeholder={f.placeholder}
                                value={form.data.values[f.key] ?? ''}
                                onChange={(e) =>
                                    form.setData('values', {
                                        ...form.data.values,
                                        [f.key]: e.target.value,
                                    })
                                }
                            />
                        </Field>
                    ))}
                </div>
                <CheckField
                    id={`${meta.kind}-active`}
                    label="Use these settings"
                    hint="Untick to fall back to the server's .env settings."
                    checked={form.data.is_active}
                    onChange={(v) => form.setData('is_active', v)}
                />
                <div>
                    <Button size="sm" disabled={form.processing}>
                        Save {meta.title}
                    </Button>
                </div>
            </form>
        </Section>
    );
}

Integrations.layout = {
    breadcrumbs: [{ title: 'Integrations', href: '/admin/integrations' }],
};
