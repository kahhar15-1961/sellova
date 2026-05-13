import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogPortal = DialogPrimitive.Portal;
const DialogClose = DialogPrimitive.Close;

const DialogOverlay = React.forwardRef(({ className, ...props }, ref) => (
    <DialogPrimitive.Overlay
        ref={ref}
        className={cn(
            'fixed inset-0 z-50 bg-slate-950/22 backdrop-blur-[8px] transition-all duration-200 data-[state=closed]:opacity-0 data-[state=open]:opacity-100',
            className,
        )}
        {...props}
    />
));
DialogOverlay.displayName = DialogPrimitive.Overlay.displayName;

const DialogContent = React.forwardRef(({ className, children, ...props }, ref) => (
    <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
            ref={ref}
            className={cn(
                'ds-modal-panel fixed left-[50%] top-[50%] z-50 grid w-[calc(100%-1.5rem)] max-w-xl translate-x-[-50%] translate-y-[-50%] gap-5 p-6 transition-all duration-200 data-[state=closed]:translate-y-[-48%] data-[state=closed]:scale-[0.98] data-[state=closed]:opacity-0 data-[state=open]:translate-y-[-50%] data-[state=open]:scale-100 data-[state=open]:opacity-100 sm:w-full sm:p-7',
                className,
            )}
            {...props}
        >
            {children}
            <DialogPrimitive.Close className="absolute right-4 top-4 rounded-2xl border border-transparent p-2 text-muted-foreground opacity-80 ring-offset-background transition hover:border-border/70 hover:bg-accent/70 hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                <X className="h-4 w-4" />
                <span className="sr-only">Close</span>
            </DialogPrimitive.Close>
        </DialogPrimitive.Content>
    </DialogPortal>
));
DialogContent.displayName = DialogPrimitive.Content.displayName;

const DialogHeader = ({ className, ...props }) => (
    <div className={cn('flex flex-col space-y-2 text-center sm:text-left', className)} {...props} />
);
DialogHeader.displayName = 'DialogHeader';

const DialogTitle = React.forwardRef(({ className, ...props }, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        className={cn('font-display text-[1.65rem] font-semibold leading-tight tracking-[-0.03em] text-foreground', className)}
        {...props}
    />
));
DialogTitle.displayName = DialogPrimitive.Title.displayName;

const DialogDescription = React.forwardRef(({ className, ...props }, ref) => (
    <DialogPrimitive.Description ref={ref} className={cn('text-sm leading-6 text-muted-foreground', className)} {...props} />
));
DialogDescription.displayName = DialogPrimitive.Description.displayName;

const DialogFooter = ({ className, ...props }) => (
    <div className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3', className)} {...props} />
);
DialogFooter.displayName = 'DialogFooter';

export {
    Dialog,
    DialogPortal,
    DialogOverlay,
    DialogClose,
    DialogTrigger,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle,
    DialogDescription,
};
