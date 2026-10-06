import { Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type Props = {
    status?: string;
    keycloakEnabled: boolean;
    keycloakLoginUrl: string;
};

export default function Login({ status, keycloakEnabled, keycloakLoginUrl }: Props) {
    return (
        <>
            <Head title="Log in" />

            <div className="flex flex-col gap-5">
                <p className="text-sm leading-6 text-white/75">
                    Masuk menggunakan akun SSO KKP. Akses CSAI hanya tersedia untuk pegawai yang terdaftar.
                </p>

                {keycloakEnabled ? (
                    <Button asChild className="h-11 w-full rounded-xl bg-white font-semibold text-[#1b52c9] hover:bg-blue-50">
                        <a href={keycloakLoginUrl} data-test="keycloak-login-button">
                            Login dengan SSO Keycloak
                        </a>
                    </Button>
                ) : (
                    <InputError message="Login SSO Keycloak belum dikonfigurasi." />
                )}
            </div>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-emerald-300">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Login ke akun Anda',
    description: 'Gunakan akun SSO KKP untuk mengakses ChatBot AI dengan data kepegawaian',
};
