import { Head, Link, useForm } from '@inertiajs/react';
import { actionError } from '@/components/kertai/actions';
import {
    ActionError,
    CheckField,
    Field,
    MoneyInput,
    Textarea,
} from '@/components/kertai/fields';
import { Page, PageHeader, Section } from '@/components/kertai/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { today } from '@/lib/format';

type Fundraiser = {
    id: number;
    title: string;
    summary: string | null;
    description: string | null;
    beneficiary: string | null;
    target_input: string;
    starts_on: string;
    ends_on: string | null;
    show_contributors: boolean;
};

export default function FundraiserForm({
    fundraiser,
}: {
    fundraiser: Fundraiser | null;
}) {
    const form = useForm({
        title: fundraiser?.title ?? '',
        summary: fundraiser?.summary ?? '',
        description: fundraiser?.description ?? '',
        beneficiary: fundraiser?.beneficiary ?? '',
        target: fundraiser?.target_input ?? '',
        starts_on: fundraiser?.starts_on ?? today(),
        ends_on: fundraiser?.ends_on ?? '',
        show_contributors: fundraiser?.show_contributors ?? true,
    });

    return (
        <>
            <Head
                title={
                    fundraiser ? `Edit ${fundraiser.title}` : 'New fundraiser'
                }
            />
            <Page className="max-w-3xl">
                <PageHeader
                    title={
                        fundraiser
                            ? `Edit ${fundraiser.title}`
                            : 'New fundraiser'
                    }
                    description="Saved as a draft. Publish it from its page when ready."
                />
                <form
                    className="grid gap-6"
                    onSubmit={(e) => {
                        e.preventDefault();

                        if (fundraiser) {
                            form.put(`/fundraisers/${fundraiser.id}`);
                        } else {
                            form.post('/fundraisers');
                        }
                    }}
                >
                    <ActionError message={actionError(form.errors)} />
                    <Section>
                        <div className="grid gap-4">
                            <Field
                                label="Title"
                                htmlFor="title"
                                error={form.errors.title}
                            >
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    placeholder="Empowerment day 2026"
                                />
                            </Field>
                            <Field
                                label="One-line summary"
                                htmlFor="summary"
                                error={form.errors.summary}
                                hint="Shown on WhatsApp when the link is shared."
                            >
                                <Input
                                    id="summary"
                                    value={form.data.summary}
                                    onChange={(e) =>
                                        form.setData('summary', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                label="Story"
                                htmlFor="description"
                                error={form.errors.description}
                            >
                                <Textarea
                                    id="description"
                                    rows={6}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Beneficiary"
                                    htmlFor="beneficiary"
                                    error={form.errors.beneficiary}
                                >
                                    <Input
                                        id="beneficiary"
                                        value={form.data.beneficiary}
                                        onChange={(e) =>
                                            form.setData(
                                                'beneficiary',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Target"
                                    htmlFor="target"
                                    error={form.errors.target}
                                >
                                    <MoneyInput
                                        id="target"
                                        value={form.data.target}
                                        onChange={(e) =>
                                            form.setData(
                                                'target',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Opens"
                                    htmlFor="starts_on"
                                    error={form.errors.starts_on}
                                >
                                    <Input
                                        id="starts_on"
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
                                <Field
                                    label="Closes (optional)"
                                    htmlFor="ends_on"
                                    error={form.errors.ends_on}
                                >
                                    <Input
                                        id="ends_on"
                                        type="date"
                                        value={form.data.ends_on}
                                        onChange={(e) =>
                                            form.setData(
                                                'ends_on',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <CheckField
                                id="show_contributors"
                                label="List contributors on the public page"
                                hint="Only people who tick 'show my name' are listed; everyone else stays anonymous."
                                checked={form.data.show_contributors}
                                onChange={(v) =>
                                    form.setData('show_contributors', v)
                                }
                            />
                        </div>
                    </Section>
                    <div className="flex gap-2">
                        <Button disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {fundraiser ? 'Save' : 'Create'}
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={
                                    fundraiser
                                        ? `/fundraisers/${fundraiser.id}`
                                        : '/fundraisers'
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

FundraiserForm.layout = {
    breadcrumbs: [{ title: 'Fundraisers', href: '/fundraisers' }],
};
