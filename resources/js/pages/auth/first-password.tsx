import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function FirstPassword() {
    const form = useForm({ password: '', password_confirmation: '' });

    return (
        <>
            <Head title="Choose your password" />
            <form
                className="flex flex-col gap-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/welcome/password');
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="password">New password</Label>
                    <PasswordInput
                        id="password"
                        autoFocus
                        autoComplete="new-password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                    />
                    <InputError message={form.errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Type it again</Label>
                    <PasswordInput
                        id="password_confirmation"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(e) =>
                            form.setData(
                                'password_confirmation',
                                e.target.value,
                            )
                        }
                    />
                </div>
                <Button disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save and continue
                </Button>
            </form>
        </>
    );
}

FirstPassword.layout = {
    title: 'Choose your own password',
    description:
        'The password you were sent works only once. Pick one only you know.',
};
