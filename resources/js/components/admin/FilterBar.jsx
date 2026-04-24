import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * @param {{ className?: string, children?: import('react').ReactNode }} props
 */
export function FilterBar({ className, children }) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-lg border border-border/80 bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="relative flex-1 sm:max-w-xs">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input placeholder="Search…" className="pl-9" disabled aria-disabled />
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" disabled>
                    Filters
                </Button>
                {children}
            </div>
        </div>
    );
}
