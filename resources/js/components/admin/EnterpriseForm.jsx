import { useMemo, useState } from 'react';
import { CalendarClock, Check, ChevronsUpDown, Clock, ImagePlus, ListChecks, Search } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';

export function Field({ label, hint, required = false, className, children }) {
    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex items-center justify-between gap-3">
                <label className="ds-label mb-0">
                    {label}
                    {required ? <span className="ml-1 text-destructive">*</span> : null}
                </label>
                {hint ? <span className="ds-helper">{hint}</span> : null}
            </div>
            {children}
        </div>
    );
}

export function EnterpriseSelect({ name, options = [], defaultValue = '', placeholder = 'Select option', required = false, value, onChange, className }) {
    const [internalValue, setInternalValue] = useState(defaultValue);
    const resolvedValue = value ?? internalValue;
    const handleChange = (next) => {
        if (value === undefined) {
            setInternalValue(next);
        }
        onChange?.({ target: { value: next } });
    };

    return (
        <div className={className}>
            {name ? <input type="hidden" name={name} value={resolvedValue || ''} required={required} /> : null}
            <Select value={resolvedValue || undefined} onValueChange={handleChange}>
                <SelectTrigger className="font-medium">
                    <SelectValue placeholder={placeholder || undefined} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={String(option.value)}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export function EnterpriseMultiSelect({ name, options = [], hint = 'Hold Cmd/Ctrl to select multiple', className }) {
    const [selected, setSelected] = useState([]);
    const [query, setQuery] = useState('');
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options;
        return options.filter((option) => String(option.label).toLowerCase().includes(q));
    }, [options, query]);
    const selectedLabels = options.filter((option) => selected.includes(String(option.value))).map((option) => option.label);

    return (
        <div className={className}>
            {selected.map((value) => (
                <input key={value} type="hidden" name={`${name}[]`} value={value} />
            ))}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button type="button" variant="outline" className="h-auto min-h-12 w-full justify-between gap-3 rounded-2xl px-4 py-3 text-left font-medium">
                        <span className="min-w-0 flex-1 truncate">
                            {selectedLabels.length ? selectedLabels.join(', ') : hint}
                        </span>
                        <span className="flex items-center gap-2 text-xs text-muted-foreground">
                            {selected.length}/{options.length}
                            <ChevronsUpDown className="h-4 w-4" />
                        </span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-80" align="start">
                    <DropdownMenuLabel className="flex items-center justify-between gap-3">
                        <span className="flex items-center gap-2">
                            <ListChecks className="h-4 w-4" />
                            Select values
                        </span>
                        <button type="button" className="text-xs text-muted-foreground hover:text-foreground" onClick={() => setSelected([])}>
                            Clear
                        </button>
                    </DropdownMenuLabel>
                    <div className="relative px-2 pb-2">
                        <Search className="pointer-events-none absolute left-5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search options" className="h-8 pl-8" />
                    </div>
                    <DropdownMenuSeparator />
                    <div className="max-h-72 overflow-y-auto p-1">
                        {filtered.map((option) => {
                            const optionValue = String(option.value);
                            return (
                                <DropdownMenuCheckboxItem
                                    key={optionValue}
                                    checked={selected.includes(optionValue)}
                                    onCheckedChange={(checked) => {
                                        setSelected((current) => {
                                            if (checked) return Array.from(new Set([...current, optionValue]));
                                            return current.filter((item) => item !== optionValue);
                                        });
                                    }}
                                >
                                    {option.label}
                                </DropdownMenuCheckboxItem>
                            );
                        })}
                        {filtered.length === 0 ? <div className="px-2 py-6 text-center text-sm text-muted-foreground">No options found.</div> : null}
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

export function DateTimeField({ name, type = 'datetime-local', defaultValue = '', className }) {
    const Icon = type === 'time' ? Clock : CalendarClock;
    return (
        <div className={cn('relative', className)}>
            <Input name={name} type={type} defaultValue={defaultValue} className="pr-11 font-medium" />
            <Icon className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        </div>
    );
}

export function FileUploadField({ name, multiple = false, accept = 'image/*' }) {
    return (
        <label className="flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-[hsl(var(--ring)/0.35)] bg-[hsl(var(--accent)/0.65)] px-4 py-6 text-center transition hover:border-[hsl(var(--ring)/0.55)] hover:bg-[hsl(var(--accent)/0.9)]">
            <ImagePlus className="h-5 w-5 text-muted-foreground" />
            <span className="mt-2 text-sm font-semibold text-foreground">{multiple ? 'Upload gallery images' : 'Upload primary image'}</span>
            <span className="mt-1 text-xs text-muted-foreground">JPG, PNG, WebP up to 5 MB</span>
            <input name={name} type="file" accept={accept} multiple={multiple} className="sr-only" />
        </label>
    );
}

export function SectionHeader({ icon: Icon = Check, title, description, className }) {
    return (
        <div className={cn('flex items-start gap-3', className)}>
            <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-border/70 bg-accent/70 text-primary shadow-sm">
                <Icon className="h-4 w-4" />
            </span>
            <div>
                <h3 className="font-display text-sm font-bold uppercase tracking-[0.16em] text-foreground">{title}</h3>
                {description ? <p className="mt-1 text-sm leading-6 text-muted-foreground">{description}</p> : null}
            </div>
        </div>
    );
}
