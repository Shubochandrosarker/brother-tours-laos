import React, { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

export default function BookingDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [booking, setBooking] = useState(null);
  const [error, setError] = useState(null);

  const caps = api.capabilities;

  const load = () => {
    api.getBooking(id).then(setBooking).catch((e) => setError(e.message));
  };

  useEffect(load, [id]);

  const action = async (fn, ...args) => {
    try {
      await fn(id, ...args);
      load();
    } catch (e) {
      if (e.data?.code === 'g2a_crm_waiver_required' && caps.verify_waivers) {
        if (confirm('Customer has no valid waiver. Override and check in? (Logged.)')) {
          try { await api.bookingCheckIn(id, { skip_waiver_check: true }); load(); return; } catch (e2) { setError(e2.message); return; }
        }
      }
      setError(e.message);
    }
  };

  const cancel = async () => {
    const reason = prompt('Cancellation reason (optional):') || '';
    if (reason === null) return;
    action(api.bookingCancel, { reason });
  };

  const del = async () => {
    if (!confirm('Delete this booking? This is logged.')) return;
    try {
      await api.deleteBooking(id);
      navigate('/bookings');
    } catch (e) {
      setError(e.message);
    }
  };

  if (error) return <div className="g2a-error">{error}</div>;
  if (!booking) return <div className="g2a-loading">Loading booking…</div>;

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/bookings" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All bookings</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>
            {booking.booking_type.replace(/_/g, ' ')} — {booking.start_time}
          </h2>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <StatusBadge value={booking.status} />
            <StatusBadge value={booking.payment_status} />
            {booking.resource_type !== 'none' && <span className="g2a-badge">{booking.resource_type} {booking.resource_id}</span>}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {caps.edit_bookings && (booking.status === 'pending' || booking.status === 'confirmed') && (
            <button className="g2a-btn primary" onClick={() => action(api.bookingCheckIn)}>Check in</button>
          )}
          {caps.edit_bookings && booking.status === 'checked_in' && (
            <button className="g2a-btn primary" onClick={() => action(api.bookingComplete)}>Complete</button>
          )}
          {caps.cancel_bookings && !['cancelled', 'refunded'].includes(booking.status) && (
            <button className="g2a-btn" onClick={cancel}>Cancel</button>
          )}
          {caps.edit_bookings && <button className="g2a-btn danger" onClick={del}>Delete</button>}
        </div>
      </div>

      <div className="g2a-customer-layout">
        <aside className="g2a-card">
          <h4 className="g2a-section-title">Customer</h4>
          {booking.customer ? (
            <>
              <div style={{ marginBottom: 8 }}>
                <Link to={`/customers/${booking.customer.id}`} style={{ color: 'var(--gold)', textDecoration: 'none', fontSize: 16, fontWeight: 600 }}>
                  {booking.customer.first_name} {booking.customer.last_name}
                </Link>
              </div>
              <dl className="g2a-detail-list">
                <div><dt>Email</dt><dd>{booking.customer.email || '—'}</dd></div>
                <div><dt>Phone</dt><dd>{booking.customer.phone || '—'}</dd></div>
                <div><dt>Waiver</dt><dd><StatusBadge value={booking.customer.waiver_status} /></dd></div>
              </dl>
            </>
          ) : (
            <p style={{ color: 'var(--text-secondary)' }}>Customer #{booking.customer_id} not found.</p>
          )}
        </aside>

        <section style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div className="g2a-card">
            <h4 className="g2a-section-title">Booking</h4>
            <dl className="g2a-detail-list">
              <div><dt>Type</dt><dd>{booking.booking_type.replace(/_/g, ' ')}</dd></div>
              <div><dt>Start</dt><dd>{booking.start_time}</dd></div>
              <div><dt>End</dt><dd>{booking.end_time}</dd></div>
              <div><dt>Amount</dt><dd>${parseFloat(booking.amount).toFixed(2)}</dd></div>
              <div><dt>Payment</dt><dd><StatusBadge value={booking.payment_status} /></dd></div>
              {booking.staff_id ? <div><dt>Staff</dt><dd>#{booking.staff_id}</dd></div> : null}
              <div><dt>Created</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{booking.created_at}</dd></div>
              <div><dt>Updated</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{booking.updated_at}</dd></div>
            </dl>
          </div>

          {booking.notes && (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Notes</h4>
              <p style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{booking.notes}</p>
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
