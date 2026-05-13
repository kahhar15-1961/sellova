import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-2xl text-[13px] font-semibold ring-offset-background transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/70 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 active:scale-[0.99]',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground shadow-card hover:-translate-y-0.5 hover:bg-primary/94',
                destructive:
                    'border border-red-200/80 bg-red-50 text-red-700 shadow-sm hover:-translate-y-0.5 hover:border-red-300 hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200',
                outline: 'border border-input/85 bg-card text-foreground shadow-sm hover:border-border hover:bg-accent/72 hover:text-accent-foreground',
                secondary: 'border border-border/80 bg-secondary/72 text-secondary-foreground shadow-sm hover:bg-secondary',
                ghost: 'text-muted-foreground hover:bg-accent/80 hover:text-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-11 px-4',
                sm: 'h-9 rounded-xl px-3 text-[12px]',
                lg: 'h-12 px-5 text-[14px]',
                icon: 'h-11 w-11',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

const Button = React.forwardRef(({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    return <Comp className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />;
});
Button.displayName = 'Button';

export { Button, buttonVariants };
