export interface CategoryItem {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    documents_count: number;
    created_at: string;
}

export interface PaginationDataCategory {
    data: CategoryItem[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
}

export interface FiltersCategory {
    search?: string;
}