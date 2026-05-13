import * as React from 'react';

export const Switch = React.forwardRef(function Switch(
  { className = '', checked = false, onCheckedChange, disabled = false, ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      type="button"
      role="switch"
      aria-checked={checked}
      data-state={checked ? 'checked' : 'unchecked'}
      disabled={disabled}
      onClick={() => onCheckedChange?.(!checked)}
      className={[
        'relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full border transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/60',
        checked ? 'border-primary/10 bg-primary' : 'border-border/80 bg-secondary',
        disabled ? 'cursor-not-allowed opacity-50' : '',
        className,
      ].join(' ')}
      {...props}
    >
      <span
        data-state={checked ? 'checked' : 'unchecked'}
        className={[
          'pointer-events-none inline-block h-5 w-5 rounded-full bg-background shadow-sm transition-transform',
          checked ? 'translate-x-6' : 'translate-x-1',
        ].join(' ')}
      />
    </button>
  );
});
