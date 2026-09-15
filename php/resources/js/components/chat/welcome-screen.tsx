import { FileSearch, HelpCircle, Scale } from 'lucide-react';

interface WelcomeScreenProps {
    onSuggestionClick: (message: string) => void;
}

export default function WelcomeScreen({ onSuggestionClick }: WelcomeScreenProps) {
    const suggestions = [
        {
            icon: <FileSearch className="h-5 w-5 text-blue-500" />,
            title: "Cara Pengajuan",
            desc: "Bagaimana syarat dan prosedur pengajuan layanan baru?",
            query: "Bagaimana syarat dan prosedur pengajuan layanan baru?"
        },
        {
            icon: <Scale className="h-5 w-5 text-purple-500" />,
            title: "Dasar Hukum",
            desc: "Apa dasar hukum dari kebijakan terbaru ini?",
            query: "Apa dasar hukum dari kebijakan terbaru ini?"
        },
        {
            icon: <HelpCircle className="h-5 w-5 text-orange-500" />,
            title: "Troubleshooting",
            desc: "Mengapa pengajuan saya ditolak?",
            query: "Apa saja alasan umum pengajuan layanan ditolak?"
        }
    ];

    return (
        <div className="flex min-h-[360px] flex-col items-center justify-center px-4 text-center">
            <h2 className="mb-2 text-xl font-semibold text-white">Ada yang dapat saya bantu?</h2>
            <p className="mb-8 max-w-md text-sm text-white/70">
                Saya siap membantu Anda menemukan informasi terkait layanan, regulasi, dan prosedur instansi berdasarkan dokumen resmi.
            </p>
            
            <div className="grid w-full max-w-2xl gap-3 sm:grid-cols-3">
                {suggestions.map((item, index) => (
                    <button
                        type="button"
                        key={index} 
                        className="cursor-pointer rounded-xl border border-white/15 bg-white/10 p-4 text-left transition-colors hover:bg-white/20"
                        onClick={() => onSuggestionClick(item.query)}
                    >
                        <div className="mb-2">{item.icon}</div>
                        <h3 className="mb-1 text-sm font-semibold text-white">{item.title}</h3>
                        <p className="text-xs text-white/65">{item.desc}</p>
                    </button>
                ))}
            </div>
        </div>
    );
}
