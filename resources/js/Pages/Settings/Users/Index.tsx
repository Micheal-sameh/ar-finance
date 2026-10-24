import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { AppUser, Paginated, Tenant, UserStatus } from '@/types/finance';

const PLATFORM_ROLES = ['Platform Admin', 'Portal Manager'];

function RoleOptions({ roles }: { roles: string[] }) {
    const platformRoles = roles.filter((role) => PLATFORM_ROLES.includes(role));
    const tenantRoles = roles.filter((role) => !PLATFORM_ROLES.includes(role));

    return (
        <>
            {platformRoles.length > 0 && (
                <optgroup label="Platform">
                    {platformRoles.map((role) => (
                        <option key={role} value={role}>
                            {role}
                        </option>
                    ))}
                </optgroup>
            )}
            {tenantRoles.length > 0 && (
                <optgroup label="Tenant">
                    {tenantRoles.map((role) => (
                        <option key={role} value={role}>
                            {role}
                        </option>
                    ))}
                </optgroup>
            )}
        </>
    );
}

function TenantCheckboxes({
    tenants,
    value,
    onChange,
    error,
}: {
    tenants: Pick<Tenant, 'id' | 'name'>[];
    value: number[];
    onChange: (next: number[]) => void;
    error?: string;
}) {
    return (
        <div>
            <div style={{ fontSize: '12px', color: 'var(--af-label)', marginBottom: '4px' }}>Tenants</div>
            <div
                className="d-flex flex-column gap-1 p-2"
                style={{ border: '1px solid var(--af-border)', borderRadius: 'var(--af-radius-sm)', maxHeight: '180px', overflowY: 'auto' }}
            >
                {tenants.map((tenant) => (
                    <label key={tenant.id} className="d-flex align-items-center gap-2" style={{ fontSize: '14px' }}>
                        <input
                            type="checkbox"
                            className="form-check-input m-0"
                            checked={value.includes(tenant.id)}
                            onChange={(e) => onChange(e.target.checked ? [...value, tenant.id] : value.filter((id) => id !== tenant.id))}
                        />
                        {tenant.name}
                    </label>
                ))}
            </div>
            {error && <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{error}</div>}
        </div>
    );
}

interface Props {
    users: Paginated<AppUser>;
    filters: { status?: string; role?: string; search?: string };
    canManage: boolean;
    canAssignTenant: boolean;
    canAssignRole: boolean;
    canCreate: boolean;
    availableRoles: string[];
    tenants: Pick<Tenant, 'id' | 'name'>[];
    viewingAllTenants: boolean;
}

