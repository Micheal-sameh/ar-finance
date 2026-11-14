import { Head, Link, router } from '@inertiajs/react';
import { Check, HeartHandshake, Undo2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Modal } from '@/Components/ui/Modal';
import { Table } from '@/Components/ui/Table';
import type { Donation, DonationsPage } from '@/types/donations';

interface Props {
    donations: DonationsPage;
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <>
            <dt style={{ color: 'var(--af-label)', fontWeight: 500 }}>{label}</dt>
            <dd className="mb-0">{children}</dd>
        </>
    );
}

export default function DonationCancelRequests({ donations }: Props) {
    const confirm = useConfirm();
    const [selected, setSelected] = useState<Donation | null>(null);

    async function decide(donation: Donation, approve: boolean) {
        const confirmed = await confirm(
            approve
                ? `Approve cancelling donation #${donation.receipt_number}? Its journal entries will be reversed.`
                : `Reject the cancellation of donation #${donation.receipt_number}? It stays received.`,
            { variant: approve ? 'danger' : 'primary', confirmLabel: approve ? 'Approve cancellation' : 'Reject request' },
        );

        if (confirmed) {
            setSelected(null);
            router.post(route(approve ? 'donations.approve-cancel' : 'donations.reject-cancel', donation.id), {}, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Donation cancel requests" />

            <PageHeader
                title="Donation cancel requests"
                subtitle="Cancellations asked for by your team. Click a row to see the full details. Approving reverses the donation’s journal entries; rejecting keeps the donation as received."
                action={
                    <Link href={route('donations.index')}>
                        <Button variant="outline">Back to donations</Button>
                    </Link>
                }
            />

            <Card padded={false}>
                {donations.data.length === 0 ? (
                    <EmptyState icon={<HeartHandshake size={20} />} title="No pending requests" description="When someone asks to cancel a donation it appears here for approval." />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Receipt</Table.HeadCell>
                            <Table.HeadCell>Donor</Table.HeadCell>
                            <Table.HeadCell>Amount</Table.HeadCell>
                            <Table.HeadCell>Requested by</Table.HeadCell>
                            <Table.HeadCell>Reason</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Decision</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {donations.data.map((donation) => (
                                <Table.Row key={donation.id} onClick={() => setSelected(donation)} style={{ cursor: 'pointer' }}>
                                    <Table.Cell className="ps-3" label="Receipt">
                                        <strong>#{donation.receipt_number}</strong>
                                        <div style={{ color: 'var(--af-label)', fontSize: '12px' }}>{donation.donated_at}</div>
                                    </Table.Cell>
                                    <Table.Cell label="Donor">{donation.donor_name ?? <span style={{ color: 'var(--af-label)' }}>ابناء الانبا رويس</span>}</Table.Cell>
                                    <Table.Cell label="Amount">
                                        <MoneyDisplay amount={donation.amount} currency={donation.currency} />
                                    </Table.Cell>
                                    <Table.Cell label="Requested by">{donation.cancel_requested_by_name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Reason">{donation.cancel_reason ?? <span style={{ color: 'var(--af-label)' }}>No reason given</span>}</Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Decision">
                                        <div className="d-flex justify-content-end gap-2" onClick={(e) => e.stopPropagation()}>
                                            <Button variant="danger" leadingIcon={<Check size={16} />} onClick={() => decide(donation, true)}>
                                                Approve
                                            </Button>
                                            <Button variant="outline" leadingIcon={<Undo2 size={16} />} onClick={() => decide(donation, false)}>
                                                Reject
                                            </Button>
                                        </div>
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Modal
                open={selected !== null}
                onClose={() => setSelected(null)}
                title={selected ? `Donation #${selected.receipt_number}` : 'Donation'}
                footer={
                    selected && (
                        <>
                            <Button variant="outline" leadingIcon={<Undo2 size={16} />} onClick={() => decide(selected, false)}>
                                Reject request
                            </Button>
                            <Button variant="danger" leadingIcon={<Check size={16} />} onClick={() => decide(selected, true)}>
                                Approve cancellation
                            </Button>
                        </>
                    )
                }
            >
                {selected && (
                    <dl className="d-grid gap-2 mb-0" style={{ gridTemplateColumns: 'max-content 1fr', columnGap: '16px', fontSize: '14px' }}>
                        <Detail label="Receipt">#{selected.receipt_number}</Detail>
                        <Detail label="Date">{selected.donated_at}</Detail>
                        <Detail label="Donor">{selected.donor_name ?? 'ابناء الانبا رويس'}</Detail>
                        <Detail label="Center">{selected.cost_center_name ?? '—'}</Detail>
                        <Detail label="Amount">
                            <MoneyDisplay amount={selected.amount} currency={selected.currency} />
                        </Detail>
                        {Number(selected.exchange_rate) !== 1 && (
                            <Detail label="Booked as">
                                {selected.base_amount} at rate {Number(selected.exchange_rate)}
                            </Detail>
                        )}
                        <Detail label="Payment">
                            {selected.payment_method_label}
                            {selected.payment_reference && ` · ref ${selected.payment_reference}`}
                        </Detail>
                        <Detail label="Notes">{selected.notes ?? '—'}</Detail>
                        <Detail label="Recorded by">
                            {selected.created_by_name ?? '—'}
                            {selected.created_at && ` · ${new Date(selected.created_at).toLocaleString()}`}
                        </Detail>
                        <Detail label="Cancel requested by">
                            {selected.cancel_requested_by_name ?? '—'}
                            {selected.cancel_requested_at && ` · ${new Date(selected.cancel_requested_at).toLocaleString()}`}
                        </Detail>
                        <Detail label="Reason">{selected.cancel_reason ?? 'No reason given'}</Detail>
                    </dl>
                )}
            </Modal>
        </>
    );
}
