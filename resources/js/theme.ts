/**
 * Single source of truth for the Avarewase brand palette. ui/ components
 * resolve semantic variant props (e.g. Badge variant="success") to these
 * tokens internally — never hardcode a hex value in a page or component.
 */
export const colors = {
    primary: '#52758A',
    primaryHover: '#3F6174',
    gold: '#9B8358',
    navy: '#293940',
    bg: '#F2F2ED',
    surface: '#FBFBF8',
    text: '#34444A',
    label: '#748084',
    border: '#D9DDD8',
    success: '#527864',
    danger: '#A75F5F',
    warning: '#9B7848',
    info: '#52758A',
} as const;

export type Variant = 'primary' | 'success' | 'warning' | 'danger' | 'neutral' | 'info';

export const variantColors: Record<Variant, { fg: string; bg: string; border: string }> = {
    primary: { fg: colors.primary, bg: '#EDF2F3', border: '#C9D8DE' },
    success: { fg: colors.success, bg: '#EDF3EF', border: '#C6D9CE' },
    warning: { fg: colors.warning, bg: '#F6F1E8', border: '#E3D3B8' },
    danger: { fg: colors.danger, bg: '#F7EEEE', border: '#E6CACA' },
    neutral: { fg: colors.label, bg: '#ECEEEA', border: colors.border },
    info: { fg: colors.info, bg: '#EDF2F3', border: '#C9D8DE' },
};

export const radius = {
    sm: '8px',
    md: '12px',
} as const;

export const shadow = {
    card: '0 1px 2px rgba(45, 58, 61, 0.025), 0 4px 14px rgba(45, 58, 61, 0.02)',
} as const;
