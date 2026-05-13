import { useEffect, useState } from 'react';
import { SidebarNav } from '@/components/admin/SidebarNav';
import { Topbar } from '@/components/admin/Topbar';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const SIDEBAR_STORAGE_KEY = 'sellova-admin-sidebar-collapsed';

/**
 * @param {{ children: import('react').ReactNode, className?: string }} props
 */
export function AdminLayout({ children, className }) {
    const [mobileNav, setMobileNav] = useState(false);
    const [collapsed, setCollapsed] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        setCollapsed(window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1');
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
    }, [collapsed]);

    return (
        <TooltipProvider delayDuration={180}>
            <div className={cn('admin-shell', className)}>
                <Sheet open={mobileNav} onOpenChange={setMobileNav}>
                    <SheetContent side="left" className="w-[min(100%,290px)] bg-sidebar p-0">
                        <div className="flex h-full flex-col bg-sidebar px-3 py-2.5 text-sidebar-foreground">
                            <SidebarNav mobile onNavigate={() => setMobileNav(false)} />
                        </div>
                    </SheetContent>
                </Sheet>

                <div className="flex min-h-screen">
                    <aside
                        className={cn(
                            'sticky top-0 hidden h-screen shrink-0 border-r border-[hsl(var(--sidebar-border))] bg-sidebar px-3 py-2.5 text-sidebar-foreground transition-[width] duration-300 lg:flex lg:flex-col',
                            collapsed ? 'w-[92px]' : 'w-[272px]',
                        )}
                    >
                        <SidebarNav collapsed={collapsed} onToggleCollapse={() => setCollapsed((value) => !value)} />
                    </aside>

                    <div className="min-w-0 flex-1">
                        <div className="flex min-h-screen flex-col">
                            <Topbar collapsed={collapsed} onOpenSidebar={() => setMobileNav(true)} />
                            <main className="flex-1 px-4 pb-6 pt-3.5 sm:px-5 lg:px-6 lg:pb-8 lg:pt-4">
                                <div className="mx-auto w-full max-w-[1600px]">{children}</div>
                            </main>
                        </div>
                    </div>
                </div>
            </div>
        </TooltipProvider>
    );
}
