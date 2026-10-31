import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PRIMARY_BUTTON, SUBTEXT } from '@/lib/glass-theme';

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
                <p className={`text-sm leading-6 ${SUBTEXT}`}>
                    Masuk menggunakan NIP dan password atau akun SSO KKP.
                </p>

                <Form action="/login" method="post" className="flex flex-col gap-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="nip">NIP</Label>
                                <Input
                                    id="nip"
                                    name="nip"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    placeholder="Masukkan NIP"
                                />
                                <InputError message={errors.nip ?? errors.login} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                    placeholder="Masukkan password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <Button
                                type="submit"
                                className={`h-11 w-full rounded-xl font-semibold ${PRIMARY_BUTTON}`}
                                disabled={processing}
                                data-test="nip-login-button"
                            >
                                {processing ? 'Memproses...' : 'Login dengan NIP'}
                            </Button>
                        </>
                    )}
                </Form>

                {keycloakEnabled && (
                    <>
                        <div className={`text-center text-sm ${SUBTEXT}`}>atau</div>
                        <Button
                            asChild
                            variant="outline"
                            className="h-11 w-full rounded-xl border-[#cfe1e2] bg-white/70 font-semibold text-[#087f82] hover:bg-[#e6f3f1]"
                        >
                            <a href={keycloakLoginUrl} target="_top" data-test="keycloak-login-button">
                                Login dengan SSO Keycloak
                            </a>
                        </Button>
                    </>
                )}

                {!keycloakEnabled && (
                    <p className={`text-center text-xs ${SUBTEXT}`}>
                        Login SSO tidak tersedia; silakan masuk menggunakan NIP.
                    </p>
                )}
            </div>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-emerald-700">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Login ke akun Anda',
    description: 'Gunakan NIP dan password atau akun SSO KKP untuk mengakses ChatBot AI',
};
