import { router } from '@inertiajs/react';
import { FileText, FileSpreadsheet, Upload, X, Pencil } from 'lucide-react';
import { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { DocumentItem, Category } from '@/types';

interface EditDocumentDialogProps {
    document: DocumentItem | null;
    isOpen: boolean;
    onOpenChange: (open: boolean) => void;
    categories: Category[];
}

export default function EditDocumentDialog({ document, isOpen, onOpenChange, categories }: EditDocumentDialogProps) {
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [categoryId, setCategoryId] = useState<string>('');
    const [accessLevel, setAccessLevel] = useState<'public' | 'internal' | 'confidential'>('public');
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Reset form saat dialog dibuka dengan dokumen baru
    useState(() => {
        if (document) {
            setCategoryId(document.category?.id || '');
            setAccessLevel(document.access_level);
            setSelectedFile(null);
        }
    });

    const handleUpdate = () => {
        if (!document) {
return;
}
        
        const formData = new FormData();
        formData.append('_method', 'PUT'); // Laravel method spoofing
        formData.append('category_id', categoryId);
        formData.append('access_level', accessLevel);

        if (selectedFile) {
            formData.append('document', selectedFile);
        }

        router.post(`/dokumen/${document.id}`, formData, {
            onSuccess: () => onOpenChange(false),
            preserveScroll: true,
        });
    };

    if (!document) {
return null;
}

    const getFileIcon = (fileName: string) => {
        const ext = fileName.split('.').pop()?.toLowerCase();

        if (ext === 'xlsx' || ext === 'xls') {
return <FileSpreadsheet className="h-5 w-5 text-green-600" />;
}

        return <FileText className="h-5 w-5 text-red-600" />;
    };

    return (
        <Dialog open={isOpen} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Edit Dokumen</DialogTitle>
                    <DialogDescription>Ubah kategori, tingkat akses, atau ganti file dokumen.</DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Kategori</label>
                            <Select value={categoryId} onValueChange={setCategoryId}>
                                <SelectTrigger><SelectValue placeholder="Pilih kategori" /></SelectTrigger>
                                <SelectContent>
                                    {categories.map(cat => <SelectItem key={cat.id} value={cat.id}>{cat.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Tingkat Akses</label>
                            <Select value={accessLevel} onValueChange={(v: any) => setAccessLevel(v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="public">Publik</SelectItem>
                                    <SelectItem value="internal">Internal</SelectItem>
                                    <SelectItem value="confidential">Rahasia</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium">Ganti File (Opsional)</label>
                        <div className="p-4 border-2 border-dashed rounded-lg text-center cursor-pointer hover:border-primary/50 transition-colors" onClick={() => fileInputRef.current?.click()}>
                            <input ref={fileInputRef} type="file" className="hidden" accept=".pdf,.docx,.xlsx,.xls" onChange={(e) => e.target.files?.[0] && setSelectedFile(e.target.files[0])} />
                            {!selectedFile ? (
                                <div className="text-muted-foreground text-sm">
                                    <Upload className="mb-1 mx-auto h-6 w-6" />
                                    Klik untuk mengganti file (Maks. 50MB)
                                </div>
                            ) : (
                                <div className="gap-3 justify-center flex items-center">
                                    {getFileIcon(selectedFile.name)}
                                    <div className="flex-1 text-left">
                                        <p className="text-sm font-semibold truncate">{selectedFile.name}</p>
                                    </div>
                                    <Button variant="ghost" size="icon" className="h-6 w-6" onClick={(e) => {
 e.stopPropagation(); setSelectedFile(null);

 if (fileInputRef.current) {
fileInputRef.current.value = '';
} 
}}>
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Batal</Button>
                    <Button onClick={handleUpdate}><Pencil className="h-4 w-4 mr-2" /> Simpan Perubahan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}