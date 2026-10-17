import React from 'react';

type LoadingSpinnerProps = {
    label?: string;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
};

const sizes = {
    sm: 'h-4 w-4',
    md: 'h-5 w-5',
    lg: 'h-8 w-8',
};

export function LoadingSpinner({label = 'Učitavanje...', size = 'md', className = ''}: LoadingSpinnerProps) {
    return (
        <span className={`inline-flex items-center ${className}`} role="status" aria-label={label}>
            <svg className={`${sizes[size]} animate-spin`} viewBox="0 0 24 24" aria-hidden="true">
                <circle className="opacity-25" cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="4"/>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <span className="sr-only">{label}</span>
        </span>
    );
}

type LoadingOverlayProps = {
    label?: string;
    visible: boolean;
};

export function LoadingOverlay({label = 'Učitavanje...', visible}: LoadingOverlayProps) {
    if (!visible) return null;

    return (
        <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/75 backdrop-blur-[1px]" role="status" aria-live="polite">
            <div className="flex items-center gap-3 rounded-md bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow">
                <LoadingSpinner label={label}/>
                {label}
            </div>
        </div>
    );
}

type LoadingButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
    loading?: boolean;
    loadingLabel?: string;
};

export function LoadingButton({loading = false, loadingLabel = 'Učitavanje...', children, disabled, className = '', ...props}: LoadingButtonProps) {
    return (
        <button {...props} disabled={loading || disabled} aria-busy={loading || undefined} className={className}>
            {loading && <LoadingSpinner label={loadingLabel} size="sm" className="mr-2"/>}
            {loading ? loadingLabel : children}
        </button>
    );
}
