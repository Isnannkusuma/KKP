import { Sparkles, FileSearch, HelpCircle, Scale } from 'lucide-react';
import { Card } from '@/components/ui/card';

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
        <div className="flex-col px-4 justify-center h-full min-h-[400px] text-center flex items-center">
            <div className="mb-4 h-16 w-16 rounded-2xl bg-primary/10 justify-center flex items-center">
                <Sparkles className="h-8 w-8 text-primary" />
            </div>
            <h2 className="mb-2 text-2xl font-bold tracking-tight">Selamat Datang di Asisten AI Layanan Biro SDMAO</h2>
            <p className="mb-8 text-muted-foreground max-w-md">
                Saya siap membantu Anda menemukan informasi terkait layanan, regulasi, dan prosedur instansi berdasarkan dokumen resmi.
            </p>
            
            <div className="grid gap-3 w-full max-w-2xl sm:grid-cols-3">
                {suggestions.map((item, index) => (
                    <Card 
                        key={index} 
                        className="p-4 text-left border-border/50 cursor-pointer hover:bg-accent hover:text-accent-foreground transition-colors"
                        onClick={() => onSuggestionClick(item.query)}
                    >
                        <div className="mb-2">{item.icon}</div>
                        <h3 className="mb-1 font-semibold text-sm">{item.title}</h3>
                        <p className="text-xs text-muted-foreground">{item.desc}</p>
                    </Card>
                ))}
            </div>
        </div>
    );
}
