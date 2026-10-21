import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Field, Select, Textarea } from '@/components/kertai/fields';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Template = {
    key: string;
    label: string;
    channel: string;
    locale: string;
    subject: string;
    body: string;
    customised: boolean;
    reminder: boolean;
};

export default function MessageTemplates({
    templates,
}: {
    templates: Template[];
}) {
    const [channel, setChannel] = useState('sms');
    const [locale, setLocale] = useState('en');
    const shown = templates.filter(
        (t) => t.channel === channel && t.locale === locale,
    );

    return (
        <>
            <Head title="Message templates" />
            <Page className="max-w-5xl">
                <PageHeader
                    title="Message templates"
                    description="The wording of every automatic message. Words in {braces} are filled in for each member."
                    actions={
                        <>
                            <Select
                                aria-label="Channel"
                                value={channel}
                                onChange={(e) => setChannel(e.target.value)}
                                options={[
                                    { value: 'sms', label: 'SMS' },
                                    { value: 'email', label: 'Email' },
                                ]}
                            />
                            <Select
                                aria-label="Language"
                                value={locale}
                                onChange={(e) => setLocale(e.target.value)}
                                options={[
                                    { value: 'en', label: 'English' },
                                    { value: 'sw', label: 'Kiswahili' },
                                ]}
                            />
                        </>
                    }
                />
                {shown.map((t) => (
                    <TemplateCard
                        key={`${t.key}-${t.channel}-${t.locale}`}
                        template={t}
                    />
                ))}
            </Page>
        </>
    );
}

function TemplateCard({ template }: { template: Template }) {
    const form = useForm({
        key: template.key,
        channel: template.channel,
        locale: template.locale,
        subject: template.subject,
        body: template.body,
        reset: false,
    });

    const id = `${template.key}-${template.channel}-${template.locale}`;

    return (
        <Section
            title={template.label}
            description={
                template.reminder
                    ? 'Reminder: members who turned reminders off do not get this.'
                    : 'Always sent.'
            }
            actions={
                template.customised ? (
                    <StatusBadge status="active" label="Customised" />
                ) : (
                    <StatusBadge status="draft" label="Standard wording" />
                )
            }
        >
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((d) => ({ ...d, reset: false }));
                    form.put('/messages/templates', { preserveScroll: true });
                }}
            >
                {template.channel === 'email' && (
                    <Field
                        label="Subject"
                        htmlFor={`${id}-subject`}
                        error={form.errors.subject}
                    >
                        <Input
                            id={`${id}-subject`}
                            value={form.data.subject}
                            onChange={(e) =>
                                form.setData('subject', e.target.value)
                            }
                        />
                    </Field>
                )}
                <Field
                    label="Text"
                    htmlFor={`${id}-body`}
                    error={form.errors.body}
                    hint={
                        template.channel === 'sms'
                            ? `${form.data.body.length} characters before placeholders are filled.`
                            : undefined
                    }
                >
                    <Textarea
                        id={`${id}-body`}
                        rows={3}
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                    />
                </Field>
                <div className="flex gap-2">
                    <Button
                        size="sm"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save
                    </Button>
                    {template.customised && (
                        <Button
                            size="sm"
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                form.transform((d) => ({ ...d, reset: true }));
                                form.put('/messages/templates', {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            Reset to standard
                        </Button>
                    )}
                </div>
            </form>
        </Section>
    );
}

MessageTemplates.layout = {
    breadcrumbs: [
        { title: 'Messages', href: '/messages' },
        { title: 'Templates', href: '/messages/templates' },
    ],
};
