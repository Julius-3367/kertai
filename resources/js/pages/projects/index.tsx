import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormDialog } from '@/components/kertai/actions';
import {
    Field,
    MemberSelect,
    Select,
    Textarea,
} from '@/components/kertai/fields';
import { EmptyState, Page, PageHeader } from '@/components/kertai/page';
import { StatusBadge } from '@/components/kertai/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { formatKes, today } from '@/lib/format';
import type { Option } from '@/types/kertai';

type Row = {
    id: number;
    name: string;
    category: string;
    status: string;
    starts_on: string;
    coordinator: string | null;
    participants: number;
    balance: number;
    contributions: number;
    income: number;
    expenses: number;
    profit: number;
    distributed: number;
};

export default function ProjectsIndex({
    projects,
    categories,
    members,
}: {
    projects: Row[];
    categories: Record<string, string>;
    members: Option[];
}) {
    const can = useCan();

    return (
        <>
            <Head title="Projects" />
            <Page>
                <PageHeader
                    title="Projects"
                    description="Each project has its own fund, participants, stock and profit-and-loss."
                    actions={
                        can('projects.manage') && (
                            <FormDialog
                                title="New project"
                                action="/projects"
                                submitLabel="Create"
                                initial={{
                                    name: '',
                                    category: 'crops',
                                    description: '',
                                    starts_on: today(),
                                    ends_on: '',
                                    share_basis: 'contribution',
                                    coordinator_member_id: '',
                                    status: 'active',
                                }}
                                trigger={
                                    <Button>
                                        <Plus /> New project
                                    </Button>
                                }
                            >
                                {(form) => (
                                    <>
                                        <Field
                                            label="Name"
                                            htmlFor="pj-name"
                                            error={form.errors.name}
                                        >
                                            <Input
                                                id="pj-name"
                                                value={form.data.name}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field
                                                label="Category"
                                                htmlFor="pj-cat"
                                                error={form.errors.category}
                                            >
                                                <Select
                                                    id="pj-cat"
                                                    value={form.data.category}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'category',
                                                            e.target.value,
                                                        )
                                                    }
                                                    options={Object.entries(
                                                        categories,
                                                    ).map(([value, label]) => ({
                                                        value,
                                                        label,
                                                    }))}
                                                />
                                            </Field>
                                            <Field
                                                label="Starts"
                                                htmlFor="pj-start"
                                                error={form.errors.starts_on}
                                            >
                                                <Input
                                                    id="pj-start"
                                                    type="date"
                                                    value={form.data.starts_on}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'starts_on',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </div>
                                        <Field
                                            label="Profit shared"
                                            htmlFor="pj-basis"
                                            error={form.errors.share_basis}
                                        >
                                            <Select
                                                id="pj-basis"
                                                value={form.data.share_basis}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'share_basis',
                                                        e.target.value,
                                                    )
                                                }
                                                options={[
                                                    {
                                                        value: 'contribution',
                                                        label: 'In proportion to what each member put in',
                                                    },
                                                    {
                                                        value: 'equal',
                                                        label: 'Equally between participants',
                                                    },
                                                ]}
                                            />
                                        </Field>
                                        <Field
                                            label="Coordinator"
                                            htmlFor="pj-coord"
                                        >
                                            <MemberSelect
                                                id="pj-coord"
                                                members={members}
                                                placeholder="None"
                                                value={
                                                    form.data
                                                        .coordinator_member_id
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'coordinator_member_id',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Description"
                                            htmlFor="pj-desc"
                                        >
                                            <Textarea
                                                id="pj-desc"
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
                                )}
                            </FormDialog>
                        )
                    }
                />

                {projects.length === 0 ? (
                    <EmptyState title="No projects yet" />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {projects.map((p) => (
                            <Link
                                key={p.id}
                                href={`/projects/${p.id}`}
                                className="space-y-3 rounded-xl border bg-card p-4 shadow-xs transition hover:shadow-md"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="font-semibold">
                                            {p.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {categories[p.category] ??
                                                p.category}{' '}
                                            · {p.participants} participants
                                            {p.coordinator
                                                ? ` · ${p.coordinator}`
                                                : ''}
                                        </div>
                                    </div>
                                    <StatusBadge status={p.status} />
                                </div>
                                <dl className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Put in
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatKes(p.contributions)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Income
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatKes(p.income)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Spent
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatKes(p.expenses)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Profit
                                        </dt>
                                        <dd
                                            className={`tabular-nums ${p.profit < 0 ? 'text-red-700 dark:text-red-400' : ''}`}
                                        >
                                            {formatKes(p.profit)}
                                        </dd>
                                    </div>
                                </dl>
                                <div className="text-xs text-muted-foreground">
                                    Fund balance {formatKes(p.balance)}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </Page>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: '/projects' }],
};
