import { AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogClose } from '@/components/ui/dialog';
import type { CategoryItem } from '@/types';

interface Props {
    isOpen: boolean;
    onOpenChange: (open: boolean) => void;
    category: CategoryItem | null;
    onConfirm: () => void;
}

export default function DeleteCategoryDialog({ isOpen, onOpenChange, category, onConfirm }: Props) {
    if (!category) {
return null;
}

    return (
        <Dialog open={isOpen} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <div className="gap-3 flex items-center">
                        <div className="h-10 w-10 rounded-full bg-red-100 justify-center dark:bg-red-900/30 flex items-center">
                            <AlertTriangle className="h-5 w-5 text-red-600" />
                        </div>
                        <div>
                            <DialogTitle>Hapus Kategori?</DialogTitle>
                            <DialogDescription className="mt-1">
                                Tindakan ini tidak dapat dibatalkan.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="py-4">
                    <p className="mb-3 text-sm text-muted-foreground">
                        Apakah Anda yakin ingin menghapus kategori <strong>"{category.name}"</strong>?
                    </p>
                    {category.documents_count > 0 ? (
                        <div className="p-3 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-md text-xs dark:bg-yellow-950/30 dark:border-yellow-800 dark:text-yellow-300">
                            ⚠️ <strong>Peringatan:</strong> Kategori ini masih digunakan oleh {category.documents_count} dokumen. 
                            Jika dihapus, kolom kategori pada dokumen-dokumen tersebut akan menjadi kosong (null).
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Kategori ini tidak sedang digunakan oleh dokumen apapun.
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Batal</Button>
                    </DialogClose>
                    <Button variant="destructive" onClick={onConfirm}>
                        Ya, Hapus Kategori
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}