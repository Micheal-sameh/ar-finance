import { useCostCenters } from '@/hooks/useCostCenters';
import { Select, type SelectProps } from '@/Components/ui/Select';
import type { CostCenterType } from '@/types/finance';

export interface CostCenterPickerProps extends Omit<SelectProps, 'value' | 'onChange'> {
    value: number | null;
    onChange: (costCenterId: number | null) => void;
    /** Only list centers of this type (e.g. profit centers for revenue lines). */
    filterType?: CostCenterType | null;
}

const GROUPS: { type: CostCenterType; label: string }[] = [
    { type: 'profit', label: 'Profit centers' },
    { type: 'cost', label: 'Cost centers' },
];

/**
 * Cost-center tagging dropdown, fed by the shared useCostCenters() hook.
 * Cost centers are a short, flat-ish list per tenant, so a plain select
 * is enough — no need for AccountPicker's searchable combobox treatment.
 */
export function CostCenterPicker({ value, onChange, filterType = null, ...rest }: CostCenterPickerProps) {
    const { costCenters, loading } = useCostCenters();

    return (
        <Select
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}
            {...rest}
        >
            <option value="">{loading ? 'Loading…' : 'No center'}</option>
            {GROUPS.filter((group) => !filterType || group.type === filterType).map((group) => {
                const centers = costCenters.filter((center) => center.type === group.type);

                return (
                    centers.length > 0 && (
                        <optgroup key={group.type} label={group.label}>
                            {centers.map((center) => (
                                <option key={center.id} value={center.id}>
                                    {center.name}
                                </option>
                            ))}
                        </optgroup>
                    )
                );
            })}
        </Select>
    );
}
