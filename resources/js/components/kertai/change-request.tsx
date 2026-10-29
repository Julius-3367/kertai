import { UserPen } from 'lucide-react';
import { ActionButton, FormDialog } from '@/components/kertai/actions';
import { Field } from '@/components/kertai/fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';

export type PendingChange = {
    id: number;
    at: string;
    rows: { field: string; from: string | null; to: string | null }[];
};

type Current = {
    phone: string;
    email: string | null;
    date_of_birth: string | null;
    nominee_name: string | null;
    nominee_phone: string | null;
    nominee_relationship: string | null;
    nominee_id_number: string | null;
};

const INPUTS: { key: keyof Current; type?: string }[] = [
    { key: 'phone', type: 'tel' },
    { key: 'email', type: 'email' },
    { key: 'date_of_birth', type: 'date' },
    { key: 'nominee_name' },
    { key: 'nominee_phone', type: 'tel' },
    { key: 'nominee_relationship' },
    { key: 'nominee_id_number' },
];

/** The member's own "please update my details" button. */
export function RequestChangeButton({
    current,
    labels,
}: {
    current: Current;
    labels: Record<string, string>;
}) {
    return (
        <FormDialog
            trigger={
                <Button variant="outline">
                    <UserPen /> Update my details
                </Button>
            }
            title="Update my details"
            description="Change what you need and send it. The secretary approves it before your record changes."
            initial={
                Object.fromEntries(
                    INPUTS.map(({ key }) => [key, current[key] ?? '']),
                ) as Record<keyof Current, string>
            }
            action="/me/changes"
            submitLabel="Send for approval"
            wide
        >
            {(form) => (
                <div className="grid gap-3 sm:grid-cols-2">
                    {INPUTS.map(({ key, type }) => (
                        <Field
                            key={key}
                            label={labels[key] ?? key}
                            htmlFor={`cr-${key}`}
                            error={(form.errors as Record<string, string>)[key]}
                        >
                            <Input
                                id={`cr-${key}`}
                                type={type ?? 'text'}
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                        </Field>
                    ))}
                </div>
            )}
        </FormDialog>
    );
}

/** A waiting request: the member can withdraw it, the secretary decides. */
export function PendingChangePanel({
    change,
    isSelf,
    canDecide,
}: {
    change: PendingChange;
    isSelf: boolean;
    canDecide: boolean;
}) {
    return (
        <div className="grid gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
            <p className="font-medium">
                {isSelf
                    ? 'Your change is waiting for approval'
                    : 'This member asked to change their details'}{' '}
                <span className="font-normal text-amber-800 dark:text-amber-300">
                    ({formatDateTime(change.at)})
                </span>
            </p>
            <ul className="grid gap-1">
                {change.rows.map((row) => (
                    <li key={row.field}>
                        {row.field}:{' '}
                        <span className="line-through opacity-70">
                            {row.from ?? 'blank'}
                        </span>{' '}
                        → <strong>{row.to ?? 'blank'}</strong>
                    </li>
                ))}
            </ul>
            <div className="flex flex-wrap gap-2">
                {isSelf && (
                    <ActionButton
                        url={`/me/changes/${change.id}/withdraw`}
                        size="sm"
                        variant="outline"
                        confirm="Withdraw this request?"
                    >
                        Withdraw
                    </ActionButton>
                )}
                {canDecide && (
                    <>
                        <ActionButton
                            url={`/member-changes/${change.id}/decide`}
                            data={{ decision: 'approved' }}
                            size="sm"
                        >
                            Approve and save
                        </ActionButton>
                        <FormDialog
                            trigger={
                                <Button size="sm" variant="outline">
                                    Reject
                                </Button>
                            }
                            title="Reject this change"
                            initial={{ decision: 'rejected', reason: '' }}
                            action={`/member-changes/${change.id}/decide`}
                            submitLabel="Reject"
                            destructive
                        >
                            {(form) => (
                                <Field
                                    label="Reason (sent to the member)"
                                    htmlFor="cr-reason"
                                >
                                    <Input
                                        id="cr-reason"
                                        value={form.data.reason}
                                        onChange={(e) =>
                                            form.setData(
                                                'reason',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            )}
                        </FormDialog>
                    </>
                )}
            </div>
        </div>
    );
}
