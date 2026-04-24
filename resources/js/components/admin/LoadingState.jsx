import { cn } from '@/lib/utils';

/**
 * @param {{ rows?: number, className?: string }} props
 */
export function LoadingState({ rows = 6, className }) {
    return (
        <div className={cn('space-y-3', className)} aria-busy="true" aria-label="Loading">
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="h-10 animate-pulse rounded-md bg-muted/70" />
            ))}
        </div>
    );
}
