import { FileText, CheckCircle2, Loader2, AlertCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { DocumentStats as StatsType } from '@/types';

interface DocumentStatsProps {
    stats: StatsType;
}

export default function DocumentStats({ stats }: DocumentStatsProps) {
    const cards = [
        {
            title: 'Total Dokumen',
            value: stats.total,
            icon: <FileText className="h-5 w-5 text-muted-foreground" />,
            color: 'text-foreground',
            description: 'Seluruh dokumen terdaftar',
        },
        {
            title: 'Siap Digunakan (RAG)',
            value: stats.ready,
            icon: <CheckCircle2 className="h-5 w-5 text-green-500" />,
            color: 'text-green-600 dark:text-green-400',
            description: 'Sudah di-embedding & siap di-query',
        },
        {
            title: 'Sedang Diproses',
            value: stats.processing,
            icon: <Loader2 className="h-5 w-5 text-blue-500 animate-spin" />,
            color: 'text-blue-600 dark:text-blue-400',
            description: 'Extract, cleaning, atau embedding',
        },
        {
            title: 'Gagal Diproses',
            value: stats.failed,
            icon: <AlertCircle className="h-5 w-5 text-red-500" />,
            color: 'text-red-600 dark:text-red-400',
            description: 'Perlu di-retry atau dicek errornya',
        },
    ];

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {cards.map((card, index) => (
                <Card key={index} className="transition-all hover:shadow-md">
                    <CardHeader className="flex-row pb-2 justify-between flex items-center space-y-0">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            {card.title}
                        </CardTitle>
                        {card.icon}
                    </CardHeader>
                    <CardContent>
                        <div className={`text-3xl font-bold tracking-tight ${card.color}`}>
                            {card.value.toLocaleString('id-ID')}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {card.description}
                        </p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}