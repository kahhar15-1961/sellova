import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * @param {{ title: string, description?: string, breadcrumbs?: { label: string, href?: string }[], actions?: import('react').ReactNode, className?: string }} props
 */
export function PageHeader({ title, description, breadcrumbs = [], actions, className }) {
    return (
        <div className={cn('mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div className="space-y-2">
                {breadcrumbs.length > 0 && (
                    <nav className="flex flex-wrap items-center gap-1 text-xs font-medium text-muted-foreground">
                        {breadcrumbs.map((crumb, i) => (
                            <span key={i} className="flex items-center gap-1">
                                {i > 0 && <ChevronRight className="h-3 w-3 opacity-60" />}
                                {crumb.href ? (
                                    <Link href={crumb.href} className="hover:text-foreground">
                                        {crumb.label}
                                    </Link>
                                ) : (
                                    <span className="text-foreground">{crumb.label}</span>
                                )}
                            </span>
                        ))}
                    </nav>
                )}
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{title}</h1>
                    {description && <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{description}</p>}
                </div>
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
