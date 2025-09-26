// Components
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { LoaderCircle, Mail, Shield } from 'lucide-react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { status: sharedStatus } = usePage().props;
    const { post, processing } = useForm();

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/email/verification-notification');
    };

    return (
        <AuthLayout
            title="驗證您的電子郵件"
            description="請點擊我們發送到您郵箱的驗證連結來完成註冊"
        >
            <Head title="電子郵件驗證 - RentalRadar">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=noto-sans-tc:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="space-y-6">
                {/* 圖示區域 */}
                <div className="flex justify-center">
                    <div className="h-16 w-16 rounded-full bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900/30 dark:to-indigo-900/30 flex items-center justify-center border border-blue-200 dark:border-blue-800">
                        <Mail className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>

                {/* 說明文字 */}
                <div className="text-center space-y-3">
                    <div className="flex items-center justify-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <Shield className="h-4 w-4" />
                        <span>為了您的帳戶安全</span>
                    </div>
                    <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                        我們已經向您的電子郵件地址發送了一封驗證郵件。
                        <br />
                        請檢查您的收件匣並點擊驗證連結來啟用您的帳戶。
                    </p>
                </div>

                {(status === 'verification-link-sent' || sharedStatus === 'verification-link-sent') && (
                    <div className="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-center">
                        <div className="flex items-center justify-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                            <Mail className="h-4 w-4" />
                            <span>驗證郵件已重新發送</span>
                        </div>
                        <p className="mt-1 text-xs text-green-600 dark:text-green-500">
                            新的驗證連結已發送到您註冊時提供的電子郵件地址
                        </p>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all duration-300"
                    >
                        {processing && (
                            <LoaderCircle className="h-4 w-4 animate-spin mr-2" />
                        )}
                        <Mail className="h-4 w-4 mr-2" />
                        重新發送驗證郵件
                    </Button>

                    <div className="text-center">
                        <button
                            onClick={() => router.post('/logout')}
                            className="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200 underline"
                        >
                            登出
                        </button>
                    </div>
                </form>

                {/* 提示說明 */}
                <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <h4 className="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">
                        沒有收到郵件？
                    </h4>
                    <ul className="text-xs text-blue-700 dark:text-blue-400 space-y-1">
                        <li>• 請檢查您的垃圾郵件或廣告郵件資料夾</li>
                        <li>• 確認您輸入的電子郵件地址是否正確</li>
                        <li>• 如果仍有問題，請點擊上方按鈕重新發送</li>
                    </ul>
                </div>
            </div>
        </AuthLayout>
    );
}
