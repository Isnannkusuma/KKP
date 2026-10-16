import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { MobileNavbar } from '@/components/mobile-navbar';
import { useIsMobile } from '@/hooks/use-mobile';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const isMobile = useIsMobile();

    return (
        <AppShell variant="sidebar">
            {!isMobile && <AppSidebar />}
            <AppContent
                variant="sidebar"
                className={`overflow-x-hidden ${isMobile ? 'pb-24' : ''}`}
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            {isMobile && <MobileNavbar />}
        </AppShell>
    );
}
