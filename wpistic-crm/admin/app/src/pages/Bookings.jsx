import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';
import Pagination from '../components/Pagination.jsx';

const TYPES = ['range_lane', 'class', 'private_training', 'consultation', 'event', 'member_only', 'offline'];
const STATUSES = ['pending', 'confirmed', 'checked_in', 'completed', 'no_show', 'cancelled'];
const RESOURCES = ['lane', 'instructor', 'room', 'class', 'none'];

function localDT(date = new Date()) {
  const z = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${z(date.getMonth() + 1)}-${z(date.getDate())}T${z(date.getHours())}:${z(date.getMinutes())}`;
}

const EMPTY = () => {
  const start = new Date(Date.now() + 60 * 60 * 1000);
  const end = new Date(Date.now() + 2 * 60 * 60 * 1000);
  return {
    customer_id: '',
    booking_type: 'range_lane',
    resource_type: 'lane',
    resource_id: 1,
    start_time: localDT(start),
    end_time: localDT(end),
    status: 'confirmed',
    amount: 0,
    notes: '',
  };
};

export default function Bookings() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [todayOnly, setTodayOnly] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);

  const caps = api.capabilities;

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listBookings({
        page,
        per_page: 25,
        status: statusFilter,
        booking_type: typeFilter,
        today: todayOnly ? 1 : '',
      })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, statusFilter, typeFilter, todayOnly]);

  useEffect(() => { refresh(); }, [refresh]);

  const save = async () => {
    setSaving(true);
    try {
      await api.createBooking({
        ...draft,
        customer_id: parseInt(draft.customer_id, 10),
        resource_id: parseInt(draft.resource_id || 0, 10),
      });
      setModal(null);
      setDraft(EMPTY());
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const checkIn = async (booking) => {
    try {
      await api.bookingCheckIn(booking.id);
      refresh();
    } catch (e) {
      if (e.data?.code === 'g2a_crm_waiver_required') {
        if (caps.verify_waivers && confirm('No valid waiver on file. Override and check in anyway? (Recorded in audit log)')) {
          try {
            await api.bookingCheckIn(booking.id, { skip_waiver_check: true });
            refresh();
            return;
          } catch (e2) { setError(e2.message); return; }
        }
        setError('Customer needs a signed waiver before check-in. Direct them to the kiosk or have a manager override.');
        return;
      }
      setError(e.message);
    }
  };

  return (
    <div>
      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <select className="g2a-select" value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} style={{ width: 160 }}>
            <option value="">All statuses</option>
            {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </select>
          <select className="g2a-select" value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }} style={{ width: 160 }}>
            <option value="">All types</option>
            {TYPES.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
          </select>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={todayOnly} onChange={(e) => { setTodayOnly(e.target.checked); setPage(1); }} />
            Today only
          </label>
        </div>
        {caps.edit_bookings && <button className="g2a-btn primary" onClick={() => { setDraft(EMPTY()); setModal('create'); }}>+ New Booking</button>}
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No bookings found.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>When</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Resource</th>
                <th>Status</th>
                <th>Waiver</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((b) => (
                <tr key={b.id}>
                  <td>
                    <div><Link to={`/bookings/${b.id}`}>{b.start_time}</Link></div>
                    <div style={{ fontSize: 11, color: 'var(--text-secondary)' }}>ends {b.end_time?.slice(11, 16)}</div>
                  </td>
                  <td>
                    {b.customer ? (
                      <Link to={`/customers/${b.customer_id}`}>{b.customer.first_name} {b.customer.last_name}</Link>
                    ) : (
                      <span style={{ color: 'var(--text-secondary)' }}>#{b.customer_id}</span>
                    )}
                  </td>
                  <td><span className="g2a-badge">{b.booking_type.replace(/_/g, ' ')}</span></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>
                    {b.resource_type !== 'none' ? `${b.resource_type} ${b.resource_id || ''}` : '—'}
                  </td>
                  <td><StatusBadge value={b.status} /></td>
                  <td>{b.customer ? <StatusBadge value={b.customer.waiver_status} /> : '—'}</td>
                  <td>
                    <div className="g2a-row-actions">
                      {caps.edit_bookings && (b.status === 'pending' || b.status === 'confirmed') && (
                        <button className="g2a-btn" onClick={() => checkIn(b)}>Check in</button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={meta} onPage={setPage} />

      {modal === 'create' && (
        <Modal
          title="New Booking"
          onClose={() => setModal(null)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setModal(null)}>Cancel</button>
              <button className="g2a-btn primary" disabled={saving || !draft.customer_id} onClick={save}>{saving ? 'Saving…' : 'Create'}</button>
            </>
          }
        >
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Customer ID</label>
              <input className="g2a-input" type="number" value={draft.customer_id} onChange={(e) => setDraft({ ...draft, customer_id: e.target.value })} placeholder="Look up on Customers page first" />
            </div>
            <div className="g2a-field">
              <label>Type</label>
              <select className="g2a-select" value={draft.booking_type} onChange={(e) => setDraft({ ...draft, booking_type: e.target.value })}>
                {TYPES.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Resource type</label>
              <select className="g2a-select" value={draft.resource_type} onChange={(e) => setDraft({ ...draft, resource_type: e.target.value })}>
                {RESOURCES.map((r) => <option key={r} value={r}>{r}</option>)}
              </select>
            </div>
            <div className="g2a-field">
              <label>Resource # (e.g. lane number)</label>
              <input className="g2a-input" type="number" value={draft.resource_id} onChange={(e) => setDraft({ ...draft, resource_id: e.target.value })} />
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Start</label>
              <input className="g2a-input" type="datetime-local" value={draft.start_time} onChange={(e) => setDraft({ ...draft, start_time: e.target.value })} />
            </div>
            <div className="g2a-field">
              <label>End</label>
              <input className="g2a-input" type="datetime-local" value={draft.end_time} onChange={(e) => setDraft({ ...draft, end_time: e.target.value })} />
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Amount</label>
              <input className="g2a-input" type="number" step="0.01" value={draft.amount} onChange={(e) => setDraft({ ...draft, amount: e.target.value })} />
            </div>
            <div className="g2a-field">
              <label>Status</label>
              <select className="g2a-select" value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value })}>
                {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
          </div>
          <div className="g2a-field">
            <label>Notes</label>
            <textarea className="g2a-textarea" value={draft.notes} onChange={(e) => setDraft({ ...draft, notes: e.target.value })} />
          </div>
        </Modal>
      )}
    </div>
  );
}