export default function UsersIndex({ users, filters, canManage, canAssignTenant, canAssignRole, canCreate, availableRoles, tenants, viewingAllTenants }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<AppUser | null>(null);
    const [creating, setCreating] = useState(false);
    const [managingTenants, setManagingTenants] = useState<AppUser | null>(null);
    const { auth } = usePage<{ auth: { user: { id: number } | null } }>().props;

    const form = useForm<{ status: UserStatus; role: string }>({
        status: 'active',
        role: '',
    });

    const createForm = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        role: '',
        tenant_ids: [] as number[],
        status: 'active' as UserStatus,
    });
    const tenantForm = useForm<{ tenant_ids: number[] }>({ tenant_ids: [] });
    const createIsPlatformRole = PLATFORM_ROLES.includes(createForm.data.role);

    function submitCreate(e: FormEvent) {
        e.preventDefault();
        createForm.post(route('platform.users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('users.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('users.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('users.index'), {}, { preserveState: true });
    }

    function openEdit(user: AppUser) {
        setEditing(user);
        form.setData({ status: user.status, role: user.roles[0] ?? '' });
        form.clearErrors();
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (!editing) {
            return;
        }

        form.put(route('users.update', editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    function openManageTenants(user: AppUser) {
        setManagingTenants(user);
        tenantForm.setData('tenant_ids', user.tenant_ids ?? []);
        tenantForm.clearErrors();
    }

    function submitTenants(e: FormEvent) {
        e.preventDefault();

        if (!managingTenants) {
            return;
        }

        tenantForm.put(route('platform.users.assign-tenant', managingTenants.id), {
            preserveScroll: true,
            onSuccess: () => setManagingTenants(null),
        });
    }

    function assignRole(user: AppUser, role: string) {
        router.put(route('platform.users.assign-role', user.id), { role }, { preserveScroll: true, preserveState: true });
    }

    return (
        <>
            <Head title="Users" />

            <PageHeader
                title="Users"
                subtitle={viewingAllTenants ? 'Every tenant’s users, combined. Switch into a tenant to manage one.' : 'Everyone with access to this tenant.'}
                action={
                    canCreate ? (
                        <Button onClick={() => setCreating(true)}>
                            <Plus size={15} /> Add user
                        </Button>
                    ) : (
                        !viewingAllTenants && <ExportButton href={route('users.export', filters)} />
                    )
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={Boolean(filters.status || filters.role || filters.search)}>
                    <form onSubmit={runSearch} className="af-filter-bar">
                        <Input
                            placeholder="Search by name or email…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{ maxWidth: '240px' }}
                        />
                        <Select
                            value={filters.role ?? ''}
                            onChange={(e) => runFilters({ role: e.target.value || undefined })}
                            style={{ maxWidth: '170px' }}
                        >
                            <option value="">All roles</option>
                            <RoleOptions roles={availableRoles} />
                        </Select>
                        <Select
                            value={filters.status ?? ''}
                            onChange={(e) => runFilters({ status: e.target.value || undefined })}
                            style={{ maxWidth: '150px' }}
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </Select>
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                        {(filters.status || filters.role || filters.search) && (
                            <Button type="button" variant="ghost" onClick={clearFilters}>
                                Clear
                            </Button>
                        )}
                    </form>
                    </FilterPanel>
                </div>

                {users.data.length === 0 ? (
                    <EmptyState icon={<ShieldCheck size={20} />} title="No users found" description="Try a different search." />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Email / Phone</Table.HeadCell>
                            <Table.HeadCell>Membership Code</Table.HeadCell>
                            <Table.HeadCell>Roles</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            {viewingAllTenants && <Table.HeadCell>Tenant</Table.HeadCell>}
                            {canManage && <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>}
                        </Table.Head>
                        <tbody>
                            {users.data.map((user) => (
                                <Table.Row key={user.id}>
                                    <Table.Cell className="ps-3" label="Name">{user.name}</Table.Cell>
                                    <Table.Cell label="Email">{user.email ?? user.phone ?? '—'}</Table.Cell>
                                    <Table.Cell label="Membership Code">{user.membership_code ?? '—'}</Table.Cell>
                                    <Table.Cell label="Roles">
                                        {viewingAllTenants && canAssignRole && user.id !== auth.user?.id ? (
                                            <Select
                                                value={user.roles[0] ?? ''}
                                                onChange={(e) => assignRole(user, e.target.value)}
                                                style={{ maxWidth: '180px' }}
                                            >
                                                <RoleOptions roles={availableRoles} />
                                            </Select>
                                        ) : (
                                            <div className="d-flex gap-1 flex-wrap">
                                                {user.roles.length > 0 ? (
                                                    user.roles.map((role) => (
                                                        <Badge key={role} variant="info">
                                                            {role}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <span style={{ fontSize: '13px', color: 'var(--af-label)' }}>—</span>
                                                )}
                                            </div>
                                        )}
                                    </Table.Cell>
                                    <Table.Cell label="Status">
                                        <Badge variant={user.status === 'active' ? 'success' : 'danger'}>
                                            {user.status === 'active' ? 'Active' : 'Suspended'}
                                        </Badge>
                                    </Table.Cell>
                                    {viewingAllTenants && (
                                        <Table.Cell label="Tenant">
                                            <div className="d-flex align-items-center gap-2">
                                                <span>{user.tenant_name || 'Unassigned'}</span>
                                                {canAssignTenant && user.id !== auth.user?.id && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm p-1"
                                                        style={{ color: 'var(--af-label)' }}
                                                        onClick={() => openManageTenants(user)}
                                                        aria-label="Manage tenants"
                                                    >
                                                        <Pencil size={14} />
                                                    </button>
                                                )}
                                            </div>
                                        </Table.Cell>
                                    )}
                                    {canManage && (
                                        <Table.Cell className="text-end pe-3" label="Actions">
                                            {user.id !== auth.user?.id && (
                                                <button
                                                    type="button"
                                                    className="btn btn-sm p-1"
                                                    style={{ color: 'var(--af-label)' }}
                                                    onClick={() => openEdit(user)}
                                                    aria-label="Edit"
                                                >
                                                    <Pencil size={15} />
                                                </button>
                                            )}
                                        </Table.Cell>
                                    )}
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title="Add user"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setCreating(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submitCreate} loading={createForm.processing}>
                            Create user
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitCreate} className="d-flex flex-column gap-3">
                    <Input label="Name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} error={createForm.errors.name} />
                    <Input label="Email" type="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} error={createForm.errors.email} />
                    <Input label="Phone" value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} error={createForm.errors.phone} />
                    <Input label="Temporary password" type="password" autoComplete="new-password" value={createForm.data.password} onChange={(e) => createForm.setData('password', e.target.value)} error={createForm.errors.password} />
                    <Select label="Role" value={createForm.data.role} onChange={(e) => createForm.setData('role', e.target.value)} error={createForm.errors.role}>
                        <option value="">Select a role…</option>
                        <RoleOptions roles={availableRoles} />
                    </Select>
                    {!createIsPlatformRole && (
                        <TenantCheckboxes
                            tenants={tenants}
                            value={createForm.data.tenant_ids}
                            onChange={(next) => createForm.setData('tenant_ids', next)}
                            error={createForm.errors.tenant_ids}
                        />
                    )}
                    <Select label="Status" value={createForm.data.status} onChange={(e) => createForm.setData('status', e.target.value as UserStatus)} error={createForm.errors.status}>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </Select>
                </form>
            </Modal>

            <Modal
                open={managingTenants !== null}
                onClose={() => setManagingTenants(null)}
                title={managingTenants ? `Tenants for ${managingTenants.name}` : ''}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setManagingTenants(null)}>
                            Cancel
                        </Button>
                        <Button onClick={submitTenants} loading={tenantForm.processing}>
                            Save tenants
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitTenants}>
                    <TenantCheckboxes
                        tenants={tenants}
                        value={tenantForm.data.tenant_ids}
                        onChange={(next) => tenantForm.setData('tenant_ids', next)}
                        error={tenantForm.errors.tenant_ids}
                    />
                    <div style={{ fontSize: '12px', color: 'var(--af-label)', marginTop: '8px' }}>
                        The first tenant they were given stays their default; they can switch between the others from the top bar.
                    </div>
                </form>
            </Modal>

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                title={editing ? `Edit ${editing.name}` : ''}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setEditing(null)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            Save changes
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Select
                        label="Role"
                        value={form.data.role}
                        onChange={(e) => form.setData('role', e.target.value)}
                        error={form.errors.role}
                    >
                        <option value="">Select a role…</option>
                        <RoleOptions roles={availableRoles} />
                    </Select>
                    <Select
                        label="Status"
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value as UserStatus)}
                        error={form.errors.status}
                    >
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </Select>
                </form>
            </Modal>
        </>
    );
}
