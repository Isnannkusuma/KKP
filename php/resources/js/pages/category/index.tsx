import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CategoryDialog from '@/components/category/category-dialog';
import CategoryTable from '@/components/category/category-table';
import DeleteCategoryDialog from '@/components/category/delete-category-dialog';
import { GLASS_PAGE_BG, PRIMARY_BUTTON } from '@/lib/glass-theme';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CategoryItem, PaginationDataCategory, FiltersCategory } from '@/types';

interface Props {
    categories: PaginationDataCategory;
    filters: FiltersCategory;
}

export default function Category({ categories, filters }: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<CategoryItem | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<CategoryItem | null>(null);

    const { flash } = usePage().props as any;

    const handleAddClick = () => {
        setEditingCategory(null);
        setIsDialogOpen(true);
    };

    const handleEditClick = (category: CategoryItem) => {
        setEditingCategory(category);
        setIsDialogOpen(true);
    };

    const handleDeleteClick = (category: CategoryItem) => {
        setDeleteTarget(category);
        setIsDeleteOpen(true);
    };

    const confirmDelete = () => {
        if (deleteTarget) {
            router.delete(`/kategori/${deleteTarget.id}`, {
                onSuccess: () => {
                    setIsDeleteOpen(false);
                    setDeleteTarget(null);
                },
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Manajemen Kategori" />

            {/* text-slate-900 di wrapper = warna teks dasar untuk semua anak yang tidak punya warna sendiri */}
            <div className={cn('flex h-full flex-1 flex-col gap-6 p-4 text-slate-900 md:p-8 [color-scheme:light]', GLASS_PAGE_BG)}>
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
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900">Manajemen Kategori</h1>
                        <p className="text-slate-600">
                            Kelola kategori dokumen untuk pengelompokan dan strategi chunking AI.
                        </p>
                    </div>
                    <button
                        onClick={handleAddClick}
                        className={cn('inline-flex h-10 items-center justify-center rounded-full px-4 py-2 text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50', PRIMARY_BUTTON)}
                    >
                        + Tambah Kategori
                    </button>
                </div>

                <div className="text-slate-900 [&_table]:text-slate-700 [&_th]:text-slate-700 [&_td]:text-slate-700 [&_input]:text-slate-900 [&_input::placeholder]:text-slate-400">
                    <CategoryTable
                        categories={categories.data}
                        pagination={categories}
                        filters={filters}
                        onEdit={handleEditClick}
                        onDelete={handleDeleteClick}
                    />
                </div>
            </div>

            <CategoryDialog
                isOpen={isDialogOpen}
                onOpenChange={setIsDialogOpen}
                category={editingCategory}
            />

            <DeleteCategoryDialog
                isOpen={isDeleteOpen}
                onOpenChange={setIsDeleteOpen}
                category={deleteTarget}
                onConfirm={confirmDelete}
            />
        </>
    );
}

Category.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Manajemen Kategori',
        },
    ],
};