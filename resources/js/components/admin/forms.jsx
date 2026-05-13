import { useId } from 'react';
import { Calendar, Check, ChevronDown, Circle, CreditCard, UploadCloud } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export function FormGroup({
    label,
    hint,
    error,
    success,
    required = false,
    htmlFor,
    className,
    children,
}) {
    return (
        <div className={cn('space-y-3', className)}>
            {label ? (
                <div className="flex items-start justify-between gap-4">
                    <Label htmlFor={htmlFor} className="mb-0">
                        {label}
                        {required ? <span className="ml-1 text-destructive">*</span> : null}
                    </Label>
                    {hint ? <span className="ds-helper text-right">{hint}</span> : null}
                </div>
            ) : null}
            {children}
            <InputError error={error} success={success} />
        </div>
    );
}

export function InputError({ error, success, className }) {
    if (!error && !success) {
        return null;
    }

    return (
        <p className={cn('text-xs font-medium', error ? 'text-destructive' : 'text-[hsl(var(--success))]', className)}>
            {error || success}
        </p>
    );
}

export function TextInput({ className, error, success, ...props }) {
    return <Input aria-invalid={Boolean(error)} data-success={success ? 'true' : undefined} className={className} {...props} />;
}

export function TextareaInput({ className, error, success, ...props }) {
    return <Textarea aria-invalid={Boolean(error)} data-success={success ? 'true' : undefined} className={className} {...props} />;
}

export function SelectInput({ value, onValueChange, placeholder = 'Select an option', options = [], className, triggerClassName, error, success, ...props }) {
    return (
        <Select value={value} onValueChange={onValueChange} {...props}>
            <SelectTrigger
                aria-invalid={Boolean(error)}
                data-success={success ? 'true' : undefined}
                className={cn(className, triggerClassName)}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={String(option.value)} value={String(option.value)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function Checkbox({ label, description, className, checked = false, ...props }) {
    const id = useId();

    return (
        <label
            htmlFor={id}
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-2xl border border-border/75 bg-card px-4 py-3.5 transition hover:border-border hover:bg-accent/35',
                className,
            )}
        >
            <span className="relative mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center">
                <input
                    id={id}
                    type="checkbox"
                    checked={checked}
                    className="peer sr-only"
                    {...props}
                />
                <span className="flex h-5 w-5 items-center justify-center rounded-md border border-input bg-card shadow-sm transition peer-focus-visible:ring-4 peer-focus-visible:ring-ring/10 peer-checked:border-primary peer-checked:bg-primary">
                    <Check className="h-3.5 w-3.5 text-white opacity-0 transition peer-checked:opacity-100" />
                </span>
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-foreground">{label}</span>
                {description ? <span className="mt-1 block text-xs leading-5 text-muted-foreground">{description}</span> : null}
            </span>
        </label>
    );
}

export function RadioGroup({ label, options = [], value, onChange, className, optionClassName, name }) {
    return (
        <div className={cn('space-y-3', className)}>
            {label ? <p className="ds-label mb-0">{label}</p> : null}
            <div className="grid gap-3 sm:grid-cols-2">
                {options.map((option) => {
                    const checked = String(value) === String(option.value);

                    return (
                        <label
                            key={String(option.value)}
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-2xl border border-border/75 bg-card px-4 py-3.5 transition hover:border-border hover:bg-accent/35',
                                checked && 'border-primary/20 bg-accent/45 shadow-sm',
                                optionClassName,
                            )}
                        >
                            <span className="relative mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center">
                                <input
                                    type="radio"
                                    name={name}
                                    value={option.value}
                                    checked={checked}
                                    onChange={() => onChange?.(option.value)}
                                    className="peer sr-only"
                                />
                                <span className="flex h-5 w-5 items-center justify-center rounded-full border border-input bg-card shadow-sm transition peer-focus-visible:ring-4 peer-focus-visible:ring-ring/10 peer-checked:border-primary">
                                    <Circle className="h-2.5 w-2.5 fill-primary text-primary opacity-0 transition peer-checked:opacity-100" />
                                </span>
                            </span>
                            <span className="min-w-0">
                                <span className="block text-sm font-semibold text-foreground">{option.label}</span>
                                {option.description ? (
                                    <span className="mt-1 block text-xs leading-5 text-muted-foreground">{option.description}</span>
                                ) : null}
                            </span>
                        </label>
                    );
                })}
            </div>
        </div>
    );
}

export function PaymentTabs({ value, onValueChange, tabs, className }) {
    return (
        <Tabs value={value} onValueChange={onValueChange} className={className}>
            <TabsList className="grid h-12 w-full grid-cols-2 rounded-2xl bg-secondary/80">
                {tabs.map((tab) => (
                    <TabsTrigger key={tab.value} value={tab.value} className="gap-2">
                        {tab.icon ?? <CreditCard className="h-4 w-4" />}
                        {tab.label}
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}

export function FileUpload({ title = 'Click to upload or drag and drop', description = 'High resolution images (PNG, JPG, WEBP). Max 5MB per file.', className, icon: Icon = UploadCloud, ...props }) {
    const id = useId();

    return (
        <label
            htmlFor={id}
            className={cn(
                'flex min-h-[220px] cursor-pointer flex-col items-center justify-center rounded-[1.75rem] border border-dashed border-[hsl(var(--ring)/0.35)] bg-[hsl(var(--accent)/0.65)] px-6 py-8 text-center transition hover:border-[hsl(var(--ring)/0.55)] hover:bg-[hsl(var(--accent)/0.9)]',
                className,
            )}
        >
            <span className="mb-5 flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full border border-border/70 bg-card shadow-card">
                <Icon className="h-7 w-7 text-[hsl(var(--info))]" />
            </span>
            <span className="text-lg font-semibold tracking-[-0.02em] text-foreground">{title}</span>
            <span className="mt-2 max-w-sm text-sm leading-6 text-muted-foreground">{description}</span>
            <input id={id} type="file" className="sr-only" {...props} />
        </label>
    );
}

export function DatePicker({ className, error, success, ...props }) {
    return (
        <div className="relative">
            <Input
                type="date"
                aria-invalid={Boolean(error)}
                data-success={success ? 'true' : undefined}
                className={cn('pr-11', className)}
                {...props}
            />
            <Calendar className="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground/75" />
        </div>
    );
}

export function PaymentSelect({ options = [], value, onValueChange, placeholder = 'Choose a payment method' }) {
    return (
        <SelectInput
            value={value}
            onValueChange={onValueChange}
            options={options}
            placeholder={placeholder}
            triggerClassName="pl-4"
        />
    );
}

export function CompactSelect({ value, onValueChange, options = [], placeholder = 'Select', className }) {
    return (
        <Select value={value} onValueChange={onValueChange}>
            <SelectTrigger className={cn('h-10 rounded-xl px-3.5 text-[13px]', className)}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={String(option.value)} value={String(option.value)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function CompactInput({ className, ...props }) {
    return <Input className={cn('h-10 rounded-xl px-3.5 text-[13px]', className)} {...props} />;
}

export function SelectCaret() {
    return <ChevronDown className="h-4 w-4 text-muted-foreground/80" />;
}
