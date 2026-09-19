import { useCostCenters } from '@/hooks/useCostCenters';
import { Select, type SelectProps } from '@/Components/ui/Select';

export interface CostCenterPickerProps extends Omit<SelectProps, 'value' | 'onChange'> {
    value: number | null;
    onChange: (costCenterId: number | null) => void;
}

/**
 * Cost-center tagging dropdown, fed by the shared useCostCenters() hook.
 * Cost centers are a short, flat-ish list per tenant, so a plain select
 * is enough — no need for AccountPicker's searchable combobox treatment.
 */
export function CostCenterPicker({ value, onChange, ...rest }: CostCenterPickerProps) {
    const { costCenters, loading } = useCostCenters();

    return (
        <Select
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}
            {...rest}
        >
            <option value="">{loading ? 'Loading…' : 'No center'}</option>
            {costCenters.map((center) => (
                <option key={center.id} value={center.id}>
                    {center.name}
                </option>
            ))}
        </Select>
    );
}
