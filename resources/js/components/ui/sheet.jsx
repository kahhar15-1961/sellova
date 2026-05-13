import * as React from 'react';
import * as SheetPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

const Sheet = SheetPrimitive.Root;
const SheetTrigger = SheetPrimitive.Trigger;
const SheetClose = SheetPrimitive.Close;
const SheetPortal = SheetPrimitive.Portal;

const SheetOverlay = React.forwardRef(({ className, ...props }, ref) => (
    <SheetPrimitive.Overlay
        className={cn('fixed inset-0 z-50 bg-slate-950/28 backdrop-blur-[6px]', className)}
        {...props}
        ref={ref}
    />
));
SheetOverlay.displayName = SheetPrimitive.Overlay.displayName;

const SheetContent = React.forwardRef(({ side = 'left', className, children, ...props }, ref) => (
    <SheetPortal>
        <SheetOverlay />
        <SheetPrimitive.Content
            ref={ref}
            className={cn(
                'fixed z-50 gap-4 border border-border/80 bg-card/98 p-6 shadow-card transition ease-in-out',
                side === 'left' && 'inset-y-0 left-0 h-full w-[86%] max-w-sm rounded-r-[1.75rem] border-l-0',
                side === 'right' && 'inset-y-0 right-0 h-full w-[86%] max-w-sm rounded-l-[1.75rem] border-r-0',
                className,
            )}
            {...props}
        >
            {children}
            <SheetPrimitive.Close className="absolute right-4 top-4 rounded-xl border border-transparent p-1.5 text-muted-foreground opacity-80 ring-offset-background transition hover:border-border/70 hover:bg-accent/70 hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                <X className="h-4 w-4" />
                <span className="sr-only">Close</span>
            </SheetPrimitive.Close>
        </SheetPrimitive.Content>
    </SheetPortal>
));
SheetContent.displayName = 'SheetContent';

const SheetHeader = ({ className, ...props }) => (
    <div className={cn('flex flex-col space-y-2 text-center sm:text-left', className)} {...props} />
);
SheetHeader.displayName = 'SheetHeader';

const SheetTitle = React.forwardRef(({ className, ...props }, ref) => (
    <SheetPrimitive.Title ref={ref} className={cn('text-lg font-semibold text-foreground', className)} {...props} />
));
SheetTitle.displayName = SheetPrimitive.Title.displayName;

const SheetDescription = React.forwardRef(({ className, ...props }, ref) => (
    <SheetPrimitive.Description ref={ref} className={cn('text-sm text-muted-foreground', className)} {...props} />
));
SheetDescription.displayName = SheetPrimitive.Description.displayName;

export { Sheet, SheetPortal, SheetOverlay, SheetTrigger, SheetClose, SheetContent, SheetHeader, SheetTitle, SheetDescription };
