import { router, usePage } from '@inertiajs/react';
import { LogOut, Menu, PanelLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Separator } from '@/components/ui/separator';

/**
 * @param {{ onOpenSidebar?: () => void }} props
 */
export function Topbar({ onOpenSidebar }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const initials = user?.email?.slice(0, 2).toUpperCase() ?? '—';

    return (
        <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-border/80 bg-card/90 px-4 backdrop-blur-md lg:px-6">
                <div className="flex items-center gap-2 lg:hidden">
                    <Button type="button" variant="ghost" size="icon" className="shrink-0" onClick={onOpenSidebar} aria-label="Open menu">
                        <Menu className="h-5 w-5" />
                    </Button>
                </div>
                <div className="hidden items-center gap-2 text-muted-foreground lg:flex">
                    <PanelLeft className="h-4 w-4 opacity-60" />
                    <span className="text-xs font-medium uppercase tracking-wide">Console</span>
                </div>
                <Separator orientation="vertical" className="hidden h-6 lg:block" />
                <div className="flex flex-1 items-center justify-end gap-3">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="hidden text-xs text-muted-foreground sm:inline">Enterprise admin</span>
                        </TooltipTrigger>
                        <TooltipContent>Session-based staff access · separate from mobile API tokens</TooltipContent>
                    </Tooltip>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="relative h-9 gap-2 rounded-full px-2">
                                <Avatar className="h-8 w-8">
                                    <AvatarFallback>{initials}</AvatarFallback>
                                </Avatar>
                                <span className="hidden max-w-[140px] truncate text-sm font-medium sm:inline">{user?.email}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>Account</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.post('/admin/logout', undefined, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <LogOut className="mr-2 h-4 w-4" />
                                Sign out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>
    );
}
