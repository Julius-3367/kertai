import { Head, Link } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { FormDialog, fieldError } from '@/components/kertai/actions';
import { CheckField, Field, Textarea } from '@/components/kertai/fields';
import {
    EmptyState,
    Page,
    PageHeader,
    Section,
} from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, percent } from '@/lib/format';
import { cn } from '@/lib/utils';

type Poll = {
    id: number;
    question: string;
    closes_at: string;
    open: boolean;
    votes: number;
    eligible: number;
    voted: boolean;
};

function inDays(days: number): string {
    const d = new Date();
    d.setDate(d.getDate() + days);

    return d.toISOString().slice(0, 10);
}

export default function VotesIndex({
    polls,
    canManage,
}: {
    polls: Poll[];
    canManage: boolean;
}) {
    return (
        <>
            <Head title="Votes" />
            <Page>
                <PageHeader
                    title="Votes"
                    description="Decisions the group makes between meetings. Votes are secret: officials see who voted, not how."
                    actions={
                        canManage && (
                            <FormDialog
                                trigger={
                                    <Button>
                                        <Plus /> New vote
                                    </Button>
                                }
                                title="Ask the members"
                                description="Leave the answers blank for a simple Yes / No."
                                initial={{
                                    question: '',
                                    details: '',
                                    options: ['', ''] as string[],
                                    closes_on: inDays(7),
                                    notify: true,
                                }}
                                action="/votes"
                                submitLabel="Open the vote"
                            >
                                {(form) => (
                                    <>
                                        <Field
                                            label="Question"
                                            htmlFor="v-q"
                                            error={form.errors.question}
                                        >
                                            <Input
                                                id="v-q"
                                                value={form.data.question}
                                                placeholder="e.g. Raise the monthly contribution to KES 2,000?"
                                                onChange={(e) =>
                                                    form.setData(
                                                        'question',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Details (optional)"
                                            htmlFor="v-d"
                                        >
                                            <Textarea
                                                id="v-d"
                                                rows={3}
                                                value={form.data.details}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'details',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <div className="grid gap-2">
                                            <span className="text-sm font-medium">
                                                Answers
                                            </span>
                                            {form.data.options.map(
                                                (option, i) => (
                                                    <div
                                                        key={i}
                                                        className="flex gap-2"
                                                    >
                                                        <Input
                                                            value={option}
                                                            placeholder={
                                                                i === 0
                                                                    ? 'Yes'
                                                                    : i === 1
                                                                      ? 'No'
                                                                      : `Answer ${i + 1}`
                                                            }
                                                            aria-label={`Answer ${i + 1}`}
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'options',
                                                                    form.data.options.map(
                                                                        (
                                                                            o,
                                                                            j,
                                                                        ) =>
                                                                            j ===
                                                                            i
                                                                                ? e
                                                                                      .target
                                                                                      .value
                                                                                : o,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        {i >= 2 && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Remove answer"
                                                                onClick={() =>
                                                                    form.setData(
                                                                        'options',
                                                                        form.data.options.filter(
                                                                            (
                                                                                _,
                                                                                j,
                                                                            ) =>
                                                                                j !==
                                                                                i,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <X />
                                                            </Button>
                                                        )}
                                                    </div>
                                                ),
                                            )}
                                            {form.data.options.length < 6 && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="justify-self-start"
                                                    onClick={() =>
                                                        form.setData(
                                                            'options',
                                                            [
                                                                ...form.data
                                                                    .options,
                                                                '',
                                                            ],
                                                        )
                                                    }
                                                >
                                                    <Plus /> Add an answer
                                                </Button>
                                            )}
                                        </div>
                                        <Field
                                            label="Voting closes at the end of"
                                            htmlFor="v-c"
                                            error={fieldError(
                                                form.errors,
                                                'closes_on',
                                            )}
                                        >
                                            <Input
                                                id="v-c"
                                                type="date"
                                                value={form.data.closes_on}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'closes_on',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <CheckField
                                            id="v-n"
                                            label="Tell every member by SMS and email"
                                            hint="They also see it under the bell in the app."
                                            checked={form.data.notify}
                                            onChange={(v) =>
                                                form.setData('notify', v)
                                            }
                                        />
                                    </>
                                )}
                            </FormDialog>
                        )
                    }
                />
                <Section>
                    {polls.length === 0 ? (
                        <EmptyState title="No votes yet">
                            {canManage
                                ? 'Open a vote to let members decide something without waiting for a meeting.'
                                : 'When the officials put a question to the group it will show here.'}
                        </EmptyState>
                    ) : (
                        <ul className="-my-2 divide-y">
                            {polls.map((poll) => (
                                <li key={poll.id}>
                                    <Link
                                        href={`/votes/${poll.id}`}
                                        className="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-muted/40"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-medium">
                                                {poll.question}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {poll.open
                                                    ? `Closes ${formatDate(poll.closes_at)}`
                                                    : `Closed`}{' '}
                                                · {poll.votes} of{' '}
                                                {poll.eligible} voted (
                                                {percent(
                                                    poll.votes,
                                                    poll.eligible,
                                                )}
                                                %)
                                            </span>
                                        </span>
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                                !poll.open
                                                    ? 'bg-muted text-muted-foreground'
                                                    : poll.voted
                                                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                                      : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                                            )}
                                        >
                                            {!poll.open
                                                ? 'Closed'
                                                : poll.voted
                                                  ? 'You voted'
                                                  : 'Vote now'}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </Page>
        </>
    );
}

VotesIndex.layout = {
    breadcrumbs: [{ title: 'Votes', href: '/votes' }],
};
