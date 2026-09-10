import { Head, Link, usePage } from '@inertiajs/react';
import type {ReactNode} from 'react';
import { dashboard, login } from '@/routes';
import { index as chatIndex } from '@/routes/chat';
import type {User} from '@/types';

export default function Welcome() {
    const { auth } = usePage<User>().props;

    return (
        <>
            <Head title="Layanan AI KKP — Asisten Kelautan & Perikanan" />

            {/* Fraunces (display), IBM Plex Sans (body), IBM Plex Mono (label) */}
            <link
                rel="stylesheet"
                href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap"
            />

            <div className="min-h-screen bg-[#EAF3EF] font-['IBM_Plex_Sans'] dark:bg-[#08202E]">
                {/* ============================= HERO ============================= */}
                <section className="overflow-hidden bg-linear-to-b text-[#EAF3EF] relative from-[#114B5F] to-[#0A2E3F] dark:from-[#0C3648] dark:to-[#061620]">
                    {/* Signature element: garis kontur batimetri (peta kedalaman laut) */}
                    <BathymetricLines />

                    <header className="px-6 py-6 z-10 mx-auto w-full max-w-6xl justify-between relative flex items-center sm:px-10">
                        <div className="gap-3 flex items-center">
                            <AnchorMark />
                            <span className="font-['IBM_Plex_Mono'] text-xs text-[#EAF3EF]/80 tracking-wide sm:text-sm">
                                KEMENTERIAN KELAUTAN &amp; PERIKANAN
                            </span>
                        </div>

                        <nav className="gap-4 text-sm flex items-center">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="px-5 py-1.5 rounded-full border border-[#EAF3EF]/30 text-[#EAF3EF] inline-block leading-normal transition hover:border-[#D9A441] hover:text-[#D9A441]"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="px-5 py-1.5 rounded-full border border-[#EAF3EF]/30 text-[#EAF3EF] inline-block leading-normal transition hover:border-[#D9A441] hover:text-[#D9A441]"
                                >
                                    Masuk Pegawai
                                </Link>
                            )}
                        </nav>
                    </header>

                    <div className="px-6 pb-28 pt-16 z-10 mx-auto max-w-4xl text-center relative sm:px-10 sm:pb-36 sm:pt-24">
                        <p className="mb-5 font-['IBM_Plex_Mono'] text-xs text-[#D9A441] uppercase tracking-[0.2em]">
                            Layanan AI KKP · Asisten Digital Kelautan &amp; Perikanan
                        </p>
                        <h1 className="font-['Fraunces'] text-4xl text-[#EAF3EF] leading-tight tracking-tight sm:text-6xl">
                            Tanya seluk-beluk laut dan
                            <br className="hidden sm:block" /> perikanan, dijawab jelas.
                        </h1>
                        <p className="mt-6 mx-auto max-w-2xl text-base text-[#EAF3EF]/75 leading-relaxed sm:text-lg">
                            Dari izin usaha perikanan, aturan tangkap, hingga informasi cuaca
                            laut — tanyakan langsung ke asisten AI resmi KKP, kapan saja,
                            tanpa perlu datang ke kantor.
                        </p>

                        <div className="flex-col mt-10 gap-4 justify-center flex items-center sm:flex-row">
                            <Link
                                href={chatIndex()}
                                className="gap-2 px-7 py-3.5 rounded-full bg-[#D9A441] text-base font-semibold text-[#0A2E3F] shadow-lg shadow-black/20 group inline-flex items-center transition hover:bg-[#e6b657] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#EAF3EF]"
                            >
                                Mulai Percakapan
                                <ArrowIcon className="transition group-hover:translate-x-0.5" />
                            </Link>

                            {!auth.user && (
                                <Link
                                    href={login()}
                                    className="gap-2 px-7 py-3.5 rounded-full text-base font-medium text-[#EAF3EF]/90 inline-flex items-center underline decoration-[#EAF3EF]/30 underline-offset-4 transition hover:text-[#D9A441] hover:decoration-[#D9A441] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D9A441]"
                                >
                                    Saya pegawai KKP, masuk di sini
                                </Link>
                            )}
                        </div>
                    </div>

                    {/* Transisi lembut ke section berikutnya */}
                    <div className="bottom-0 h-24 bg-linear-to-b pointer-events-none absolute inset-x-0 from-transparent to-[#EAF3EF] dark:to-[#08202E]" />
                </section>

                {/* ============================= LAYANAN ============================= */}
                <section className="px-6 py-20 mx-auto max-w-6xl sm:px-10 sm:py-28">
                    <div className="mb-14 mx-auto max-w-2xl text-center">
                        <h2 className="font-['Fraunces'] text-3xl text-[#0C1B22] dark:text-[#EDEDEC] sm:text-4xl">
                            Satu asisten, dua layanan
                        </h2>
                        <p className="mt-4 text-[#0C1B22]/70 dark:text-[#A1A09A]">
                            Dibangun untuk masyarakat pesisir dan nelayan, sekaligus jadi alat
                            bantu kerja sehari-hari untuk pegawai di lingkungan KKP.
                        </p>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-3">
                        <ServiceCard
                            icon={<WaveIcon />}
                            title="Informasi Publik"
                            desc="Prosedur perizinan, syarat dokumen, dan regulasi kelautan-perikanan yang berlaku, dijelaskan dalam bahasa sederhana."
                        />
                        <ServiceCard
                            icon={<CompassIcon />}
                            title="Info Cuaca & Wilayah Tangkap"
                            desc="Tanyakan kondisi perairan dan zona tangkap sesuai wilayah kerja Anda sebelum melaut."
                        />
                        <ServiceCard
                            icon={<FolderIcon />}
                            title="Akses Pegawai"
                            desc="Pegawai KKP masuk lewat akun instansi untuk akses dokumen internal, SOP, dan data kerja terbatas."
                            accentBorder
                        />
                    </div>
                </section>

                {/* ============================= FOOTER ============================= */}
                <footer className="border-t border-[#0C1B22]/10 bg-[#EAF3EF] dark:border-[#EDEDEC]/10 dark:bg-[#08202E]">
                    <div className="flex-col gap-3 px-6 py-10 mx-auto max-w-6xl text-center flex items-center sm:px-10">
                        <div className="gap-2 text-[#0C1B22]/60 flex items-center dark:text-[#A1A09A]">
                            <AnchorMark small />
                            <span className="font-['IBM_Plex_Mono'] text-xs tracking-wide">
                                KEMENTERIAN KELAUTAN DAN PERIKANAN REPUBLIK INDONESIA
                            </span>
                        </div>
                        <p className="max-w-md text-xs text-[#0C1B22]/45 dark:text-[#A1A09A]/60">
                            Layanan ini adalah asisten digital resmi untuk membantu informasi
                            layanan KKP. Untuk pengaduan/dokumen resmi, tetap mengacu pada
                            kanal resmi instansi.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}

