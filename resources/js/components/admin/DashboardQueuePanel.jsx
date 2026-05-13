import { Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * @param {{
 *   title: string,
 *   description?: string,
 *   href?: string,
 *   linkLabel?: string,
 *   locked?: boolean,
 *   lockedMessage?: string,
 *   children: import('react').ReactNode,
 *   className?: string,
 * }} props
 */
export function DashboardQueuePanel({
    title,
    description,
    href,
    linkLabel = 'View all',
    locked,
    lockedMessage = 'You do not have permission to view this queue.',
    children,
    className,
}) {
    if (locked) {
        return (
            <Card className={cn('panel-card border-dashed bg-muted/20 dark:bg-slate-800/55', className)}>
                <CardHeader>
                    <CardTitle className="text-base">{title}</CardTitle>
                    <CardDescription>{lockedMessage}</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    return (
        <Card className={cn('panel-card', className)}>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 space-y-0 border-b border-border/70 pb-4 dark:border-slate-700/80">
                <div className="min-w-0 space-y-1">
                    <CardTitle className="text-base dark:text-slate-100">{title}</CardTitle>
                    {description ? <CardDescription className="dark:text-slate-400">{description}</CardDescription> : null}
                </div>
                {href ? (
                    <Button variant="outline" size="sm" className="shrink-0" asChild>
                        <Link href={href}>{linkLabel}</Link>
                    </Button>
                ) : null}
            </CardHeader>
            <CardContent className="pt-0">{children}</CardContent>
        </Card>
    );
}
