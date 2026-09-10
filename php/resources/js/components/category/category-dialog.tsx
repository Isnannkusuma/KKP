import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { CategoryItem } from '@/types';

interface Props {
    isOpen: boolean;
    onOpenChange: (open: boolean) => void;
    category: CategoryItem | null;
}

interface FormErrors {
    name?: string;
    slug?: string;
    description?: string;
}

export default function CategoryDialog({
    isOpen,
    onOpenChange,
    category,
}: Props) {
    const [name, setName] = useState(category?.name ?? '');
    const [slug, setSlug] = useState(category?.slug ?? '');
    const [description, setDescription] = useState(
        category?.description ?? '',
    );
    const [errors, setErrors] = useState<FormErrors>({});

    const handleNameChange = (value: string) => {
        setName(value);

        if (!category) {
            const generatedSlug = value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)+/g, '');

            setSlug(generatedSlug);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const url = category ? `/kategori/${category.id}` : '/kategori';

        const formData = new FormData();

        formData.append('name', name);
        formData.append('slug', slug);
        formData.append('description', description);

        if (category) {
            formData.append('_method', 'PUT');
        }

        router.post(url, formData, {
            onSuccess: () => {
                onOpenChange(false);
                setErrors({});
            },
            onError: (errs) => {
                setErrors(errs);
            },
            preserveScroll: true,
        });
    };

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            setErrors({});
        }

        onOpenChange(open);
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-125">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {category
                                ? 'Edit Kategori'
                                : 'Tambah Kategori Baru'}
                        </DialogTitle>

                        <DialogDescription>
                            {category
                                ? 'Perbarui informasi kategori dokumen.'
                                : 'Tambahkan kategori baru untuk mengelompokkan dokumen.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Nama Kategori{' '}
                                <span className="text-red-500">*</span>
                            </label>

                            <Input
                                value={name}
                                onChange={(e) =>
                                    handleNameChange(e.target.value)
                                }
                                placeholder="Contoh: Surat Keputusan"
                            />

                            {errors.name && (
                                <p className="text-xs text-red-500">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Slug <span className="text-red-500">*</span>
                            </label>

                            <Input
                                value={slug}
                                onChange={(e) => setSlug(e.target.value)}
                                placeholder="contoh: surat-keputusan"
                                className="font-mono text-sm"
                            />

                            <p className="text-xs text-muted-foreground">
                                Digunakan oleh sistem AI untuk strategi
                                chunking.
                            </p>

                            {errors.slug && (
                                <p className="text-xs text-red-500">
                                    {errors.slug}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Deskripsi (Opsional)
                            </label>

                            <Textarea
                                value={description}
                                onChange={(e) =>
                                    setDescription(e.target.value)
                                }
                                placeholder="Keterangan singkat tentang kategori ini..."
                                rows={3}
                            />

                            {errors.description && (
                                <p className="text-xs text-red-500">
                                    {errors.description}
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>

                        <Button type="submit">
                            {category
                                ? 'Simpan Perubahan'
                                : 'Tambah Kategori'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}