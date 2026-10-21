import { Form, Head, useForm, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { CheckField, Field, Select } from '@/components/kertai/fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

type Preferences = {
    notify_sms: boolean;
    notify_email: boolean;
    reminders_opt_out: boolean;
    show_name_publicly: boolean;
    locale: string;
};

export default function Profile({
    status,
    preferences,
}: {
    status?: string;
    preferences: Preferences | null;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Your name and email. Your phone number is changed by the secretary."
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Email address (optional)
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email ?? ''}
                                    name="email"
                                    autoComplete="email"
                                    placeholder="Email address"
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {auth.user.phone && (
                                <p className="text-sm text-muted-foreground">
                                    You sign in with {auth.user.phone}
                                    {auth.user.email
                                        ? ` or ${auth.user.email}`
                                        : ''}
                                    .
                                </p>
                            )}

                            {status && (
                                <p className="text-sm text-green-600">
                                    {status}
                                </p>
                            )}

                            <Button
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                Save
                            </Button>
                        </>
                    )}
                </Form>

                {preferences && <PreferencesForm preferences={preferences} />}
            </div>
        </>
    );
}

function PreferencesForm({ preferences }: { preferences: Preferences }) {
    const form = useForm(preferences);

    return (
        <form
            className="space-y-4 border-t pt-6"
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/me/preferences', { preserveScroll: true });
            }}
        >
            <Heading
                variant="small"
                title="Messages and privacy"
                description="How the group contacts you and what others can see"
            />
            <CheckField
                id="notify_sms"
                label="Send me SMS"
                checked={form.data.notify_sms}
                onChange={(v) => form.setData('notify_sms', v)}
            />
            <CheckField
                id="notify_email"
                label="Send me email"
                checked={form.data.notify_email}
                onChange={(v) => form.setData('notify_email', v)}
            />
            <CheckField
                id="reminders_opt_out"
                label="Turn off reminders"
                hint="Contribution, arrears and meeting reminders. Receipts and payout messages still come."
                checked={form.data.reminders_opt_out}
                onChange={(v) => form.setData('reminders_opt_out', v)}
            />
            <CheckField
                id="show_name_publicly"
                label="Show my name on public fundraiser pages"
                hint="Off by default. When off you appear as a well-wisher."
                checked={form.data.show_name_publicly}
                onChange={(v) => form.setData('show_name_publicly', v)}
            />
            <Field
                label="Language for messages"
                htmlFor="locale"
                className="max-w-xs"
            >
                <Select
                    id="locale"
                    value={form.data.locale}
                    onChange={(e) => form.setData('locale', e.target.value)}
                    options={[
                        { value: 'en', label: 'English' },
                        { value: 'sw', label: 'Kiswahili' },
                    ]}
                />
            </Field>
            <Button disabled={form.processing}>Save preferences</Button>
        </form>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
