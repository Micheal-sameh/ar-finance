import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Employee, Paginated } from '@/types/finance';

interface Props {
    employees: Paginated<Employee>;
    filters: { search?: string };
}

export default function EmployeesIndex({ employees, filters }: Props) {
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Employee | null>(null);

    const form = useForm({
        name: '',
        email: '',
        job_title: '',
        salary: '',
        hire_date: new Date().toISOString().slice(0, 10),
        is_active: true as boolean,
    });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(employee: Employee) {
        setEditing(employee);
        form.setData({
            name: employee.name,
            email: employee.email ?? '',
            job_title: employee.job_title ?? '',
            salary: employee.salary,
            hire_date: employee.hire_date.slice(0, 10),
            is_active: employee.is_active,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('employees.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('employees.store'), {
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    async function destroy(employee: Employee) {
        if (await confirm(`Delete employee "${employee.name}"?`, { variant: 'danger', confirmLabel: 'Delete' })) {
            router.delete(route('employees.destroy', employee.id));
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('employees.index'), { search }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Employees" />

            <PageHeader
                title="Employees"
                subtitle="Everyone on payroll."
                action={
                    <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                        New Employee
                    </Button>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2" style={{ maxWidth: '320px' }}>
                        <Input placeholder="Search by name…" value={search} onChange={(e) => setSearch(e.target.value)} />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </div>

                {employees.data.length === 0 ? (
                    <EmptyState
                        icon={<Users2 size={20} />}
                        title="No employees yet"
                        description="Add an employee before running payroll."
                        action={<Button onClick={openCreate}>New Employee</Button>}
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Job title</Table.HeadCell>
                            <Table.HeadCell className="text-end">Salary</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {employees.data.map((employee) => (
                                <Table.Row key={employee.id}>
                                    <Table.Cell className="ps-3">{employee.name}</Table.Cell>
                                    <Table.Cell>{employee.job_title ?? '—'}</Table.Cell>
                                    <Table.Cell className="text-end">
                                        <MoneyDisplay amount={employee.salary} />
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={employee.is_active ? 'success' : 'neutral'}>
                                            {employee.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <div className="d-flex justify-content-end gap-1">
                                            <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={() => openEdit(employee)} aria-label="Edit">
                                                <Pencil size={15} />
                                            </button>
                                            <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-danger)' }} onClick={() => destroy(employee)} aria-label="Delete">
                                                <Trash2 size={15} />
                                            </button>
                                        </div>
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Edit Employee' : 'New Employee'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Add employee'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input label="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Input label="Email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
                    <Input label="Job title" value={form.data.job_title} onChange={(e) => form.setData('job_title', e.target.value)} error={form.errors.job_title} />
                    <Input
                        type="number"
                        step="0.01"
                        min="0.01"
                        label="Monthly salary"
                        value={form.data.salary}
                        onChange={(e) => form.setData('salary', e.target.value)}
                        error={form.errors.salary}
                    />
                    <Input
                        type="date"
                        label="Hire date"
                        value={form.data.hire_date}
                        onChange={(e) => form.setData('hire_date', e.target.value)}
                        error={form.errors.hire_date}
                    />
                    <div className="form-check">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            id="is_active"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                        />
                        <label className="form-check-label" htmlFor="is_active" style={{ fontSize: '14px' }}>
                            Active (eligible for payroll runs)
                        </label>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
