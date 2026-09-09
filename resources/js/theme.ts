/**
 * Single source of truth for the Avarewase brand palette. ui/ components
 * resolve semantic variant props (e.g. Badge variant="success") to these
 * tokens internally — never hardcode a hex value in a page or component.
 */
export const colors = {
    primary: '#2563EB',
    primaryHover: '#1D4ED8',
    gold: '#C9922A',
    navy: '#1E2D4A',
    bg: '#F1F3F7',
    surface: '#FFFFFF',
    text: '#0F172A',
    label: '#6B7A99',
    border: '#D1D9E6',
    success: '#16A34A',
    danger: '#DC2626',
    warning: '#D97706',
    info: '#2563EB',
} as const;

export type Variant = 'primary' | 'success' | 'warning' | 'danger' | 'neutral' | 'info';

export const variantColors: Record<Variant, { fg: string; bg: string; border: string }> = {
    primary: { fg: colors.primary, bg: '#EFF4FE', border: '#BFD3FB' },
    success: { fg: colors.success, bg: '#EAF7EE', border: '#BFE6CB' },
    warning: { fg: colors.warning, bg: '#FCF3E7', border: '#F3D9AE' },
    danger: { fg: colors.danger, bg: '#FCEBEB', border: '#F4BFBF' },
    neutral: { fg: colors.label, bg: '#EEF1F6', border: colors.border },
    info: { fg: colors.info, bg: '#EFF4FE', border: '#BFD3FB' },
};

export const radius = {
    sm: '8px',
    md: '12px',
} as const;

export const shadow = {
    card: '0 1px 2px 0 rgb(15 23 42 / 0.06), 0 1px 3px 0 rgb(15 23 42 / 0.08)',
} as const;
