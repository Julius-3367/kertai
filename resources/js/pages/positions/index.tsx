import { Head, useForm } from '@inertiajs/react';
import { Copy, Trash2, UserPlus } from 'lucide-react';
import {
    ActionButton,
    FormDialog,
    actionError,
} from '@/components/kertai/actions';
import {
    ActionError,
    CheckField,
    Field,
    MemberSelect,
    Select,
} from '@/components/kertai/fields';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Table, TBody, TD, TH, THead, TR } from '@/components/kertai/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, today } from '@/lib/format';
import type { Option } from '@/types/kertai';

type Position = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    permissions: string[];
    is_official: boolean;
    is_system: boolean;
    max_holders: number | null;
    holders: {
        term_id: number;
        member_id: number;
        name: string;
        starts_on: string;
        ends_on: string | null;
        is_acting: boolean;
    }[];
};

type Permission = { value: string; label: string; money: boolean };

export default function PositionsIndex({
    positions,
    permissions,
    members,
    history,
}: {
    positions: Position[];
    permissions: Permission[];
    members: Option[];
    history: {
        id: number;
        position: string;
        member: string;
        starts_on: string;
        ends_on: string | null;
        is_acting: boolean;
        resolution_reference: string | null;
        ended_reason: string | null;
    }[];
}) {
    return (
        <>
            <Head title="Positions" />
            <Page>
                <PageHeader
                    title="Positions and terms"
                    description="Each position is a bundle of permissions held for a term. Create vice positions by copying an existing one."
                    actions={
                        <FormDialog
                            title="New position"
                            description="Starts with the same access as the position you copy. Adjust it afterwards."
                            action="/positions"
                            submitLabel="Create"
                            initial={{
                                name: '',
                                copy_from: String(positions[0]?.id ?? ''),
                                description: '',
                                max_holders: '1',
                            }}
                            trigger={
                                <Button>
                                    <Copy /> New position
                                </Button>
                            }
                        >
                            {(form) => (
                                <>
                                    <Field
                                        label="Name"
                                        htmlFor="pos-name"
                                        error={form.errors.name}
                                    >
                                        <Input
                                            id="pos-name"
                                            placeholder="Vice chairperson"
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Copy access from"
                                        htmlFor="pos-copy"
                                        error={form.errors.copy_from}
                                    >
                                        <Select
                                            id="pos-copy"
                                            value={form.data.copy_from}
                                            onChange={(e) =>
                                                form.setData(
                                                    'copy_from',
                                                    e.target.value,
                                                )
                                            }
                                            options={positions.map((p) => ({
                                                value: p.id,
                                                label: p.name,
                                            }))}
                                        />
                                    </Field>
                                    <Field
                                        label="Description"
                                        htmlFor="pos-desc"
                                    >
                                        <Input
                                            id="pos-desc"
                                            value={form.data.description}
                                            onChange={(e) =>
                                                form.setData(
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Most people who can hold it at once"
                                        htmlFor="pos-max"
                                        hint="Leave empty for no limit."
                                    >
                                        <Input
                                            id="pos-max"
                                            inputMode="numeric"
                                            value={form.data.max_holders}
                                            onChange={(e) =>
                                                form.setData(
                                                    'max_holders',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </>
                            )}
                        </FormDialog>
                    }
                />

                <div className="grid gap-6">
                    {positions.map((p) => (
                        <PositionCard
                            key={p.id}
                            position={p}
                            permissions={permissions}
                            members={members}
                        />
                    ))}
                </div>

                <Section title="Term history">
                    <Table>
                        <THead>
                            <TR>
                                <TH>Position</TH>
                                <TH>Member</TH>
                                <TH>From</TH>
                                <TH>To</TH>
                                <TH>Resolution</TH>
                                <TH>Note</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {history.map((h) => (
                                <TR key={h.id}>
                                    <TD>
                                        {h.is_acting ? 'Acting ' : ''}
                                        {h.position}
                                    </TD>
                                    <TD>{h.member}</TD>
                                    <TD>{formatDate(h.starts_on)}</TD>
                                    <TD>
                                        {h.ends_on
                                            ? formatDate(h.ends_on)
                                            : 'present'}
                                    </TD>
                                    <TD>{h.resolution_reference ?? '—'}</TD>
                                    <TD className="text-muted-foreground">
                                        {h.ended_reason ?? ''}
                                    </TD>
                                </TR>
                            ))}
                        </TBody>
                    </Table>
                </Section>
            </Page>
        </>
    );
}

function PositionCard({
    position,
    permissions,
    members,
}: {
    position: Position;
    permissions: Permission[];
    members: Option[];
}) {
    const form = useForm({
        name: position.name,
        description: position.description ?? '',
        max_holders:
            position.max_holders === null ? '' : String(position.max_holders),
        permissions: position.permissions,
    });

    const toggle = (value: string, on: boolean) =>
        form.setData(
            'permissions',
            on
                ? [...form.data.permissions, value]
                : form.data.permissions.filter((p) => p !== value),
        );

    return (
        <Section
            title={position.name}
            description={position.description ?? undefined}
            actions={
                <>
                    <FormDialog
                        title={`Appoint ${position.name}`}
                        description="Acting appointments need an end date and lapse on their own."
                        action={`/positions/${position.id}/assign`}
                        submitLabel="Appoint"
                        initial={{
                            member_id: '',
                            starts_on: today(),
                            ends_on: '',
                            is_acting: false,
                            resolution_reference: '',
                        }}
                        trigger={
                            <Button size="sm" variant="outline">
                                <UserPlus /> Appoint
                            </Button>
                        }
                    >
                        {(f) => (
                            <>
                                <Field
                                    label="Member"
                                    htmlFor={`m-${position.id}`}
                                    error={f.errors.member_id}
                                >
                                    <MemberSelect
                                        id={`m-${position.id}`}
                                        members={members}
                                        value={f.data.member_id}
                                        onChange={(e) =>
                                            f.setData(
                                                'member_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label="From"
                                        htmlFor={`s-${position.id}`}
                                        error={f.errors.starts_on}
                                    >
                                        <Input
                                            id={`s-${position.id}`}
                                            type="date"
                                            value={f.data.starts_on}
                                            onChange={(e) =>
                                                f.setData(
                                                    'starts_on',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="To (optional)"
                                        htmlFor={`e-${position.id}`}
                                        error={f.errors.ends_on}
                                    >
                                        <Input
                                            id={`e-${position.id}`}
                                            type="date"
                                            value={f.data.ends_on}
                                            onChange={(e) =>
                                                f.setData(
                                                    'ends_on',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                                <CheckField
                                    id={`a-${position.id}`}
                                    label="Acting appointment"
                                    checked={f.data.is_acting}
                                    onChange={(v) => f.setData('is_acting', v)}
                                />
                                <Field
                                    label="Resolution / minute number"
                                    htmlFor={`r-${position.id}`}
                                >
                                    <Input
                                        id={`r-${position.id}`}
                                        value={f.data.resolution_reference}
                                        onChange={(e) =>
                                            f.setData(
                                                'resolution_reference',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </>
                        )}
                    </FormDialog>
                    {!position.is_system && position.holders.length === 0 && (
                        <ActionButton
                            url={`/positions/${position.id}`}
                            method="delete"
                            size="sm"
                            variant="ghost"
                            confirm={`Delete ${position.name}?`}
                            aria-label="Delete position"
                        >
                            <Trash2 />
                        </ActionButton>
                    )}
                </>
            }
        >
            <div className="grid gap-6 lg:grid-cols-[1fr_2fr]">
                <div>
                    <h3 className="mb-2 text-sm font-medium">Holders</h3>
                    {position.holders.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Vacant</p>
                    ) : (
                        <ul className="space-y-2">
                            {position.holders.map((h) => (
                                <li
                                    key={h.term_id}
                                    className="flex items-center justify-between gap-2 text-sm"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {h.name}
                                        </span>
                                        {h.is_acting && (
                                            <StatusBadge
                                                status="pending"
                                                label="Acting"
                                                className="ml-2"
                                            />
                                        )}
                                        <span className="block text-xs text-muted-foreground">
                                            since {formatDate(h.starts_on)}
                                            {h.ends_on
                                                ? `, until ${formatDate(h.ends_on)}`
                                                : ''}
                                        </span>
                                    </span>
                                    <FormDialog
                                        title={`End ${h.name}'s term`}
                                        action={`/terms/${h.term_id}/end`}
                                        method="put"
                                        submitLabel="End term"
                                        initial={{
                                            ends_on: today(),
                                            ended_reason: '',
                                        }}
                                        trigger={
                                            <Button size="sm" variant="ghost">
                                                End
                                            </Button>
                                        }
                                    >
                                        {(f) => (
                                            <>
                                                <Field
                                                    label="Last day"
                                                    htmlFor={`end-${h.term_id}`}
                                                    error={f.errors.ends_on}
                                                >
                                                    <Input
                                                        id={`end-${h.term_id}`}
                                                        type="date"
                                                        value={f.data.ends_on}
                                                        onChange={(e) =>
                                                            f.setData(
                                                                'ends_on',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Reason"
                                                    htmlFor={`why-${h.term_id}`}
                                                >
                                                    <Input
                                                        id={`why-${h.term_id}`}
                                                        placeholder="Term ended at AGM"
                                                        value={
                                                            f.data.ended_reason
                                                        }
                                                        onChange={(e) =>
                                                            f.setData(
                                                                'ended_reason',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                            </>
                                        )}
                                    </FormDialog>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(`/positions/${position.id}`, {
                            preserveScroll: true,
                        });
                    }}
                    className="space-y-3"
                >
                    <ActionError message={actionError(form.errors)} />
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field
                            label="Name"
                            htmlFor={`pn-${position.id}`}
                            error={form.errors.name}
                        >
                            <Input
                                id={`pn-${position.id}`}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Description"
                            htmlFor={`pd-${position.id}`}
                            className="sm:col-span-2"
                        >
                            <Input
                                id={`pd-${position.id}`}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {permissions.map((perm) => (
                            <CheckField
                                key={perm.value}
                                id={`${position.id}-${perm.value}`}
                                label={perm.label}
                                hint={perm.money ? 'Money' : undefined}
                                checked={form.data.permissions.includes(
                                    perm.value,
                                )}
                                onChange={(on) => toggle(perm.value, on)}
                            />
                        ))}
                    </div>
                    <Button
                        size="sm"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save access
                    </Button>
                </form>
            </div>
        </Section>
    );
}

PositionsIndex.layout = {
    breadcrumbs: [{ title: 'Positions', href: '/positions' }],
};
