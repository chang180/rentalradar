import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle, LogIn, Shield } from 'lucide-react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    return (
        <AuthLayout
            title="登入 RentalRadar"
            description="歡迎回來！請登入您的帳戶以繼續使用智慧租屋分析"
        >
            <Head title="登入 - RentalRadar">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=noto-sans-tc:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            {status && (
                <div className="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-center dark:border-green-800 dark:bg-green-900/20">
                    <div className="flex items-center justify-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                        <Shield className="h-4 w-4" />
                        <span>{status}</span>
                    </div>
                </div>
            )}

            <Form
                {...AuthenticatedSessionController.store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="email"
                                    className="font-medium text-gray-700 dark:text-gray-300"
                                >
                                    電子郵件
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="example@email.com"
                                    className="border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:focus:border-blue-400 dark:focus:ring-blue-400"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label
                                        htmlFor="password"
                                        className="font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        密碼
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm text-blue-600 transition-colors duration-200 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                            tabIndex={5}
                                        >
                                            忘記密碼？
                                        </TextLink>
                                    )}
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="請輸入密碼"
                                    className="border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:focus:border-blue-400 dark:focus:ring-blue-400"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className="border-gray-300 data-[state=checked]:border-blue-600 data-[state=checked]:bg-blue-600 dark:border-gray-600"
                                />
                                <Label
                                    htmlFor="remember"
                                    className="cursor-pointer text-sm text-gray-700 dark:text-gray-300"
                                >
                                    記住我的登入狀態
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full transform bg-gradient-to-r from-blue-600 to-indigo-600 py-3 font-semibold text-white shadow-lg transition-all duration-300 hover:scale-105 hover:from-blue-700 hover:to-indigo-700 hover:shadow-xl"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && (
                                    <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                                )}
                                <LogIn className="mr-2 h-4 w-4" />
                                立即登入
                            </Button>

                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-center text-xs text-gray-500 dark:border-blue-800 dark:bg-blue-900/20 dark:text-gray-400">
                                安全登入，保護您的租屋市場分析資料
                            </div>
                        </div>

                        <div className="text-center text-sm text-gray-600 dark:text-gray-400">
                            還沒有帳號嗎？{' '}
                            <TextLink
                                href={register()}
                                tabIndex={6}
                                className="font-medium text-blue-600 transition-colors duration-200 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                                立即註冊
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
