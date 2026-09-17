import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DeleteDocumentDialog from '@/components/document/delete-document-dialog';
import DocumentStats from '@/components/document/document-stats';
import DocumentTable from '@/components/document/document-table';
import UploadDocumentDialog from '@/components/document/upload-document-dialog';
import { GLASS_PAGE_BG } from '@/lib/glass-theme';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { DocumentStats as StatsType, PaginationData, Filters, Category } from '@/types';

interface Props {
    documents: PaginationData;
    stats: StatsType;
    filters: Filters;
    categories: Category[];
}

export default function Dokumen({ documents, stats, filters, categories }: Props) {
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [deleteTargetId, setDeleteTargetId] = useState<string | null>(null);
    const { flash } = usePage().props as any;

    const handleUpload = (formData: FormData) => {
        router.post('/dokumen', formData, {
            preserveScroll: true,
        });
    };

    const handleDeleteClick = (id: string) => {
        setDeleteTargetId(id);
        setIsDeleteOpen(true);
    };

    const confirmDelete = () => {
        if (deleteTargetId) {
            router.delete(`/dokumen/${deleteTargetId}`, {
                onSuccess: () => {
                    setIsDeleteOpen(false);
                    setDeleteTargetId(null);
                },
                preserveScroll: true,
            });
        }
    };

    const handleRetry = (id: string) => {
        router.post(`/dokumen/${id}/retry`, {}, {
            preserveScroll: true,
        });
    };

    const handleDownload = (id: string) => {
        window.open(`/dokumen/${id}/download`, '_blank');
    };

    return (
        <>
            <Head title="Manajemen Dokumen" />

            {/* text-slate-900 di wrapper = warna teks dasar untuk semua anak yang tidak punya warna sendiri */}
            <div className={cn('flex h-full flex-1 flex-col gap-6 p-4 text-black md:p-8 [color-scheme:light]', GLASS_PAGE_BG)}>
                {flash?.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.errors?.error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {flash.errors.error}
                    </div>
                )}

                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900">Manajemen Dokumen AI</h1>
                        <p className="text-slate-600">
                            Kelola dokumen yang akan digunakan sebagai basis pengetahuan chatbot.
                        </p>
                    </div>
                    <UploadDocumentDialog onUpload={handleUpload} categories={categories} />
                </div>

                <div className="text-slate-900 [&_table]:text-slate-700 [&_th]:text-slate-700 [&_td]:text-slate-700">
                    <DocumentStats stats={stats} />
                </div>

                <div className="text-slate-900 [&_table]:text-slate-700 [&_th]:text-slate-700 [&_td]:text-slate-700 [&_input]:text-slate-900 [&_input::placeholder]:text-slate-400">
                    <DocumentTable
                        documents={documents.data}
                        pagination={documents}
                        filters={filters}
                        categories={categories}
                        onDeleteClick={handleDeleteClick}
                        onRetryClick={handleRetry}
                        onDownloadClick={handleDownload}
                    />
                </div>
            </div>

            <DeleteDocumentDialog
                isOpen={isDeleteOpen}
                onOpenChange={setIsDeleteOpen}
                onConfirm={confirmDelete}
            />
        </>
    );
}

Dokumen.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Manajemen Dokumen',
        },
    ],
};