/* --------------------------------------------------------------------- */
/* Sub-komponen                                                           */
/* --------------------------------------------------------------------- */

interface ServiceCardProps {
    icon: ReactNode;
    title: string;
    desc: string;
    accentBorder?: boolean;
}

function ServiceCard({ icon, title, desc, accentBorder = false }: ServiceCardProps) {
    return (
        <div
            className={`rounded-2xl border bg-white p-6 shadow-sm transition hover:shadow-md dark:bg-[#161615] ${
                accentBorder
                    ? 'border-[#D9A441]/40'
                    : 'border-[#0C1B22]/8 dark:border-[#3E3E3A]'
            }`}
        >
            <div className="mb-4 h-11 w-11 justify-center rounded-full bg-[#114B5F]/10 text-[#114B5F] flex items-center dark:bg-[#D9A441]/10 dark:text-[#D9A441]">
                {icon}
            </div>
            <h3 className="font-['Fraunces'] text-lg text-[#0C1B22] dark:text-[#EDEDEC]">
                {title}
            </h3>
            <p className="mt-2 text-sm text-[#0C1B22]/65 leading-relaxed dark:text-[#A1A09A]">
                {desc}
            </p>
        </div>
    );
}

/** Motif signature: garis kontur kedalaman laut ala peta nautika -- elemen
 * unik yang menegaskan subjek "kelautan", bukan dekorasi generik. */
function BathymetricLines() {
    const lines: string[] = [
        'M -100 620 Q 300 560 600 610 T 1300 590',
        'M -100 520 Q 320 460 620 505 T 1300 480',
        'M -100 420 Q 340 370 640 400 T 1300 380',
        'M -100 320 Q 360 280 660 300 T 1300 285',
        'M -100 220 Q 380 190 680 200 T 1300 190',
    ];

    return (
        <svg
            className="h-full w-full opacity-[0.35] pointer-events-none absolute inset-0 motion-safe:animate-[drift_40s_ease-in-out_infinite]"
            viewBox="0 0 1200 700"
            fill="none"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
            <style>
                {`@keyframes drift {
                    0%, 100% { transform: translate(0, 0); }
                    50% { transform: translate(-1.5%, 1%); }
                  }`}
            </style>
            {lines.map((d, i) => (
                <path
                    key={i}
                    d={d}
                    stroke="#EAF3EF"
                    strokeWidth="1"
                    strokeDasharray={i % 2 === 0 ? '0' : '2 6'}
                />
            ))}
        </svg>
    );
}

function AnchorMark({ small = false }: { small?: boolean }) {
    const size = small ? 16 : 20;

    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="5" r="2" stroke="currentColor" strokeWidth="1.6" />
            <path
                d="M12 7v13M6 12H2a10 10 0 0 0 9 9M18 12h4a10 10 0 0 1-9 9"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
        </svg>
    );
}

function WaveIcon() {
    return (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M2 8c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2M2 14c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2M2 20c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
        </svg>
    );
}

function CompassIcon() {
    return (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.6" />
            <path d="M14.5 9.5 13 13l-3.5 1.5L11 11l3.5-1.5Z" fill="currentColor" />
        </svg>
    );
}

function FolderIcon() {
    return (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M3 7a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7Z"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function ArrowIcon({ className = '' }: { className?: string }) {
    return (
        <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            className={className}
            aria-hidden="true"
        >
            <path
                d="M5 12h14M13 6l6 6-6 6"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}