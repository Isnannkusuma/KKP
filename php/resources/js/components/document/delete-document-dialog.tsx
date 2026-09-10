import { AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogClose } from '@/components/ui/dialog';

interface DeleteDocumentDialogProps {
    isOpen: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
}

export default function DeleteDocumentDialog({ isOpen, onOpenChange, onConfirm }: DeleteDocumentDialogProps) {
    return (
        <Dialog open={isOpen} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <div className="gap-3 flex items-center">
                        <div className="h-10 w-10 rounded-full bg-red-100 justify-center dark:bg-red-900/30 flex items-center">
                            <AlertTriangle className="h-5 w-5 text-red-600" />
                        </div>
                        <div>
                            <DialogTitle>Konfirmasi Hapus Dokumen</DialogTitle>
                            <DialogDescription className="mt-1">Tindakan ini tidak dapat dibatalkan.</DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <div className="py-4">
                    <p className="text-sm text-muted-foreground">
                        Apakah Anda yakin ingin menghapus dokumen ini? File fisik akan dihapus dan 
                        semua data vektor (chunks & embeddings) akan ikut terhapus secara permanen dari database.
                    </p>
                </div>
                <DialogFooter>
                    <DialogClose asChild><Button variant="outline">Batal</Button></DialogClose>
                    <Button variant="destructive" onClick={onConfirm}>Ya, Hapus Dokumen</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}