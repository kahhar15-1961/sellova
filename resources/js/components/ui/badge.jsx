import * as React from 'react';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.01em] transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-primary/10 text-primary',
                secondary: 'border-transparent bg-secondary/85 text-secondary-foreground',
                outline: 'border-border/80 text-foreground',
                success: 'border-transparent bg-[hsl(var(--success-soft))] text-[hsl(var(--success))]',
                warning: 'border-transparent bg-[hsl(var(--warning-soft))] text-[hsl(var(--warning))]',
                danger: 'border-transparent bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-200',
                muted: 'border-transparent bg-muted/90 text-muted-foreground',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Badge({ className, variant, ...props }) {
    return <div className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };
