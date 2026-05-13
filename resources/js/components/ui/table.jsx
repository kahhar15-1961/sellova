import * as React from 'react';
import { cn } from '@/lib/utils';

const Table = React.forwardRef(({ className, ...props }, ref) => (
    <div className="admin-scrollbar relative w-full overflow-auto rounded-b-[1.5rem] rounded-t-none bg-card/98 shadow-none dark:bg-slate-800/95">
        <table ref={ref} className={cn('w-full caption-bottom text-sm', className)} {...props} />
    </div>
));
Table.displayName = 'Table';

const TableHeader = React.forwardRef(({ className, ...props }, ref) => (
    <thead ref={ref} className={cn('[&_tr]:border-b border-border/70 bg-secondary/45 dark:border-slate-700/90 dark:bg-slate-700/35', className)} {...props} />
));
TableHeader.displayName = 'TableHeader';

const TableBody = React.forwardRef(({ className, ...props }, ref) => (
    <tbody ref={ref} className={cn('[&_tr:last-child]:border-0', className)} {...props} />
));
TableBody.displayName = 'TableBody';

const TableRow = React.forwardRef(({ className, ...props }, ref) => (
    <tr
        ref={ref}
        className={cn(
            'border-b border-border/50 transition-colors duration-200 hover:bg-accent/45 data-[state=selected]:bg-accent/65',
            'dark:border-slate-700/80 dark:hover:bg-slate-700/30 dark:data-[state=selected]:bg-slate-700/45',
            'h-[76px]',
            className,
        )}
        {...props}
    />
));
TableRow.displayName = 'TableRow';

const TableHead = React.forwardRef(({ className, ...props }, ref) => (
    <th
        ref={ref}
        className={cn(
            'h-14 px-5 text-left align-middle text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground dark:text-slate-500',
            className,
        )}
        {...props}
    />
));
TableHead.displayName = 'TableHead';

const TableCell = React.forwardRef(({ className, ...props }, ref) => (
    <td ref={ref} className={cn('px-5 py-4 align-middle text-[14px] text-foreground dark:text-slate-200', className)} {...props} />
));
TableCell.displayName = 'TableCell';

export { Table, TableHeader, TableBody, TableHead, TableRow, TableCell };
