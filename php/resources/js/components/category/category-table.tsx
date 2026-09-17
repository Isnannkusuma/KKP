import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, FileText, Pencil, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { GLASS_CARD } from '@/lib/glass-theme';
import type { CategoryItem, FiltersCategory, PaginationDataCategory } from '@/types';

interface Props {
    categories: CategoryItem[];
    pagination: PaginationDataCategory;
    filters: FiltersCategory;
    onEdit: (category: CategoryItem) => void;
    onDelete: (category: CategoryItem) => void;
}

export default function CategoryTable({ categories, pagination, filters, onEdit, onDelete }: Props) {
    const [searchInput, setSearchInput] = useState(filters.search || '');

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get('/kategori', { search: searchInput || undefined }, { preserveState: true, preserveScroll: true });
        }, 500);

        return () => clearTimeout(timer);
    }, [searchInput]);

    return (
        <Card className={`flex-1 ${GLASS_CARD}`}>
            <CardHeader>
                <div className="flex-col gap-4 flex sm:flex-row sm:items-center sm:justify-between">
                    <CardTitle className="text-slate-800">Daftar Kategori</CardTitle>
                    <div className="w-full relative sm:w-64">
                        <Search className="top-2.5 h-4 w-4 text-muted-foreground absolute left-2" />
                        <input
                            type="text"
                            placeholder="Cari kategori..."
                            className="py-2 w-full text-sm rounded-md border border-input bg-background pl-8 pr-3 focus:outline-none focus:ring-2 focus:ring-ring"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                        />
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama Kategori</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Deskripsi</TableHead>
                            <TableHead className="text-center">Jumlah Dokumen</TableHead>
                            <TableHead className="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {categories.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="h-32 text-center text-muted-foreground">
                                    Tidak ada kategori ditemukan.
                                </TableCell>
                            </TableRow>
                        ) : (
                            categories.map((cat) => (
                                <TableRow key={cat.id}>
                                    <TableCell className="font-medium">{cat.name}</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary" className="font-mono text-xs">
                                            {cat.slug}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground max-w-xs truncate">
                                        {cat.description || '-'}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <div className="gap-1 justify-center text-sm flex items-center">
                                            <FileText className="h-4 w-4 text-muted-foreground" />
                                            {cat.documents_count}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="gap-2 justify-end flex">
                                            <Button variant="ghost" size="icon" onClick={() => onEdit(cat)}>
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" className="text-red-600 hover:text-red-700 hover:bg-red-50" onClick={() => onDelete(cat)}>
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {pagination.last_page > 1 && (
                    <div className="mt-4 pt-4 justify-between border-t flex items-center">
                        <p className="text-sm text-muted-foreground">
                            {pagination.from} - {pagination.to} dari {pagination.total}
                        </p>
                        <div className="gap-2 flex items-center">
                            <Button variant="outline" size="sm" disabled={pagination.current_page <= 1} onClick={() => router.get('/kategori', { ...filters, page: pagination.current_page - 1 }, { preserveState: true })}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <span className="text-sm">{pagination.current_page} / {pagination.last_page}</span>
                            <Button variant="outline" size="sm" disabled={pagination.current_page >= pagination.last_page} onClick={() => router.get('/kategori', { ...filters, page: pagination.current_page + 1 }, { preserveState: true })}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}