import { usePage } from '@inertiajs/react';
import { Select } from '@/Components/ui/Select';
import type { PlatformShare } from '@/Components/layout/TenantSwitcher';

interface ServiceFieldProps {
    value: number | string | null;
    onChange: (tenantId: number | null) => void;
    error?: string;
}

/**
 * Create modals opened from the combined "All services" view have no
 * service to save into, so a Platform Admin picks one here. The value goes
 * up as `for_tenant`, which TenantContext reads — no switching needed.
 */
export function ServiceField({ value, onChange, error }: ServiceFieldProps) {
    const { platform } = usePage<{ platform: PlatformShare | null }>().props;

    return (
        <Select
            label="Service"
            help="The business this record belongs to. Pick the one you are working for."
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}
            error={error}
        >
            <option value="">Select a service…</option>
            {(platform?.tenants ?? [])
                .filter((tenant) => tenant.is_active)
                .map((tenant) => (
                    <option key={tenant.id} value={tenant.id}>
                        {tenant.name}
                    </option>
                ))}
        </Select>
    );
}
