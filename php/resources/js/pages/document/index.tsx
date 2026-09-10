import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DeleteDocumentDialog from '@/components/document/delete-document-dialog';
import DocumentStats from '@/components/document/document-stats';
import DocumentTable from '@/components/document/document-table';
import UploadDocumentDialog from '@/components/document/upload-document-dialog';
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

            <div className="flex-1 flex-col gap-6 p-4 h-full flex md:p-8">
                {flash?.success && (
                    <div className="px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm dark:bg-green-950/30 dark:border-green-800 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex-col gap-4 flex md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Manajemen Dokumen AI</h1>
                        <p className="text-muted-foreground">
                            Kelola dokumen yang akan digunakan sebagai basis pengetahuan chatbot.
                        </p>
                    </div>
                    {/* KIRIM categories ke Dialog */}
                    <UploadDocumentDialog onUpload={handleUpload} categories={categories} />
                </div>

                <DocumentStats stats={stats} />

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
    ],
};