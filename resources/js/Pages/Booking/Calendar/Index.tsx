import type { DateSelectArg, EventClickArg } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin, { type DateClickArg } from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Head, router } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import { useRef, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { useBookableItems } from '@/hooks/useBookableItems';
import api from '@/lib/api';
import type { BookingEvent, BookingRecurrence } from '@/types/booking';
import { InfoTip } from '@/Components/ui/InfoTip';

const STATUS_VARIANT: Record<BookingEvent['status'], 'info' | 'success' | 'neutral'> = {
    confirmed: 'info',
    paid: 'success',
    cancelled: 'neutral',
};

/** Local wall-clock value for an <input type="datetime-local">, e.g. "2026-10-06T09:00". */
function toInputValue(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

interface Props {
    viewingAllTenants: boolean;
}

export default function CalendarIndex({ viewingAllTenants }: Props) {
    const calendarRef = useRef<FullCalendar>(null);
    const [isNarrowScreen] = useState(() => typeof window !== 'undefined' && window.matchMedia('(max-width: 767.98px)').matches);
    const { items, loading: itemsLoading } = useBookableItems();

    const [selection, setSelection] = useState<{ start: string; end: string } | null>(null);
    const [bookableItemId, setBookableItemId] = useState<number | null>(null);
    const [bookingTypeId, setBookingTypeId] = useState<number | null>(null);
    const [recurrence, setRecurrence] = useState<BookingRecurrence>('none');
    const [recurrenceUntil, setRecurrenceUntil] = useState('');
    const [notes, setNotes] = useState('');
    const [bookingDepositAmount, setBookingDepositAmount] = useState('');
    const [bookingDepositDueAt, setBookingDepositDueAt] = useState('');
    const [bookingError, setBookingError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const [selectedEvent, setSelectedEvent] = useState<BookingEvent | null>(null);
    const [paymentAccountId, setPaymentAccountId] = useState<number | null>(null);
    const [payMode, setPayMode] = useState(false);
    const [payAmount, setPayAmount] = useState('');
    const [depositMode, setDepositMode] = useState(false);
    const [depositDueAt, setDepositDueAt] = useState('');

    const [dayViewDate, setDayViewDate] = useState<string | null>(null);
    const [dayViewBookings, setDayViewBookings] = useState<BookingEvent[]>([]);
    const [dayViewLoading, setDayViewLoading] = useState(false);

    const selectedItem = items.find((item) => item.id === bookableItemId) ?? null;
    const selectedType = selectedItem?.booking_types.find((type) => type.id === bookingTypeId) ?? null;
    const needsType = Boolean(selectedItem && selectedItem.booking_types.length > 0);
    const unitPrice = selectedType ? Number(selectedType.price) : 0;

    function estimatedPrice(): number | null {
        if (!selectedItem || !selection) return null;

        if (!selectedType) return null;

        if (selectedType.rate_type === 'flat') return unitPrice;

        const hours = (new Date(selection.end).getTime() - new Date(selection.start).getTime()) / 3_600_000;

        return Math.round(unitPrice * hours * 100) / 100;
    }

    function estimatedDeposit(): number | null {
        const price = estimatedPrice();

        if (!selectedItem?.deposit_percent || price === null) return null;

        return Math.round(price * (Number(selectedItem.deposit_percent) / 100) * 100) / 100;
    }

    /** What actually gets submitted — the person's override if they typed one and it's valid, otherwise the item's minimum. */
    function depositToCollect(): number | null {
        const minimum = estimatedDeposit();

        if (bookingDepositAmount === '') return minimum;

        const entered = Number(bookingDepositAmount);

        return Number.isFinite(entered) ? entered : minimum;
    }

    function depositAmountError(): string | undefined {
        if (bookingDepositAmount === '') return undefined;

        const entered = Number(bookingDepositAmount);
        const minimum = estimatedDeposit();
        const price = estimatedPrice();

        if (!Number.isFinite(entered) || entered <= 0) return 'Enter a valid amount.';
        if (minimum !== null && entered < minimum) return `Must be at least the minimum deposit (${minimum}).`;
        if (price !== null && entered > price) return `Can't be more than the price (${price}).`;

        return undefined;
    }

    function handleSelect(info: DateSelectArg) {
        setBookableItemId(null);
        setBookingTypeId(null);
        setRecurrence('none');
        setRecurrenceUntil('');
        setNotes('');
        setBookingDepositAmount('');
        setBookingDepositDueAt('');
        setBookingError(null);

        // A month-view (or any all-day) selection spans the whole day(s)
        // clicked, not an actual time slot — default to a 1-hour slot
        // starting at 9am instead, and let the time fields below be edited.
        if (info.allDay) {
            const start = new Date(info.start);
            start.setHours(9, 0, 0, 0);
            const end = new Date(start);
            end.setHours(start.getHours() + 1);
            setSelection({ start: toInputValue(start), end: toInputValue(end) });
        } else {
            setSelection({ start: toInputValue(info.start), end: toInputValue(info.end) });
        }

        info.view.calendar.unselect();
    }

    function closeBookingModal() {
        setSelection(null);
        setBookingDepositAmount('');
        setBookingDepositDueAt('');
    }

    /** A Platform Admin's view is read-only, so a day click opens that day's bookings grouped by tenant instead of starting a new booking. */
    function openDayView(info: DateClickArg) {
        const date = info.dateStr.slice(0, 10);
        const from = `${date}T00:00:00`;
        const to = new Date(`${date}T00:00:00`);
        to.setDate(to.getDate() + 1);

        setDayViewDate(date);
        setDayViewBookings([]);
        setDayViewLoading(true);

        api
            .get<BookingEvent[]>(route('bookings.calendar'), { params: { from, to: toInputValue(to) } })
            .then((response) => {
                if (Array.isArray(response.data)) setDayViewBookings(response.data);
            })
            .finally(() => setDayViewLoading(false));
    }

    function closeDayView() {
        setDayViewDate(null);
        setDayViewBookings([]);
    }

    function submitBooking() {
        if (!selection || !bookableItemId || (needsType && !bookingTypeId)) return;
        if (recurrence !== 'none' && !recurrenceUntil) return;
        if (selectedItem?.deposit_percent && (!bookingDepositDueAt || depositAmountError())) return;

        setSubmitting(true);
        setBookingError(null);

        router.post(
            route('bookings.store'),
            {
                bookable_item_id: bookableItemId,
                booking_type_id: needsType ? bookingTypeId : null,
                recurrence,
                recurrence_until: recurrence === 'none' ? null : recurrenceUntil,
                starts_at: selection.start,
                ends_at: selection.end,
                notes: notes || null,
                payment_due_at: bookingDepositDueAt || null,
                deposit_amount: selectedItem?.deposit_percent ? depositToCollect() : null,
            },
            {
                // Without this, Inertia fully remounts the page on this
                // POST's redirect-back response — tearing down the live
                // FullCalendar instance (and this component's state) before
                // the refetchEvents() call below can run against it.
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    const error = (page.props.flash as { error?: string } | undefined)?.error;

                    if (error) {
                        setBookingError(error);
                    } else {
                        setSelection(null);
                        setBookingDepositAmount('');
                        setBookingDepositDueAt('');
                        calendarRef.current?.getApi().refetchEvents();
                    }
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    function closeEventModal() {
        setSelectedEvent(null);
        setPayMode(false);
        setPayAmount('');
        setDepositMode(false);
        setPaymentAccountId(null);
        setDepositDueAt('');
    }

    /** What actually gets submitted — the person's partial amount if they typed one and it's valid, otherwise the full remaining balance. */
    function amountToCollect(): number | null {
        if (!selectedEvent) return null;
        if (payAmount === '') return selectedEvent.balance_due;

        const entered = Number(payAmount);

        return Number.isFinite(entered) ? entered : selectedEvent.balance_due;
    }

    function payAmountError(): string | undefined {
        if (!selectedEvent || payAmount === '') return undefined;

        const entered = Number(payAmount);

        if (!Number.isFinite(entered) || entered <= 0) return 'Enter a valid amount.';
        if (entered > selectedEvent.balance_due) return `Can't exceed the balance due (${selectedEvent.balance_due}).`;

        return undefined;
    }

    function cancelBooking() {
        if (!selectedEvent) return;

        router.post(route('bookings.cancel', selectedEvent.id), {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeEventModal();
                calendarRef.current?.getApi().refetchEvents();
            },
        });
    }

    function markPaid() {
        if (!selectedEvent || !paymentAccountId || payAmountError()) return;

        router.post(
            route('bookings.mark-paid', selectedEvent.id),
            { payment_account_id: paymentAccountId, amount: amountToCollect() },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    closeEventModal();
                    calendarRef.current?.getApi().refetchEvents();
                },
            },
        );
    }

    function payDeposit() {
        if (!selectedEvent || !paymentAccountId || !depositDueAt) return;

        router.post(
            route('bookings.pay-deposit', selectedEvent.id),
            { payment_account_id: paymentAccountId, payment_due_at: depositDueAt },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    closeEventModal();
                    calendarRef.current?.getApi().refetchEvents();
                },
            },
        );
    }

    return (
        <>
            <Head title="Booking Calendar" />

            <PageHeader
                title="Booking Calendar"
                subtitle={
                    viewingAllTenants
                        ? 'Every service’s bookings, combined. Pick an item to book it in its own service; click a day in month view to see that day’s bookings.'
                        : 'Select a time slot on the calendar to book it.'
                }
            />

            <Card padded={false}>
                <div className="p-3">
                    <FullCalendar
                        ref={calendarRef}
                        plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
                        initialView={isNarrowScreen ? 'timeGridDay' : 'timeGridWeek'}
                        headerToolbar={
                            isNarrowScreen
                                ? { left: 'prev,next', center: 'title', right: 'today' }
                                : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' }
                        }
                        footerToolbar={isNarrowScreen ? { center: 'dayGridMonth,timeGridWeek,timeGridDay' } : undefined}
                        titleFormat={isNarrowScreen ? { month: 'short', day: 'numeric', year: 'numeric' } : undefined}
                        dayHeaderFormat={isNarrowScreen ? { weekday: 'short', day: 'numeric' } : undefined}
                        selectable
                        select={handleSelect}
                        views={viewingAllTenants ? { dayGridMonth: { selectable: false } } : undefined}
                        dateClick={viewingAllTenants ? (info: DateClickArg) => info.view.type === 'dayGridMonth' && openDayView(info) : undefined}
                        eventClick={(info: EventClickArg) => setSelectedEvent(info.event.extendedProps as BookingEvent)}
                        height="auto"
                        slotMinTime="06:00:00"
                        slotMaxTime="22:00:00"
                        events={(fetchInfo, successCallback, failureCallback) => {
                            api
                                .get<Array<{ id: number } & BookingEvent>>(route('bookings.calendar'), {
                                    params: { from: fetchInfo.startStr, to: fetchInfo.endStr },
                                })
                                .then((response) => {
                                    const rows = response.data;

                                    if (!Array.isArray(rows)) {
                                        failureCallback(new Error('Unexpected response loading the calendar.'));
                                        return;
                                    }

                                    successCallback(
                                        rows.map((event) => ({
                                            id: String(event.id),
                                            title: event.tenant_name ? `${event.tenant_name} · ${event.title}` : event.title,
                                            start: event.start,
                                            end: event.end,
                                            backgroundColor: event.status === 'cancelled'
                                                ? 'var(--af-label)'
                                                : event.is_overdue
                                                  ? 'var(--af-danger)'
                                                  : undefined,
                                            extendedProps: event,
                                        })),
                                    );
                                })
                                .catch((error) => failureCallback(error));
                        }}
                    />
                </div>
            </Card>

            <Modal
                open={Boolean(selection)}
                onClose={closeBookingModal}
                title="New Booking"
                footer={
                    <>
                        <Button variant="outline" onClick={closeBookingModal}>
                            Cancel
                        </Button>
                        <Button
                            onClick={submitBooking}
                            loading={submitting}
                            disabled={
                                !bookableItemId ||
                                (needsType && !bookingTypeId) ||
                                (recurrence !== 'none' && !recurrenceUntil) ||
                                !selection ||
                                selection.end <= selection.start ||
                                Boolean(selectedItem?.deposit_percent && (!bookingDepositDueAt || depositAmountError()))
                            }
                        >
                            Book
                        </Button>
                    </>
                }
            >
                <div className="d-flex flex-column gap-3">
                    {bookingError && <div style={{ color: 'var(--af-danger)', fontSize: '13px' }}>{bookingError}</div>}

                    <Select
                        label="What are you booking?"
                        help="The hall, room or resource the customer wants."
                        value={bookableItemId ?? ''}
                        onChange={(e) => {
                            setBookableItemId(e.target.value ? Number(e.target.value) : null);
                            setBookingTypeId(null);
                        }}
                    >
                        <option value="">{itemsLoading ? 'Loading…' : 'Select an item'}</option>
                        {items.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}{viewingAllTenants && item.tenant_name ? ` · ${item.tenant_name}` : ''} {item.is_exclusive ? '' : '(shared)'}
                            </option>
                        ))}
                    </Select>

                    {needsType && selectedItem && (
                        <Select
                            label="Booking type"
                            help="The kind of event. It decides the rate that is charged."
                            value={bookingTypeId ?? ''}
                            onChange={(e) => setBookingTypeId(e.target.value ? Number(e.target.value) : null)}
                        >
                            <option value="">Select a type</option>
                            {selectedItem.booking_types.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name} — {type.price} {type.rate_type === 'hourly' ? '/ hour' : 'flat'}
                                </option>
                            ))}
                        </Select>
                    )}

                    {selection && (
                        <div className="d-flex gap-3">
                            <Input
                                type="datetime-local"
                                label="Starts"
                                help="When the booking begins."
                                value={selection.start}
                                onChange={(e) => setSelection({ ...selection, start: e.target.value })}
                            />
                            <Input
                                type="datetime-local"
                                label="Ends"
                                help="When the booking finishes. It must be after the start."
                                value={selection.end}
                                onChange={(e) => setSelection({ ...selection, end: e.target.value })}
                                error={selection.end <= selection.start ? 'Must be after the start time.' : undefined}
                            />
                        </div>
                    )}

                    <div className="d-flex gap-3">
                        <Select label="Repeat" help="Make the same booking happen again on a regular schedule instead of entering each one by hand." value={recurrence} onChange={(e) => setRecurrence(e.target.value as BookingRecurrence)}>
                            <option value="none">Does not repeat</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </Select>
                        {recurrence !== 'none' && (
                            <Input
                                type="date"
                                label="Repeat until"
                                help="The last day the repeating booking can happen. No occurrences are created after it."
                                value={recurrenceUntil}
                                min={selection?.start.slice(0, 10)}
                                onChange={(e) => setRecurrenceUntil(e.target.value)}
                            />
                        )}
                    </div>

                    {estimatedPrice() !== null && (
                        <div style={{ fontSize: '14px' }}>
                            Estimated price: <strong>{estimatedPrice()}</strong>
                        </div>
                    )}

                    {selectedItem?.deposit_percent && (
                        <div className="d-flex flex-column gap-3" style={{ borderTop: '1px solid var(--af-border)', paddingTop: '12px' }}>
                            <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                This item requires a minimum {selectedItem.deposit_percent}% deposit
                                {estimatedDeposit() !== null && (
                                    <>
                                        {' '}
                                        — <strong>{estimatedDeposit()}</strong>
                                    </>
                                )}{' '}
                                — collected now into the account the admin set up for this item. The person booking can pay more upfront if they want.
                            </div>

                            <Input
                                type="number"
                                step="0.01"
                                min={estimatedDeposit() ?? 0.01}
                                label="Deposit to collect"
                                help="The part of the price paid upfront to hold the booking. The rest is collected later."
                                placeholder={estimatedDeposit() !== null ? String(estimatedDeposit()) : undefined}
                                value={bookingDepositAmount}
                                onChange={(e) => setBookingDepositAmount(e.target.value)}
                                error={depositAmountError()}
                            />

                            <Input
                                type="datetime-local"
                                label="Balance due by"
                                help="The date the remaining balance must be paid."
                                value={bookingDepositDueAt}
                                onChange={(e) => setBookingDepositDueAt(e.target.value)}
                            />
                        </div>
                    )}

                    <Input label="Notes (optional)" help="Anything staff should know about this booking, such as set-up requests." value={notes} onChange={(e) => setNotes(e.target.value)} />
                </div>
            </Modal>

            <Modal
                open={Boolean(selectedEvent)}
                onClose={closeEventModal}
                title="Booking"
                footer={
                    selectedEvent && (
                        <>
                            {selectedEvent.can_cancel && selectedEvent.status === 'confirmed' && !payMode && !depositMode && (
                                <Button variant="outline" onClick={cancelBooking}>
                                    Cancel booking
                                </Button>
                            )}
                            {selectedEvent.can_pay_deposit &&
                                selectedEvent.accepts_deposits &&
                                selectedEvent.status === 'confirmed' &&
                                !selectedEvent.deposit_paid_at &&
                                !payMode &&
                                !depositMode && <Button variant="outline" onClick={() => setDepositMode(true)}>Pay deposit</Button>}
                            {selectedEvent.can_mark_paid && selectedEvent.status === 'confirmed' && !payMode && !depositMode && (
                                <Button onClick={() => setPayMode(true)}>
                                    {selectedEvent.balance_due < Number(selectedEvent.price) ? 'Collect balance' : 'Mark paid'}
                                </Button>
                            )}
                            {payMode && (
                                <Button onClick={markPaid} disabled={!paymentAccountId || Boolean(payAmountError())}>
                                    Confirm payment
                                </Button>
                            )}
                            {depositMode && (
                                <Button onClick={payDeposit} disabled={!paymentAccountId || !depositDueAt}>
                                    Confirm deposit
                                </Button>
                            )}
                        </>
                    )
                }
            >
                {selectedEvent && (
                    <div className="d-flex flex-column gap-3">
                        <div className="d-flex align-items-center gap-2">
                            <Badge variant={STATUS_VARIANT[selectedEvent.status]}>{selectedEvent.status}</Badge>
                            <Badge variant={selectedEvent.is_exclusive ? 'warning' : 'info'}>
                                {selectedEvent.is_exclusive ? 'Exclusive' : 'Shared'}
                            </Badge>
                            {selectedEvent.is_overdue && <Badge variant="danger">Overdue</Badge>}
                        </div>

                        {selectedEvent.tenant_name && (
                            <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>Tenant: {selectedEvent.tenant_name}</div>
                        )}

                        {selectedEvent.booking_type_name && <div style={{ fontSize: '14px' }}>Type: {selectedEvent.booking_type_name}</div>}
                        {selectedEvent.recurrence !== 'none' && (
                            <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Repeats {selectedEvent.recurrence}
                                {selectedEvent.recurrence_until && ` until ${selectedEvent.recurrence_until}`}
                            </div>
                        )}
                        <div style={{ fontSize: '14px' }}>Price: {selectedEvent.price}</div>

                        {selectedEvent.deposit_paid_at && (
                            <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Deposit of {selectedEvent.deposit_amount} paid {new Date(selectedEvent.deposit_paid_at).toLocaleString()}.
                            </div>
                        )}

                        {selectedEvent.status === 'confirmed' && selectedEvent.balance_due < Number(selectedEvent.price) && (
                            <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Collected so far: {Math.round((Number(selectedEvent.price) - selectedEvent.balance_due) * 100) / 100}.
                            </div>
                        )}

                        {selectedEvent.status === 'confirmed' && (
                            <div style={{ fontSize: '14px' }}>
                                Balance due: <strong>{selectedEvent.balance_due}</strong>
                                {selectedEvent.payment_due_at && <> by {new Date(selectedEvent.payment_due_at).toLocaleString()}.</>}
                            </div>
                        )}

                        {payMode && (
                            <div className="d-flex flex-column gap-3">
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={selectedEvent.balance_due}
                                    label="Amount to collect"
                                    placeholder={String(selectedEvent.balance_due)}
                                    help="Defaults to the full balance due — enter less to record a partial payment."
                                    value={payAmount}
                                    onChange={(e) => setPayAmount(e.target.value)}
                                    error={payAmountError()}
                                />
                                <div>
                                    <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                        Received into <InfoTip>The bank or cash account that receives this payment.</InfoTip>
                                    </label>
                                    <AccountPicker value={paymentAccountId} onChange={setPaymentAccountId} filterType="asset" tenantId={selectedEvent.tenant_id ?? null} />
                                </div>
                            </div>
                        )}

                        {depositMode && (
                            <div className="d-flex flex-column gap-3">
                                <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                    Deposit ({selectedEvent.deposit_percent}%):{' '}
                                    <strong>
                                        {Math.round(Number(selectedEvent.price) * (Number(selectedEvent.deposit_percent) / 100) * 100) / 100}
                                    </strong>
                                </div>
                                <div>
                                    <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                        Received into
                                    </label>
                                    <AccountPicker value={paymentAccountId} onChange={setPaymentAccountId} filterType="asset" tenantId={selectedEvent.tenant_id ?? null} />
                                </div>
                                <Input
                                    type="datetime-local"
                                    label="Complete payment by"
                                    help="The date the remaining balance must be paid."
                                    value={depositDueAt}
                                    onChange={(e) => setDepositDueAt(e.target.value)}
                                />
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            <Modal
                open={Boolean(dayViewDate)}
                onClose={closeDayView}
                title={dayViewDate ? `Bookings on ${dayViewDate}` : 'Bookings'}
            >
                {dayViewLoading ? (
                    <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>Loading…</div>
                ) : dayViewBookings.length === 0 ? (
                    <EmptyState icon={<CalendarClock size={20} />} title="No bookings that day" />
                ) : (
                    <div className="d-flex flex-column gap-3">
                        {Object.entries(
                            dayViewBookings.reduce<Record<string, BookingEvent[]>>((groups, booking) => {
                                const tenant = booking.tenant_name ?? 'Unknown service';
                                (groups[tenant] ??= []).push(booking);
                                return groups;
                            }, {}),
                        ).map(([tenant, bookings]) => (
                            <Card key={tenant} padded={false}>
                                <div
                                    className="px-3 py-2"
                                    style={{ borderBottom: '1px solid var(--af-border)', fontWeight: 600, fontSize: '14px', color: 'var(--af-navy)' }}
                                >
                                    {tenant}
                                </div>
                                <div className="d-flex flex-column">
                                    {bookings.map((booking, index) => (
                                        <div
                                            key={booking.id}
                                            className="px-3 py-2 d-flex align-items-center justify-content-between gap-2"
                                            style={index < bookings.length - 1 ? { borderBottom: '1px solid var(--af-border)' } : undefined}
                                        >
                                            <div>
                                                <div style={{ fontSize: '13px' }}>{booking.title}</div>
                                                <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                                                    {new Date(booking.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} –{' '}
                                                    {new Date(booking.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </div>
                                            </div>
                                            <Badge variant={STATUS_VARIANT[booking.status]}>{booking.status}</Badge>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </Modal>
        </>
    );
}
