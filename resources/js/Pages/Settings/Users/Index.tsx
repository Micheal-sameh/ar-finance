import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, ShieldCheck } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { AppUser, Paginated, UserStatus } from '@/types/finance';

interface Props {
    users: Paginated<AppUser>;
    filters: { status?: string; role?: string; search?: string };
    canManage: boolean;
    availableRoles: string[];
}

export default function UsersIndex({ users, filters, canManage, availableRoles }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<AppUser | null>(null);
    const { auth } = usePage<{ auth: { user: { id: number } | null } }>().props;

    const form = useForm<{ status: UserStatus; role: string }>({
        status: 'active',
        role: '',
    });

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

    return (
        <>
            <Head title="Users" />

            <PageHeader
                title="Users"
                subtitle="Everyone with access to this tenant."
                action={<ExportButton href={route('users.export', filters)} />}
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2 flex-wrap align-items-end">
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
                            {availableRoles.map((role) => (
                                <option key={role} value={role}>
                                    {role}
                                </option>
                            ))}
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
                </div>

                {users.data.length === 0 ? (
                    <EmptyState icon={<ShieldCheck size={20} />} title="No users found" description="Try a different search." />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Email</Table.HeadCell>
                            <Table.HeadCell>Membership Code</Table.HeadCell>
                            <Table.HeadCell>Roles</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            {canManage && <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>}
                        </Table.Head>
                        <tbody>
                            {users.data.map((user) => (
                                <Table.Row key={user.id}>
                                    <Table.Cell className="ps-3">{user.name}</Table.Cell>
                                    <Table.Cell>{user.email}</Table.Cell>
                                    <Table.Cell>{user.membership_code ?? '—'}</Table.Cell>
                                    <Table.Cell>
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
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={user.status === 'active' ? 'success' : 'danger'}>
                                            {user.status === 'active' ? 'Active' : 'Suspended'}
                                        </Badge>
                                    </Table.Cell>
                                    {canManage && (
                                        <Table.Cell className="text-end pe-3">
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
                        {availableRoles.map((role) => (
                            <option key={role} value={role}>
                                {role}
                            </option>
                        ))}
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
