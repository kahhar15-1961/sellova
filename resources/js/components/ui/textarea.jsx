import * as React from 'react';
import { cn } from '@/lib/utils';

const Textarea = React.forwardRef(({ className, ...props }, ref) => (
    <textarea
        className={cn(
            'ds-control flex min-h-[132px] py-3.5 leading-6',
            className,
        )}
        ref={ref}
        {...props}
    />
));
Textarea.displayName = 'Textarea';

export { Textarea };
