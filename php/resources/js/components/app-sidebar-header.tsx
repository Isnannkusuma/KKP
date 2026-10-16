import { Breadcrumbs } from '@/components/breadcrumbs';
import { MobileUserMenu } from '@/components/mobile-user-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const isMobile = useIsMobile();

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                {!isMobile && <SidebarTrigger className="-ml-1" />}
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {isMobile && <MobileUserMenu />}
        </header>
    );
}
