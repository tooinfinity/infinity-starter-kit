import { Form, Head } from '@inertiajs/react';
// Components
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';
/* @chisel-logout */
import { logout } from '@/routes';
/* @end-chisel-logout */
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { __ } = useTranslation();

    return (
        <AuthLayout
            title={__('Verify email')}
            description={__(
                'Please verify your email address by clicking on the link we just emailed to you.',
            )}
        >
            <Head title={__('Email verification')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {__(
                        'A new verification link has been sent to the email address you provided during registration.',
                    )}
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {__('Resend verification email')}
                        </Button>

                        {/* @chisel-logout */}
                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {__('Log out')}
                        </TextLink>
                        {/* @end-chisel-logout */}
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
