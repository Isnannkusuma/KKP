import { FileSearch, HelpCircle, Scale } from 'lucide-react';

export interface ChatSuggestion {
    query: string;
    requiresLogin?: boolean;
}

interface WelcomeScreenProps {
    onSuggestionClick: (suggestion: ChatSuggestion) => void;
}

export default function WelcomeScreen({ onSuggestionClick }: WelcomeScreenProps) {
    const suggestions = [
        {
            icon: <FileSearch className="h-5 w-5 text-[#078589]" />,
            title: "Rekomendasi Bangkom Saya",
            desc: "Apa rekomendasi Bangkom yang sesuai untuk saya?",
            query: "Berikan rekomendasi Bangkom saya berdasarkan data kepegawaian saya.",
            requiresLogin: true,
        },
        {
            icon: <Scale className="h-5 w-5 text-[#078589]" />,
            title: "Login Aplikasi Sianter",
            desc: "Bagaimana cara login di Aplikasi Sianter?",
            query: "Bagaimana cara login di Aplikasi Sianter?"
        },
        {
            icon: <HelpCircle className="h-5 w-5 text-[#078589]" />,
            title: "Pengajuan Cuti",
            desc: "Bagaimana cara pengajuan libur cuti untuk pegawai?",
            query: "Bagaimana cara pengajuan libur cuti untuk pegawai?"
        }
    ];

    return (
        <div className="flex min-h-[360px] flex-col items-center justify-center px-4 text-center">
            <h2 className="mb-2 text-xl font-semibold text-[#183746]">Ada yang dapat saya bantu?</h2>
            <p className="mb-8 max-w-md text-sm text-[#52717b]">
                Saya siap membantu Anda menemukan informasi terkait layanan, regulasi, dan prosedur instansi berdasarkan dokumen resmi.
            </p>
            
            <div className="grid w-full max-w-2xl gap-3 sm:grid-cols-3">
                {suggestions.map((item, index) => (
                    <button
                        type="button"
                        key={index} 
                        className="cursor-pointer rounded-xl border border-[#d6e7e8] bg-white p-4 text-left shadow-sm shadow-[#123746]/5 transition-colors hover:border-[#9bd9d2] hover:bg-[#f4faf9]"
                        onClick={() => onSuggestionClick(item)}
                    >
                        <div className="mb-2">{item.icon}</div>
                        <h3 className="mb-1 text-sm font-semibold text-[#183746]">{item.title}</h3>
                        <p className="text-xs text-[#66838a]">{item.desc}</p>
                    </button>
                ))}
            </div>
        </div>
    );
}
