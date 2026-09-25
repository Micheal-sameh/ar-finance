import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';

export default function FixedAssetsCreate() {
    const form = useForm({
        name: '',
        purchase_date: new Date().toISOString().slice(0, 10),
        cost: '',
        salvage_value: '0',
        useful_life_years: '5',
        depreciation_method: 'straight_line',
        asset_account_id: null as number | null,
        depreciation_account_id: null as number | null,
        accumulated_depreciation_account_id: null as number | null,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('fixed-assets.store'));
    }

    return (
        <>
            <Head title="New Fixed Asset" />

            <PageHeader title="New Fixed Asset" subtitle="Straight-line depreciation, posted monthly from the register." />

            <form onSubmit={submit}>
                <Card>
                    <div className="row g-3 mb-4">
                        <div className="col-md-6">
                            <Input
                                label="Name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                error={form.errors.name}
                                placeholder="e.g. MacBook Pro"
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="date"
                                label="Purchase date"
                                value={form.data.purchase_date}
                                onChange={(e) => form.setData('purchase_date', e.target.value)}
                                error={form.errors.purchase_date}
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="number"
                                step="0.01"
                                min="0.01"
                                label="Cost"
                                value={form.data.cost}
                                onChange={(e) => form.setData('cost', e.target.value)}
                                error={form.errors.cost}
                                placeholder="0.00"
                            />
                        </div>
                    </div>

                    <div className="row g-3 mb-4">
                        <div className="col-md-3">
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                label="Salvage value"
                                value={form.data.salvage_value}
                                onChange={(e) => form.setData('salvage_value', e.target.value)}
                                error={form.errors.salvage_value}
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="number"
                                min="1"
                                max="100"
                                label="Useful life (years)"
                                value={form.data.useful_life_years}
                                onChange={(e) => form.setData('useful_life_years', e.target.value)}
                                error={form.errors.useful_life_years}
                            />
                        </div>
                        <div className="col-md-6">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Depreciation method
                            </label>
                            <div
                                className="d-flex align-items-center"
                                style={{
                                    height: '38px',
                                    padding: '0 12px',
                                    borderRadius: 'var(--af-radius-sm)',
                                    border: '1px solid var(--af-border)',
                                    color: 'var(--af-label)',
                                    fontSize: '14px',
                                    backgroundColor: '#F8F9FB',
                                }}
                            >
                                Straight-line (only method supported today)
                            </div>
                        </div>
                    </div>

                    <div className="row g-3 mb-4">
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Asset account
                            </label>
                            <AccountPicker
                                value={form.data.asset_account_id}
                                onChange={(id) => form.setData('asset_account_id', id)}
                                error={form.errors.asset_account_id}
                                placeholder="e.g. Office Equipment"
                                filterType="asset"
                                dropUp
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Depreciation expense account
                            </label>
                            <AccountPicker
                                value={form.data.depreciation_account_id}
                                onChange={(id) => form.setData('depreciation_account_id', id)}
                                error={form.errors.depreciation_account_id}
                                placeholder="e.g. Depreciation Expense"
                                filterType="expense"
                                dropUp
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Accumulated depreciation account
                            </label>
                            <AccountPicker
                                value={form.data.accumulated_depreciation_account_id}
                                onChange={(id) => form.setData('accumulated_depreciation_account_id', id)}
                                error={form.errors.accumulated_depreciation_account_id}
                                placeholder="e.g. Accumulated Depreciation"
                                filterType="asset"
                                dropUp
                            />
                        </div>
                    </div>

                    <div className="d-flex justify-content-end pt-3" style={{ borderTop: '1px solid var(--af-border)' }}>
                        <Button type="submit" loading={form.processing}>
                            Add to Register
                        </Button>
                    </div>
                </Card>
            </form>
        </>
    );
}
