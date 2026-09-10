import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CategoryDialog from '@/components/category/category-dialog';
import CategoryTable from '@/components/category/category-table';
import DeleteCategoryDialog from '@/components/category/delete-category-dialog';
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
            <div className="flex-1 flex-col gap-6 p-4 h-full flex md:p-8">
                {flash?.success && (
                    <div className="px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm dark:bg-green-950/30 dark:border-green-800 dark:text-green-300">
                        {flash.success}
                    </div>
                )}
                {flash?.errors?.error && (
                    <div className="px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm dark:bg-red-950/30 dark:border-red-800 dark:text-red-300">
                        {flash.errors.error}
                    </div>
                )}

                <div className="flex-col gap-4 flex md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Manajemen Kategori</h1>
                        <p className="text-muted-foreground">
                            Kelola kategori dokumen untuk pengelompokan dan strategi chunking AI.
                        </p>
                    </div>
                    <button 
                        onClick={handleAddClick}
                        className="px-4 py-2 justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-10 inline-flex items-center ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 hover:bg-primary/90"
                    >
                        + Tambah Kategori
                    </button>
                </div>

                <CategoryTable 
                    categories={categories.data} 
                    pagination={categories}
                    filters={filters}
                    onEdit={handleEditClick}
                    onDelete={handleDeleteClick}
                />
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
    ],
};