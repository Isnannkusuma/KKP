import { Head, router } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft, ChevronRight, CircleHelp, Save, Sparkles, ThumbsDown, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { GLASS_BAR, GLASS_CARD, GLASS_PAGE_BG, GLASS_PILL, PRIMARY_BUTTON } from '@/lib/glass-theme';
import { cn } from '@/lib/utils';

type FaqStatus = 'draft' | 'approved' | 'rejected';

interface FaqEntryItem {
    id: string;
    question: string;
    answer: string;
    sourceCount: number;
    status: FaqStatus;
    createdAt: string | null;
    approvedAt: string | null;
    approvedBy: string | null;
}

interface PaginationData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface FaqReviewProps {
    status: FaqStatus;
    entries: PaginationData<FaqEntryItem>;
    counts: {
        draft: number;
        approved: number;
        rejected: number;
    };
}

const formatDate = (date: string | null): string => {
    if (!date) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
};

const statusTabs: { key: FaqStatus; label: string }[] = [
    { key: 'draft', label: 'Menunggu Review' },
    { key: 'approved', label: 'Disetujui' },
    { key: 'rejected', label: 'Ditolak' },
];

export default function FaqReview({ status, entries, counts }: FaqReviewProps) {
    const changeStatus = (nextStatus: FaqStatus) => router.get('/faq-review', { status: nextStatus }, { preserveScroll: true });
    const changePage = (page: number) => router.get('/faq-review', { status, page }, { preserveScroll: true });

    return (
        <>
            <Head title="Review FAQ Otomatis" />
            <div className={cn('flex h-full flex-1 flex-col gap-6 p-4 md:p-8', GLASS_PAGE_BG)}>
                <div className={cn('flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between', GLASS_BAR)}>
                    <div>
                        <div className="flex items-center gap-2">
                            <Sparkles className="size-4 text-blue-500" />
                            <p className="text-xs font-medium text-blue-600">Dihasilkan otomatis dari clustering pertanyaan populer</p>
                        </div>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900">Review FAQ Otomatis</h1>
                        <p className="text-sm text-slate-500">Tinjau, koreksi, dan setujui FAQ sebelum dipakai chatbot untuk menjawab pengguna.</p>
                    </div>
                    <div className="flex gap-2">
                        {statusTabs.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => changeStatus(tab.key)}
                                className={cn(
                                    'flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-colors',
                                    status === tab.key ? 'bg-blue-600 text-white' : cn(GLASS_PILL, 'text-slate-600 hover:bg-blue-50'),
                                )}
                            >
                                {tab.label}
                                <Badge variant="secondary" className={cn('border-0', status === tab.key ? 'bg-white/20 text-white' : 'bg-blue-50 text-blue-700')}>
                                    {counts[tab.key]}
                                </Badge>
                            </button>
                        ))}
                    </div>
                </div>

                <div className="space-y-4">
                    {entries.data.length > 0 ? (
                        entries.data.map((entry) => <FaqCard key={entry.id} entry={entry} />)
                    ) : (
                        <Card className={GLASS_CARD}>
                            <CardContent className="pt-6">
                                <EmptyState
                                    message={
                                        status === 'draft'
                                            ? 'Belum ada draft FAQ baru. Jalankan clustering mingguan untuk menemukan topik populer.'
                                            : `Belum ada FAQ dengan status ${status === 'approved' ? 'disetujui' : 'ditolak'}.`
                                    }
                                />
                            </CardContent>
                        </Card>
                    )}
                </div>

                {entries.last_page > 1 && (
                    <div className="flex items-center justify-between text-xs text-slate-500">
                        <span>
                            Halaman {entries.current_page} dari {entries.last_page} &middot; {entries.total} total
                        </span>
                        <div className="flex gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8 border-blue-100 text-blue-600 hover:bg-blue-50"
                                disabled={entries.current_page === 1}
                                onClick={() => changePage(entries.current_page - 1)}
                            >
                                <ChevronLeft />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8 border-blue-100 text-blue-600 hover:bg-blue-50"
                                disabled={entries.current_page === entries.last_page}
                                onClick={() => changePage(entries.current_page + 1)}
                            >
                                <ChevronRight />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function FaqCard({ entry }: { entry: FaqEntryItem }) {
    const [question, setQuestion] = useState(entry.question);
    const [answer, setAnswer] = useState(entry.answer);
    const [isSaving, setIsSaving] = useState(false);
    const hasChanges = question !== entry.question || answer !== entry.answer;

    const save = () => {
        setIsSaving(true);
        router.put(
            `/faq-review/${entry.id}`,
            { question, answer },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            },
        );
    };

    const approve = () => {
        if (hasChanges && !window.confirm('Ada perubahan yang belum disimpan. Simpan dulu sebelum menyetujui?')) {
            return;
        }

        router.post(`/faq-review/${entry.id}/approve`, {}, { preserveScroll: true });
    };

    const reject = () => {
        if (!window.confirm('Tolak FAQ ini? FAQ tidak akan dipakai chatbot.')) {
            return;
        }

        router.post(`/faq-review/${entry.id}/reject`, {}, { preserveScroll: true });
    };

    const destroy = () => {
        if (!window.confirm('Hapus FAQ ini secara permanen? Tindakan ini tidak dapat dibatalkan.')) {
            return;
        }

        router.delete(`/faq-review/${entry.id}`, { preserveScroll: true });
    };

    return (
        <Card className={GLASS_CARD}>
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="text-slate-800">
                        <Badge variant="secondary" className="mr-2 border-blue-100 bg-blue-50 text-blue-700">
                            {entry.sourceCount}x ditanyakan
                        </Badge>
                        Draft FAQ
                    </CardTitle>
                    <span className="text-xs text-slate-400">Dibuat: {formatDate(entry.createdAt)}</span>
                </div>
                {entry.status === 'approved' && entry.approvedAt && (
                    <CardDescription className="text-emerald-600">
                        Disetujui oleh {entry.approvedBy ?? 'Admin'} pada {formatDate(entry.approvedAt)}
                    </CardDescription>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-slate-500">Pertanyaan FAQ</label>
                    <textarea
                        value={question}
                        onChange={(event) => setQuestion(event.target.value)}
                        rows={2}
                        className="w-full rounded-md border border-blue-100 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-blue-200"
                    />
                </div>
                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-slate-500">Jawaban</label>
                    <textarea
                        value={answer}
                        onChange={(event) => setAnswer(event.target.value)}
                        rows={4}
                        className="w-full rounded-md border border-blue-100 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-blue-200"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2 border-t border-blue-50 pt-3">
                    {hasChanges && (
                        <Button size="sm" variant="outline" className="border-blue-200 text-blue-600 hover:bg-blue-50" onClick={save} disabled={isSaving}>
                            <Save className="size-4" /> {isSaving ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    )}

                    {entry.status !== 'approved' && (
                        <Button size="sm" className={PRIMARY_BUTTON} onClick={approve}>
                            <CheckCircle2 className="size-4" /> Setujui
                        </Button>
                    )}

                    {entry.status !== 'rejected' && (
                        <Button size="sm" variant="outline" className="border-amber-200 text-amber-600 hover:bg-amber-50" onClick={reject}>
                            <ThumbsDown className="size-4" /> Tolak
                        </Button>
                    )}

                    <Button size="sm" variant="outline" className="ml-auto border-red-200 text-red-600 hover:bg-red-50" onClick={destroy}>
                        <Trash2 className="size-4" /> Hapus
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex min-h-40 flex-col items-center justify-center gap-2 text-center text-sm text-slate-400">
            <CircleHelp className="size-5" />
            {message}
        </div>
    );
}

FaqReview.layout = {
    breadcrumbs: [
        {
            title: 'Review FAQ',
            href: { url: '/faq-review', method: 'get' as const },
        },
    ],
};