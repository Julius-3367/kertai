import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { actionError } from '@/components/kertai/actions';
import {
    ActionError,
    CheckField,
    Field,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { today } from '@/lib/format';

type MemberData = {
    id: number;
    member_number: string;
    name: string;
    phone: string;
    email: string | null;
    id_number: string | null;
    gender: string | null;
    date_of_birth: string | null;
    joined_on: string;
    status: string;
    nominee_name: string | null;
    nominee_phone: string | null;
    nominee_relationship: string | null;
    nominee_id_number: string | null;
    notify_sms: boolean;
    notify_email: boolean;
    locale: string;
    notes: string | null;
};

export default function MemberForm({ member }: { member: MemberData | null }) {
    const form = useForm({
        name: member?.name ?? '',
        phone: member?.phone ?? '',
        email: member?.email ?? '',
        id_number: member?.id_number ?? '',
        gender: member?.gender ?? '',
        date_of_birth: member?.date_of_birth ?? '',
        joined_on: member?.joined_on ?? today(),
        member_number: member?.member_number ?? '',
        status:
            member?.status && ['active', 'suspended'].includes(member.status)
                ? member.status
                : 'active',
        nominee_name: member?.nominee_name ?? '',
        nominee_phone: member?.nominee_phone ?? '',
        nominee_relationship: member?.nominee_relationship ?? '',
        nominee_id_number: member?.nominee_id_number ?? '',
        notify_sms: member?.notify_sms ?? true,
        notify_email: member?.notify_email ?? true,
        locale: member?.locale ?? 'en',
        notes: member?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (member) {
            form.put(`/members/${member.id}`);
        } else {
            form.post('/members');
        }
    };

    const text = (
        key: keyof typeof form.data,
        label: string,
        props: Record<string, string> = {},
    ) => (
        <Field label={label} htmlFor={key} error={form.errors[key]}>
            <Input
                id={key}
                value={String(form.data[key] ?? '')}
                onChange={(e) => form.setData(key, e.target.value)}
                {...props}
            />
        </Field>
    );

    return (
        <>
            <Head title={member ? `Edit ${member.name}` : 'Add member'} />
            <Page className="max-w-4xl">
                <PageHeader
                    title={member ? `Edit ${member.name}` : 'Add a member'}
                    description={
                        member
                            ? `Member ${member.member_number}`
                            : 'They get a member number automatically unless you enter one.'
                    }
                />
                <form onSubmit={submit} className="grid gap-6">
                    <ActionError message={actionError(form.errors)} />
                    <Section title="Personal details">
                        <div className="grid gap-4 sm:grid-cols-2">
                            {text('name', 'Full name', { autoComplete: 'off' })}
                            {text('phone', 'Phone (M-Pesa number)', {
                                placeholder: '0712 345 678',
                                inputMode: 'tel',
                            })}
                            {text('email', 'Email (optional)', {
                                type: 'email',
                            })}
                            {text('id_number', 'National ID number')}
                            <Field
                                label="Gender"
                                htmlFor="gender"
                                error={form.errors.gender}
                            >
                                <Select
                                    id="gender"
                                    value={form.data.gender}
                                    onChange={(e) =>
                                        form.setData('gender', e.target.value)
                                    }
                                    placeholder="Not stated"
                                    options={[
                                        { value: 'female', label: 'Female' },
                                        { value: 'male', label: 'Male' },
                                    ]}
                                />
                            </Field>
                            {text('date_of_birth', 'Date of birth', {
                                type: 'date',
                            })}
                        </div>
                    </Section>

                    <Section title="Membership">
                        <div className="grid gap-4 sm:grid-cols-2">
                            {text('joined_on', 'Joined on', { type: 'date' })}
                            {text('member_number', 'Member number', {
                                placeholder: 'Automatic',
                            })}
                            {member && (
                                <Field
                                    label="Status"
                                    htmlFor="status"
                                    error={form.errors.status}
                                    hint="Exits and deaths are recorded from the member page."
                                >
                                    <Select
                                        id="status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target.value,
                                            )
                                        }
                                        options={[
                                            {
                                                value: 'active',
                                                label: 'Active',
                                            },
                                            {
                                                value: 'suspended',
                                                label: 'Suspended',
                                            },
                                        ]}
                                    />
                                </Field>
                            )}
                            <Field label="Message language" htmlFor="locale">
                                <Select
                                    id="locale"
                                    value={form.data.locale}
                                    onChange={(e) =>
                                        form.setData('locale', e.target.value)
                                    }
                                    options={[
                                        { value: 'en', label: 'English' },
                                        { value: 'sw', label: 'Kiswahili' },
                                    ]}
                                />
                            </Field>
                        </div>
                        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:gap-8">
                            <CheckField
                                id="notify_sms"
                                label="Send SMS"
                                checked={form.data.notify_sms}
                                onChange={(v) => form.setData('notify_sms', v)}
                            />
                            <CheckField
                                id="notify_email"
                                label="Send email"
                                checked={form.data.notify_email}
                                onChange={(v) =>
                                    form.setData('notify_email', v)
                                }
                            />
                        </div>
                    </Section>

                    <Section
                        title="Nominee"
                        description="Receives the member's savings if they pass on."
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            {text('nominee_name', 'Nominee name')}
                            {text('nominee_phone', 'Nominee phone')}
                            {text('nominee_relationship', 'Relationship')}
                            {text('nominee_id_number', 'Nominee ID number')}
                        </div>
                    </Section>

                    <Section title="Notes">
                        <Textarea
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </Section>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {member ? 'Save changes' : 'Add member'}
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={
                                    member
                                        ? `/members/${member.id}`
                                        : '/members'
                                }
                            >
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </form>
            </Page>
        </>
    );
}

MemberForm.layout = { breadcrumbs: [{ title: 'Members', href: '/members' }] };
