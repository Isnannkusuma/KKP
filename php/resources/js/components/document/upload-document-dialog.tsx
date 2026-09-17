import { Plus, FileText, FileSpreadsheet, Upload, X, AlertCircle } from 'lucide-react';
import { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PRIMARY_BUTTON } from '@/lib/glass-theme';
import type { Category } from '@/types';

interface UploadDocumentDialogProps {
    onUpload: (formData: FormData) => void;
    categories: Category[];
}

export default function UploadDocumentDialog({ onUpload, categories }: UploadDocumentDialogProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [categoryId, setCategoryId] = useState<string>('');
    const [accessLevel, setAccessLevel] = useState<'public' | 'internal' | 'confidential'>('public');
    const [error, setError] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const validateFile = (file: File): boolean => {
        const ext = file.name.split('.').pop()?.toLowerCase();

        if (!['pdf', 'docx', 'xlsx', 'xls'].includes(ext || '')) {
            setError('Hanya file PDF, DOCX, atau XLSX yang diperbolehkan.');

            return false;
        }

        // UBAH: Max 50MB sesuai controller
        if (file.size > 50 * 1024 * 1024) {
            setError('Ukuran file maksimal 50MB.');

            return false;
        }

        setError(null);

        return true;
    };

    const handleFileSelect = (file: File) => {
        if (validateFile(file)) {
setSelectedFile(file);
}
    };

    const handleUploadClick = () => {
        if (selectedFile && categoryId) {
            const formData = new FormData();
            formData.append('document', selectedFile);
            formData.append('category_id', categoryId);
            formData.append('access_level', accessLevel);
            
            onUpload(formData);
            resetDialog();
        } else if (!categoryId) {
            setError('Silakan pilih kategori dokumen.');
        }
    };

    const resetDialog = () => {
        setIsOpen(false);
        setSelectedFile(null);
        setCategoryId('');
        setAccessLevel('public');
        setError(null);

        if (fileInputRef.current) {
fileInputRef.current.value = '';
}
    };

    const getFileIcon = (fileName: string) => {
        const ext = fileName.split('.').pop()?.toLowerCase();

        if (ext === 'xlsx' || ext === 'xls') {
return <FileSpreadsheet className="h-5 w-5 text-green-600" />;
}

        return <FileText className="h-5 w-5 text-red-600" />;
    };

    const formatFileSize = (bytes: number) => {
        if (bytes >= 1024 * 1024) {
return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

        return (bytes / 1024).toFixed(2) + ' KB';
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => {
 if (!open) {
resetDialog();
} else {
setIsOpen(true);
} 
}}>
            <DialogTrigger asChild>
                <Button className={PRIMARY_BUTTON}><Plus className="h-4 w-4 mr-2" /> Unggah Dokumen</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Unggah Dokumen Baru</DialogTitle>
                    <DialogDescription>Pilih file PDF, DOCX, atau XLSX. Dokumen akan diproses otomatis menjadi basis pengetahuan AI.</DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    {/* Kategori & Akses Level */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Kategori <span className="text-red-500">*</span></label>
                            <Select value={categoryId} onValueChange={setCategoryId}>
                                <SelectTrigger><SelectValue placeholder="Pilih kategori" /></SelectTrigger>
                                <SelectContent>
                                    {categories.map(cat => (
                                        <SelectItem key={cat.id} value={cat.id}>{cat.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Tingkat Akses <span className="text-red-500">*</span></label>
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

                    {/* Drop Zone */}
                    <div
                        className={`border-2 border-dashed rounded-lg p-6 text-center transition-colors cursor-pointer ${isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 hover:border-primary/50'}`}
                        onDragOver={(e) => {
 e.preventDefault(); setIsDragging(true); 
}}
                        onDragLeave={(e) => {
 e.preventDefault(); setIsDragging(false); 
}}
                        onDrop={(e) => {
 e.preventDefault(); setIsDragging(false);

 if (e.dataTransfer.files?.[0]) {
handleFileSelect(e.dataTransfer.files[0]);
} 
}}
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <input ref={fileInputRef} type="file" className="hidden" accept=".pdf,.docx,.xlsx,.xls" onChange={(e) => e.target.files?.[0] && handleFileSelect(e.target.files[0])} />
                        {!selectedFile ? (
                            <>
                                <Upload className="mb-2 mx-auto h-8 w-8 text-muted-foreground" />
                                <p className="text-sm font-medium">Klik atau drag & drop file di sini</p>
                                <p className="mt-1 text-xs text-muted-foreground">PDF, DOCX, XLSX (Maks. 50MB)</p>
                            </>
                        ) : (
                            <div className="gap-3 justify-center flex items-center">
                                {getFileIcon(selectedFile.name)}
                                <div className="flex-1 text-left">
                                    <p className="text-sm font-semibold truncate">{selectedFile.name}</p>
                                    <p className="text-xs text-muted-foreground">{formatFileSize(selectedFile.size)}</p>
                                </div>
                                <Button variant="ghost" size="icon" className="h-6 w-6" onClick={(e) => {
 e.stopPropagation(); setSelectedFile(null); setError(null);

 if (fileInputRef.current) {
fileInputRef.current.value = '';
} 
}}>
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                        )}
                    </div>

                    {error && (
                        <div className="gap-2 p-2 text-sm text-red-500 bg-red-50 rounded flex items-center dark:bg-red-950/30">
                            <AlertCircle className="h-4 w-4" /> {error}
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={resetDialog}>Batal</Button>
                    <Button onClick={handleUploadClick} disabled={!selectedFile || !categoryId || !!error}>
                        <Upload className="h-4 w-4 mr-2" /> Proses & Unggah
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}