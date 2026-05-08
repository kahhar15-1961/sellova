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
        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border border-transparent transition-colors',
        checked ? 'bg-primary' : 'bg-muted',
        disabled ? 'cursor-not-allowed opacity-50' : '',
        className,
      ].join(' ')}
      {...props}
    >
      <span
        data-state={checked ? 'checked' : 'unchecked'}
        className={[
          'pointer-events-none inline-block h-5 w-5 rounded-full bg-background shadow transition-transform',
          checked ? 'translate-x-5' : 'translate-x-1',
        ].join(' ')}
      />
    </button>
  );
});
