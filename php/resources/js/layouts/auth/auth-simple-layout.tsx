import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh items-center justify-center bg-[radial-gradient(circle_at_top_right,_#0a83c7_0,_#063b69_40%,_#06192e_100%)] p-5 text-white sm:p-8">
            <div className="w-full max-w-[390px] rounded-[22px] border border-white/20 bg-[#23476d]/95 px-6 py-7 shadow-2xl shadow-slate-950/50 backdrop-blur-xl sm:px-7">
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex items-center gap-2 font-semibold"
                        >
                            <img src="/KKP%20LOGO%203.png" alt="Kementerian Kelautan dan Perikanan" className="aspect-square h-8 w-8 shrink-0 rounded-full object-contain ring-1 ring-cyan-300/80" />
                            <span>Layanan AI KKP</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-[26px] font-bold">Masuk ke akun Anda</h1>
                            <p className="text-center text-sm leading-5 text-blue-100/70">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
