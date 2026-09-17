import { router } from '@inertiajs/react';
import { FileText, FileSpreadsheet, MoreHorizontal, Trash2, Download, RefreshCw, Loader2, CheckCircle2, AlertCircle, Clock, Search, ChevronLeft, ChevronRight, Pencil } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { GLASS_CARD } from '@/lib/glass-theme';
import type { DocumentItem, PaginationData, Filters, Category } from '@/types';

interface DocumentTableProps {
    documents: DocumentItem[];
    pagination: PaginationData;
    filters: Filters;
    categories: Category[]; 
    onDeleteClick: (id: string) => void; 
    onRetryClick: (id: string) => void; 
    onDownloadClick: (id: string) => void; 
}

export default function DocumentTable({ documents, pagination, filters, categories, onDeleteClick, onRetryClick, onDownloadClick }: DocumentTableProps) {
    const [searchInput, setSearchInput] = useState(filters.search || '');
    const [categoryFilter, setCategoryFilter] = useState(filters.category_id || '');

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get('/dokumen', { ...filters, search: searchInput || undefined, category_id: categoryFilter || undefined }, { preserveState: true, preserveScroll: true });
        }, 500);

        return () => clearTimeout(timer);
    }, [searchInput, categoryFilter]);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'ready': return <Badge className="bg-green-500 hover:bg-green-600"><CheckCircle2 className="w-3 h-3 mr-1" /> Siap (RAG)</Badge>;
            case 'pending': return <Badge variant="secondary"><Clock className="w-3 h-3 mr-1" /> Menunggu</Badge>;
            case 'processing': case 'extracting': case 'cleaning': case 'embedding':
                return <Badge variant="secondary" className="bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300"><Loader2 className="w-3 h-3 mr-1 animate-spin" /> Memproses</Badge>;
            case 'failed': return <Badge variant="destructive"><AlertCircle className="w-3 h-3 mr-1" /> Gagal</Badge>;
            default: return <Badge variant="outline">{status}</Badge>;
        }
    };

    const getFileIcon = (type: string) => {
        if (type === 'xlsx' || type === 'xls') {
return <FileSpreadsheet className="h-5 w-5 text-green-600 shrink-0" />;
}

        if (type === 'docx') {
return <FileText className="h-5 w-5 text-blue-600 shrink-0" />;
}

        return <FileText className="h-5 w-5 text-red-600 shrink-0" />;
    };

    const formatDate = (dateString: string) => new Date(dateString).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    useEffect(() => {
        const hasProcessing = documents.some((d) => !['ready', 'failed'].includes(d.status));

        if (hasProcessing) {
            const interval = setInterval(() => router.reload({ only: ['documents', 'stats'] }), 5000);

            return () => clearInterval(interval);
        }
    }, [documents]);

    return (
        <Card className={`flex-1 ${GLASS_CARD}`}>
            <CardHeader>
                <div className="flex-col gap-4 flex sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="text-slate-800">Daftar Dokumen</CardTitle>
                        <CardDescription>Kelola dokumen yang menjadi knowledge base untuk AI Chatbot.</CardDescription>
                    </div>
                    <div className="gap-2 w-full flex sm:w-auto">
                        <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                            <SelectTrigger className="w-[180px]"><SelectValue placeholder="Semua Kategori" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Kategori</SelectItem>
                                {categories.map(cat => <SelectItem key={cat.id} value={cat.id}>{cat.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <div className="flex-1 relative sm:w-64">
                            <Search className="top-2.5 h-4 w-4 text-muted-foreground absolute left-2" />
                            <input type="text" placeholder="Cari dokumen..." className="py-2 w-full text-sm rounded-md border border-input bg-background pl-8 pr-3 focus:outline-none focus:ring-2 focus:ring-ring" value={searchInput} onChange={(e) => setSearchInput(e.target.value)} />
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama Dokumen</TableHead>
                            <TableHead className="w-[120px]">Kategori</TableHead>
                            <TableHead className="w-[80px]">Tipe</TableHead>
                            <TableHead className="w-[150px]">Status AI</TableHead>
                            <TableHead className="w-[80px]">Chunks</TableHead>
                            <TableHead className="w-[160px]">Tanggal</TableHead>
                            <TableHead className="w-[60px] text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {documents.length === 0 ? (
                            <TableRow><TableCell colSpan={7} className="h-32 text-center text-muted-foreground">Belum ada dokumen. Unggah dokumen pertama Anda.</TableCell></TableRow>
                        ) : (
                            documents.map((doc) => (
                                <TableRow key={doc.id}>
                                    <TableCell className="font-medium">
                                        <div className="gap-2 flex items-center">
                                            {getFileIcon(doc.file_type)}
                                            <span className="max-w-[250px] truncate">{doc.name}</span>
                                        </div>
                                        {doc.status === 'failed' && doc.error_message && (
                                            <p className="mt-1 text-xs text-red-500 max-w-[300px] truncate">{doc.error_message}</p>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="text-xs text-black">
                                            {doc.category?.name || 'Tidak ada'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-xs font-mono text-muted-foreground uppercase">{doc.file_type}</TableCell>
                                    <TableCell>{getStatusBadge(doc.status)}</TableCell>
                                    <TableCell className="text-sm">{doc.total_chunks > 0 ? <span className="font-mono">{doc.total_chunks}</span> : <span className="text-muted-foreground">-</span>}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{formatDate(doc.created_at)}</TableCell>
                                    <TableCell className="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild><Button variant="ghost" className="p-0 h-8 w-8"><MoreHorizontal className="h-4 w-4" /></Button></DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel>Aksi</DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem onClick={() => onDownloadClick(doc.id)} disabled={doc.status !== 'ready'}>
                                                    <Download className="h-4 w-4 mr-2" /> Unduh
                                                </DropdownMenuItem>
                                                {doc.status === 'failed' && (
                                                    <DropdownMenuItem onClick={() => onRetryClick(doc.id)}>
                                                        <RefreshCw className="h-4 w-4 mr-2" /> Proses Ulang
                                                    </DropdownMenuItem>
                                                )}
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem className="text-red-600 focus:text-red-600 focus:bg-red-50 dark:focus:bg-red-950/50" onSelect={(e) => {
 e.preventDefault(); onDeleteClick(doc.id); 
}}>
                                                    <Trash2 className="h-4 w-4 mr-2" /> Hapus
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {pagination.last_page > 1 && (
                    <div className="mt-4 pt-4 justify-between border-t flex items-center">
                        <p className="text-sm text-muted-foreground">Menampilkan {pagination.from} - {pagination.to} dari {pagination.total} dokumen</p>
                        <div className="gap-2 flex items-center">
                            <Button variant="outline" size="sm" disabled={pagination.current_page <= 1} onClick={() => router.get('/dokumen', { ...filters, page: pagination.current_page - 1 }, { preserveState: true, preserveScroll: true })}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <span className="text-sm">{pagination.current_page} / {pagination.last_page}</span>
                            <Button variant="outline" size="sm" disabled={pagination.current_page >= pagination.last_page} onClick={() => router.get('/dokumen', { ...filters, page: pagination.current_page + 1 }, { preserveState: true, preserveScroll: true })}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}