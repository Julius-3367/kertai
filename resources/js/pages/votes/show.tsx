import { Head, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { ActionButton, actionError } from '@/components/kertai/actions';
import { ActionError } from '@/components/kertai/fields';
import { Page, PageHeader, Progress, Section } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { formatDateTime, percent } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    poll: {
        id: number;
        question: string;
        details: string | null;
        options: string[];
        closes_at: string;
        closed_at: string | null;
        open: boolean;
        created_by: string | null;
        created_at: string | null;
    };
    myChoice: number | null;
    canVote: boolean;
    results: { option: string; votes: number }[] | null;
    turnout: { votes: number; eligible: number };
    voters: string[] | null;
    canManage: boolean;
};

export default function VoteShow({
    poll,
    myChoice,
    canVote,
    results,
    turnout,
    voters,
    canManage,
}: Props) {
    const form = useForm({ choice: myChoice ?? -1 });
    const top = results ? Math.max(...results.map((r) => r.votes)) : 0;

    return (
        <>
            <Head title={poll.question} />
            <Page className="max-w-3xl">
                <PageHeader
                    title={poll.question}
                    description={
                        poll.open
                            ? `Voting closes ${formatDateTime(poll.closes_at)}`
                            : `Closed ${formatDateTime(poll.closed_at ?? poll.closes_at)}`
                    }
                    actions={
                        canManage &&
                        poll.open && (
                            <ActionButton
                                url={`/votes/${poll.id}/close`}
                                variant="outline"
                                confirm="Close this vote now? Members will no longer be able to vote."
                            >
                                <Lock /> Close vote
                            </ActionButton>
                        )
                    }
                />

                {poll.details && (
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {poll.details}
                    </p>
                )}

                {canVote && (
                    <Section
                        title={
                            myChoice === null ? 'Your vote' : 'Change your vote'
                        }
                        description="Only the totals are shown to anyone. You can change your vote until it closes."
                    >
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(`/votes/${poll.id}/vote`, {
                                    preserveScroll: true,
                                });
                            }}
                            className="grid gap-3"
                        >
                            <ActionError message={actionError(form.errors)} />
                            <div className="grid gap-2">
                                {poll.options.map((option, i) => (
                                    <label
                                        key={i}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-3 text-sm',
                                            form.data.choice === i &&
                                                'border-emerald-600 bg-emerald-50 dark:bg-emerald-950',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="choice"
                                            className="size-4 accent-emerald-700"
                                            checked={form.data.choice === i}
                                            onChange={() =>
                                                form.setData('choice', i)
                                            }
                                        />
                                        {option}
                                    </label>
                                ))}
                            </div>
                            <Button
                                className="justify-self-start"
                                disabled={
                                    form.processing ||
                                    form.data.choice < 0 ||
                                    form.data.choice === myChoice
                                }
                            >
                                {myChoice === null ? 'Vote' : 'Change vote'}
                            </Button>
                        </form>
                    </Section>
                )}

                <Section
                    title="Results"
                    description={`${turnout.votes} of ${turnout.eligible} members voted (${percent(turnout.votes, turnout.eligible)}%)`}
                >
                    {results ? (
                        <ul className="grid gap-3">
                            {results.map((r, i) => (
                                <li key={i} className="grid gap-1">
                                    <div className="flex justify-between gap-3 text-sm">
                                        <span
                                            className={cn(
                                                !poll.open &&
                                                    r.votes === top &&
                                                    top > 0 &&
                                                    'font-semibold',
                                            )}
                                        >
                                            {r.option}
                                            {myChoice === i && (
                                                <span className="ml-2 text-xs text-emerald-700 dark:text-emerald-400">
                                                    your vote
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-muted-foreground tabular-nums">
                                            {r.votes} (
                                            {percent(r.votes, turnout.votes)}%)
                                        </span>
                                    </div>
                                    <Progress
                                        value={percent(r.votes, turnout.votes)}
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Results show once you have voted, or when the vote
                            closes.
                        </p>
                    )}
                </Section>

                {voters && (
                    <Section
                        title="Who has voted"
                        description="Names only. Nobody can see how a member voted."
                    >
                        {voters.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No votes yet.
                            </p>
                        ) : (
                            <p className="text-sm">{voters.join(', ')}</p>
                        )}
                    </Section>
                )}

                {poll.created_by && (
                    <p className="text-xs text-muted-foreground">
                        Opened by {poll.created_by}
                        {poll.created_at &&
                            ` on ${formatDateTime(poll.created_at)}`}
                    </p>
                )}
            </Page>
        </>
    );
}

VoteShow.layout = {
    breadcrumbs: [{ title: 'Votes', href: '/votes' }],
};
