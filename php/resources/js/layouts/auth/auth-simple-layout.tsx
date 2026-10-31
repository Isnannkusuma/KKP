import { Link } from '@inertiajs/react';
import { GLASS_CARD, GLASS_PAGE_BG, HEADING_TEXT, SUBTEXT } from '@/lib/glass-theme';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    description,
}: AuthLayoutProps) {
    return (
        <div className={`flex min-h-svh items-center justify-center p-5 sm:p-8 ${GLASS_PAGE_BG}`}>
            <div className={`w-full max-w-[390px] px-6 py-7 sm:px-7 ${GLASS_CARD}`}>
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className={`flex items-center gap-2 font-semibold ${HEADING_TEXT}`}
                        >
                            <img src="/KKP%20LOGO%203.png" alt="Kementerian Kelautan dan Perikanan" className="aspect-square h-8 w-8 shrink-0 rounded-full object-contain ring-1 ring-[#9bd9d2]" />
                            <span>Layanan CS AI Biro SDMAO KKP</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className={`text-[26px] font-bold ${HEADING_TEXT}`}>Masuk ke akun Anda</h1>
                            <p className={`text-center text-sm leading-5 ${SUBTEXT}`}>
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
