import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

export default function ConfirmPassword() {
    return (
        <AuthLayout
            title="確認您的密碼"
            description="這是應用程式的安全區域。請在繼續之前確認您的密碼。"
        >
            <Head title="確認密碼" />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">密碼</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="請輸入您的密碼"
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && (
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                )}
                                確認密碼
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
