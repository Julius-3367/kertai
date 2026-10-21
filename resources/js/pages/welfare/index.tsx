import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormDialog } from '@/components/kertai/actions';
import {
    Field,
    MoneyInput,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
    StatCard,
} from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import {
    Num,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
} from '@/components/kertai/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, formatKes, titleCase, today } from '@/lib/format';

type CaseRow = {
    id: number;
    member: string;
    beneficiary_name: string;
    relationship: string;
    event: string;
    event_on: string;
    benefit_amount: number;
    levy_per_member: number | null;
    status: string;
};

type MemberWithDependants = {
    id: number;
    label: string;
    dependants: { id: number; name: string; relationship: string }[];
};

export default function WelfareIndex({
    cases,
    fund,
    benefits,
    members,
    events,
}: {
    cases: CaseRow[];
    fund: number;
    benefits: Record<string, number>;
    members: MemberWithDependants[];
    events: Record<string, string>;
}) {
    return (
        <>
            <Head title="Welfare" />
            <Page>
                <PageHeader
                    title="Welfare"
                    description="Covers members, their spouses, children and parents."
                    actions={
                        <FormDialog
                            title="Open a welfare case"
                            description="Leave the benefit empty to use the amount set in the group rules."
                            action="/welfare"
                            submitLabel="Open case"
                            initial={{
                                member_id: '',
                                dependant_id: '',
                                event: 'death',
                                event_on: today(),
                                description: '',
                                benefit_amount: '',
                            }}
                            trigger={
                                <Button>
                                    <Plus /> Open case
                                </Button>
                            }
                        >
                            {(form) => {
                                const member = members.find(
                                    (m) => String(m.id) === form.data.member_id,
                                );

                                return (
                                    <>
                                        <Field
                                            label="Member"
                                            htmlFor="wc-member"
                                            error={form.errors.member_id}
                                        >
                                            <Select
                                                id="wc-member"
                                                value={form.data.member_id}
                                                onChange={(e) => {
                                                    form.setData(
                                                        'member_id',
                                                        e.target.value,
                                                    );
                                                    form.setData(
                                                        'dependant_id',
                                                        '',
                                                    );
                                                }}
                                                placeholder="Choose a member"
                                                options={members.map((m) => ({
                                                    value: m.id,
                                                    label: m.label,
                                                }))}
                                            />
                                        </Field>
                                        <Field
                                            label="Who is affected"
                                            htmlFor="wc-dep"
                                            error={form.errors.dependant_id}
                                        >
                                            <Select
                                                id="wc-dep"
                                                value={form.data.dependant_id}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'dependant_id',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="The member themself"
                                                options={(
                                                    member?.dependants ?? []
                                                ).map((d) => ({
                                                    value: d.id,
                                                    label: `${d.name} (${d.relationship})`,
                                                }))}
                                            />
                                        </Field>
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field
                                                label="Event"
                                                htmlFor="wc-event"
                                                error={form.errors.event}
                                            >
                                                <Select
                                                    id="wc-event"
                                                    value={form.data.event}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'event',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={Object.entries(
                                                        events,
                                                    ).map(([value, label]) => ({
                                                        value,
                                                        label,
                                                    }))}
                                                />
                                            </Field>
                                            <Field
                                                label="Date"
                                                htmlFor="wc-date"
                                                error={form.errors.event_on}
                                            >
                                                <Input
                                                    id="wc-date"
                                                    type="date"
                                                    value={form.data.event_on}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'event_on',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </div>
                                        <Field
                                            label="Benefit (optional)"
                                            htmlFor="wc-benefit"
                                            error={form.errors.benefit_amount}
                                        >
                                            <MoneyInput
                                                id="wc-benefit"
                                                value={form.data.benefit_amount}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'benefit_amount',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Details"
                                            htmlFor="wc-desc"
                                        >
                                            <Textarea
                                                id="wc-desc"
                                                value={form.data.description}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </>
                                );
                            }}
                        </FormDialog>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <StatCard
                        label="Welfare fund"
                        value={formatKes(fund)}
                        tone="good"
                    />
                    {Object.entries(benefits).map(([key, value]) => (
                        <StatCard
                            key={key}
                            label={titleCase(key)}
                            value={formatKes(value)}
                        />
                    ))}
                </div>

                <Section title="Cases">
                    {cases.length === 0 ? (
                        <EmptyState title="No welfare cases" />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Date</TH>
                                    <TH>Beneficiary</TH>
                                    <TH>Event</TH>
                                    <TH>Levy</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Benefit</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {cases.map((c) => (
                                    <TR key={c.id}>
                                        <TD>{formatDate(c.event_on)}</TD>
                                        <TD>
                                            <Link
                                                href={`/welfare/${c.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {c.beneficiary_name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {c.relationship === 'self'
                                                    ? 'Member'
                                                    : `${titleCase(c.relationship)} of ${c.member}`}
                                            </div>
                                        </TD>
                                        <TD>{titleCase(c.event)}</TD>
                                        <TD>
                                            {c.levy_per_member
                                                ? `${formatKes(c.levy_per_member)} each`
                                                : '—'}
                                        </TD>
                                        <TD>
                                            <StatusBadge status={c.status} />
                                        </TD>
                                        <Num>{formatKes(c.benefit_amount)}</Num>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Section>
            </Page>
        </>
    );
}

WelfareIndex.layout = { breadcrumbs: [{ title: 'Welfare', href: '/welfare' }] };
