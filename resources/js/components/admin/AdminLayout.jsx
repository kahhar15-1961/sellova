import { useState } from 'react';
import { SidebarNav } from '@/components/admin/SidebarNav';
import { Topbar } from '@/components/admin/Topbar';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * @param {{ children: import('react').ReactNode, className?: string }} props
 */
export function AdminLayout({ children, className }) {
    const [mobileNav, setMobileNav] = useState(false);

    return (
        <TooltipProvider delayDuration={200}>
        <div className={cn('min-h-screen bg-slate-50', className)}>
            <Sheet open={mobileNav} onOpenChange={setMobileNav}>
                <SheetContent side="left" className="w-[min(100%,280px)] p-0">
                    <SheetHeader className="border-b border-border px-4 py-4 text-left">
                        <SheetTitle className="text-base">Navigation</SheetTitle>
                    </SheetHeader>
                    <div className="overflow-y-auto px-2 py-4">
                        <SidebarNav onNavigate={() => setMobileNav(false)} />
                    </div>
                </SheetContent>
            </Sheet>

            <div className="flex min-h-screen">
                <aside className="sticky top-0 hidden h-screen w-64 shrink-0 border-r border-border/80 bg-card shadow-sm lg:flex lg:flex-col">
                    <div className="flex h-14 items-center border-b border-border/80 px-4">
                        <span className="text-sm font-semibold tracking-tight">Sellova Admin</span>
                    </div>
                    <div className="flex-1 overflow-y-auto px-2 py-4">
                        <SidebarNav />
                    </div>
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <Topbar onOpenSidebar={() => setMobileNav(true)} />
                    <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">{children}</main>
                </div>
            </div>
        </div>
        </TooltipProvider>
    );
}
