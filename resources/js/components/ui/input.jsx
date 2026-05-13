import * as React from 'react';
import { cn } from '@/lib/utils';

const Input = React.forwardRef(({ className, type, ...props }, ref) => (
    <input
        type={type}
        className={cn(
            'ds-control flex min-w-0 file:border-0 file:bg-transparent file:text-sm file:font-medium',
            className,
        )}
        ref={ref}
        {...props}
    />
));
Input.displayName = 'Input';

export { Input };
