import type { DateSelectArg, EventApi, EventClickArg } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin, { type DateClickArg } from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Head, router } from '@inertiajs/react';
import { Banknote, CalendarClock, CreditCard, Landmark } from 'lucide-react';
import { useRef, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { InfoTip } from '@/Components/ui/InfoTip';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { useBookableItems } from '@/hooks/useBookableItems';
import api from '@/lib/api';
import type { BookingEvent, BookingRecurrence } from '@/types/booking';

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
    /** Placeholder shown the instant a booking is saved, until the refetch brings the real event in. */
    const pendingEventRef = useRef<EventApi | null>(null);
    const [isNarrowScreen] = useState(() => typeof window !== 'undefined' && window.matchMedia('(max-width: 767.98px)').matches);
    const { items, loading: itemsLoading } = useBookableItems();

    const [selection, setSelection] = useState<{ start: string; end: string } | null>(null);
    const [bookableItemId, setBookableItemId] = useState<number | null>(null);
    const [bookingTypeId, setBookingTypeId] = useState<number | null>(null);
    const [recurrence, setRecurrence] = useState<BookingRecurrence>('none');
    const [recurrenceUntil, setRecurrenceUntil] = useState('');
    const [notes, setNotes] = useState('');
    const [paymentMethod, setPaymentMethod] = useState<'cash' | 'bank_transfer' | 'pos'>('cash');
    const [paymentReference, setPaymentReference] = useState('');
    const [discountType, setDiscountType] = useState<'percent' | 'fixed'>('percent');
    const [discountValue, setDiscountValue] = useState('');
    const [bookingError, setBookingError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const [selectedEvent, setSelectedEvent] = useState<BookingEvent | null>(null);

    const [dayViewDate, setDayViewDate] = useState<string | null>(null);
    const [dayViewBookings, setDayViewBookings] = useState<BookingEvent[]>([]);
    const [dayViewLoading, setDayViewLoading] = useState(false);

    const selectedItem = items.find((item) => item.id === bookableItemId) ?? null;
    const selectedType = selectedItem?.booking_types.find((type) => type.id === bookingTypeId) ?? null;
    const needsType = Boolean(selectedItem && selectedItem.booking_types.length > 0);
    const unitPrice = selectedType ? Number(selectedType.price) : 0;

    /** The price before any discount. */
    function grossPrice(): number | null {
        if (!selectedItem || !selection) return null;

        if (!selectedType) return null;

        if (selectedType.rate_type === 'flat') return unitPrice;

        const hours = (new Date(selection.end).getTime() - new Date(selection.start).getTime()) / 3_600_000;

        return Math.round(unitPrice * hours * 100) / 100;
    }

    /** What the discount comes to in money, capped so it never exceeds the price. */
    function discountAmount(): number {
        const gross = grossPrice();
        const value = Number(discountValue);

        if (gross === null || discountValue === '' || !Number.isFinite(value) || value <= 0) return 0;

        const amount = discountType === 'percent' ? Math.round(gross * value) / 100 : value;

        return Math.min(Math.round(amount * 100) / 100, gross);
    }

    function discountError(): string | undefined {
        if (discountValue === '') return undefined;

        const value = Number(discountValue);
        const gross = grossPrice();

        if (!Number.isFinite(value) || value <= 0) return 'Enter a valid discount.';
        if (discountType === 'percent' && value > 100) return "A percent discount can't be more than 100.";
        if (discountType === 'fixed' && gross !== null && value > gross) return `Can't be more than the price (${gross}).`;

        return undefined;
    }

    /** The price after the discount — what the customer actually owes. */
    function estimatedPrice(): number | null {
        const gross = grossPrice();

        return gross === null ? null : Math.round((gross - discountAmount()) * 100) / 100;
    }

    function handleSelect(info: DateSelectArg) {
        setBookableItemId(null);
        setBookingTypeId(null);
        setRecurrence('none');
        setRecurrenceUntil('');
        setNotes('');
        setDiscountType('percent');
        setDiscountValue('');
        setPaymentMethod('cash');
        setPaymentReference('');
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
        if (discountError()) return;

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
                payment_method: paymentMethod,
                payment_reference: paymentReference.trim() || null,
                discount_type: discountValue === '' ? null : discountType,
                discount_value: discountValue === '' ? null : Number(discountValue),
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
                        const api = calendarRef.current?.getApi();

                        pendingEventRef.current?.remove();
                        pendingEventRef.current = selection
                            ? (api?.addEvent({
                                  title: `${selectedItem?.name ?? 'Booking'} · saving…`,
                                  start: selection.start,
                                  end: selection.end,
                                  classNames: ['opacity-75'],
                              }) ?? null)
                            : null;

                        setSelection(null);
                        api?.refetchEvents();
                    }
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    function closeEventModal() {
        setSelectedEvent(null);
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

                                    pendingEventRef.current?.remove();
                                    pendingEventRef.current = null;

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
                                            backgroundColor: event.status === 'cancelled' ? 'var(--af-label)' : undefined,
                                            extendedProps: event,
                                        })),
                                    );
                                })
                                .catch((error) => {
                                    pendingEventRef.current?.remove();
                                    pendingEventRef.current = null;
                                    failureCallback(error);
                                });
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
                                Boolean(discountError())
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

                    {grossPrice() !== null && (
                        <div className="d-flex gap-3 align-items-start">
                            <Select
                                label="Discount"
                                help="Optionally reduce the price. Choose Percent to take off a share of it, or Fixed amount to take off a set sum."
                                value={discountType}
                                onChange={(e) => setDiscountType(e.target.value as 'percent' | 'fixed')}
                            >
                                <option value="percent">Percent (%)</option>
                                <option value="fixed">Fixed amount</option>
                            </Select>
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                label="Discount value (optional)"
                                help="How much to take off. For a repeating booking it is applied to every booking in the series."
                                placeholder={discountType === 'percent' ? 'e.g. 10' : 'e.g. 50'}
                                value={discountValue}
                                onChange={(e) => setDiscountValue(e.target.value)}
                                error={discountError()}
                            />
                        </div>
                    )}

                    {estimatedPrice() !== null && (
                        <div style={{ fontSize: '14px' }}>
                            {discountAmount() > 0 && (
                                <div style={{ color: 'var(--af-label)' }}>
                                    Price {grossPrice()} − discount {discountAmount()}
                                </div>
                            )}
                            Estimated price: <strong>{estimatedPrice()}</strong>
                        </div>
                    )}

                    <div>
                        <div className="d-block" style={{ fontSize: '12px', fontWeight: 600, color: '#5e6b80', marginBottom: '6px' }}>
                            Payment method
                            <span className="ms-1">
                                <InfoTip label="About Payment method">
                                    How the customer pays. The money is recorded in the account your finance team set for this method (cash, bank or POS).
                                </InfoTip>
                            </span>
                        </div>
                        <div className="d-flex gap-2 flex-wrap">
                            {(
                                [
                                    { value: 'cash', label: 'Cash', icon: <Banknote size={16} /> },
                                    { value: 'bank_transfer', label: 'Bank transfer', icon: <Landmark size={16} /> },
                                    { value: 'pos', label: 'POS', icon: <CreditCard size={16} /> },
                                ] as const
                            ).map((method) => (
                                <Button
                                    key={method.value}
                                    type="button"
                                    variant={paymentMethod === method.value ? 'primary' : 'outline'}
                                    leadingIcon={method.icon}
                                    aria-pressed={paymentMethod === method.value}
                                    onClick={() => setPaymentMethod(method.value)}
                                    style={{ flex: 1 }}
                                >
                                    {method.label}
                                </Button>
                            ))}
                        </div>
                    </div>

                        <Input
                            label={
                                paymentMethod === 'pos' ? 'POS transaction reference (optional)' : paymentMethod === 'cash' ? 'Cash receipt number (optional)' : 'Bank transfer reference (optional)'
                            }
                            help={
                                paymentMethod === 'pos'
                                    ? 'The transaction or receipt number printed on the POS slip. It is saved on the booking and its journal entry so the payment can be matched later.'
                                    : paymentMethod === 'cash'
                                      ? 'The number on the cash receipt or voucher given to the customer. It is saved on the booking and its journal entry so the payment can be matched later.'
                                      : 'The reference number of the bank transfer, as shown on the transfer receipt or bank statement. It is saved on the booking and its journal entry so the payment can be matched later.'
                            }
                            maxLength={100}
                            value={paymentReference}
                            onChange={(e) => setPaymentReference(e.target.value)}
                        />

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
                            {selectedEvent.can_cancel && selectedEvent.status !== 'cancelled' && (
                                <Button variant="outline" onClick={cancelBooking}>
                                    Cancel booking
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
                        <div style={{ fontSize: '14px' }}>
                            Price: {selectedEvent.price}
                            {Number(selectedEvent.discount_amount) > 0 && (
                                <span style={{ color: 'var(--af-label)' }}>
                                    {' '}
                                    (after {selectedEvent.discount_type === 'percent' ? `${Number(selectedEvent.discount_value)}%` : selectedEvent.discount_amount} discount)
                                </span>
                            )}
                            {selectedEvent.payment_method_label && <span style={{ color: 'var(--af-label)' }}> · paid by {selectedEvent.payment_method_label.toLowerCase()}{selectedEvent.payment_reference && ` (ref ${selectedEvent.payment_reference})`}</span>}
                        </div>

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
