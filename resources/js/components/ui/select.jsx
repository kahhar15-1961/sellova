import * as React from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';
import { Check, ChevronDown, ChevronUp } from 'lucide-react';
import { cn } from '@/lib/utils';

const Select = SelectPrimitive.Root;
const SelectGroup = SelectPrimitive.Group;
const SelectValue = SelectPrimitive.Value;

const SelectTrigger = React.forwardRef(({ className, children, ...props }, ref) => (
    <SelectPrimitive.Trigger
        ref={ref}
        className={cn(
            'ds-control flex items-center justify-between gap-3 pr-3.5 text-left text-[14px] font-medium',
            className,
        )}
        {...props}
    >
        {children}
        <SelectPrimitive.Icon asChild>
            <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground/80" />
        </SelectPrimitive.Icon>
    </SelectPrimitive.Trigger>
));
SelectTrigger.displayName = SelectPrimitive.Trigger.displayName;

const SelectContent = React.forwardRef(({ className, children, position = 'popper', ...props }, ref) => (
    <SelectPrimitive.Portal>
        <SelectPrimitive.Content
            ref={ref}
            className={cn(
                'relative z-50 max-h-96 min-w-[8rem] overflow-hidden rounded-[1.35rem] border border-border/80 bg-card/98 p-1.5 text-card-foreground shadow-card backdrop-blur-xl data-[state=closed]:opacity-0 data-[state=closed]:scale-[0.98] data-[state=open]:opacity-100 data-[state=open]:scale-100',
                position === 'popper' &&
                    'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
                className,
            )}
            position={position}
            {...props}
        >
            <SelectPrimitive.ScrollUpButton className="flex cursor-default items-center justify-center py-1">
                <ChevronUp className="h-4 w-4" />
            </SelectPrimitive.ScrollUpButton>
            <SelectPrimitive.Viewport className={cn('p-1', position === 'popper' && 'h-[var(--radix-select-trigger-height)] w-full min-w-[var(--radix-select-trigger-width)]')}>
                {children}
            </SelectPrimitive.Viewport>
            <SelectPrimitive.ScrollDownButton className="flex cursor-default items-center justify-center py-1">
                <ChevronDown className="h-4 w-4" />
            </SelectPrimitive.ScrollDownButton>
        </SelectPrimitive.Content>
    </SelectPrimitive.Portal>
));
SelectContent.displayName = SelectPrimitive.Content.displayName;

const SelectItem = React.forwardRef(({ className, children, ...props }, ref) => (
    <SelectPrimitive.Item
        ref={ref}
        className={cn(
            'relative flex w-full cursor-default select-none items-center rounded-xl py-2.5 pl-9 pr-3.5 text-[13px] font-medium outline-none transition-colors focus:bg-accent/88 focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
            className,
        )}
        {...props}
    >
        <span className="absolute left-3 flex h-4 w-4 items-center justify-center">
            <SelectPrimitive.ItemIndicator>
                <Check className="h-4 w-4 text-primary" />
            </SelectPrimitive.ItemIndicator>
        </span>
        <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
    </SelectPrimitive.Item>
));
SelectItem.displayName = SelectPrimitive.Item.displayName;

export { Select, SelectGroup, SelectValue, SelectTrigger, SelectContent, SelectItem };
