import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';
import Pagination from '../components/Pagination.jsx';

const STATUSES = ['active', 'trial', 'pending_payment', 'past_due', 'cancelled', 'expired', 'suspended', 'vip'];
const ACCESS = ['standard', 'premium', 'family', 'corporate', 'lifetime'];
const BILLING = ['current', 'past_due', 'failed', 'paused', 'comp'];

function today() { return new Date().toISOString().slice(0, 10); }
function plusMonths(months) {
  const d = new Date();
  d.setMonth(d.getMonth() + months);
  return d.toISOString().slice(0, 10);
}

const EMPTY = () => ({
  customer_id: '',
  plan_name: 'Standard Range Membership',
  access_level: 'standard',
  status: 'active',
  billing_status: 'current',
  monthly_amount: 0,
  start_date: today(),
  renewal_date: plusMonths(1),
  notes: '',
});

export default function Memberships() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [renewsSoon, setRenewsSoon] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);

  const caps = api.capabilities;

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listMemberships({
        page,
        per_page: 25,
        status: statusFilter,
        renews_within_days: renewsSoon ? 14 : '',
      })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, statusFilter, renewsSoon]);

  useEffect(() => { refresh(); }, [refresh]);

  const save = async () => {
    setSaving(true);
    try {
      await api.createMembership({ ...draft, customer_id: parseInt(draft.customer_id, 10), monthly_amount: parseFloat(draft.monthly_amount || 0) });
      setModal(null);
      setDraft(EMPTY());
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <select className="g2a-select" value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} style={{ width: 180 }}>
            <option value="">All statuses</option>
            {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </select>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={renewsSoon} onChange={(e) => { setRenewsSoon(e.target.checked); setPage(1); }} />
            Renewing within 14 days
          </label>
        </div>
        {caps.edit_memberships && <button className="g2a-btn primary" onClick={() => { setDraft(EMPTY()); setModal('create'); }}>+ New Membership</button>}
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No memberships found.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Plan</th>
                <th>Access</th>
                <th>Status</th>
                <th>Billing</th>
                <th>Renews</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((m) => (
                <tr key={m.id}>
                  <td>
                    {m.customer ? (
                      <Link to={`/customers/${m.customer_id}`}>{m.customer.first_name} {m.customer.last_name}</Link>
                    ) : (
                      <span style={{ color: 'var(--text-secondary)' }}>#{m.customer_id}</span>
                    )}
                  </td>
                  <td><Link to={`/memberships/${m.id}`}>{m.plan_name}</Link></td>
                  <td><span className="g2a-badge">{m.access_level}</span></td>
                  <td><StatusBadge value={m.status} /></td>
                  <td><StatusBadge value={m.billing_status} /></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{m.renewal_date || '—'}</td>
                  <td><Link className="g2a-btn" to={`/memberships/${m.id}`}>Open</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={meta} onPage={setPage} />

      {modal === 'create' && (
        <Modal
          title="New Membership"
          onClose={() => setModal(null)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setModal(null)}>Cancel</button>
              <button className="g2a-btn primary" disabled={saving || !draft.customer_id || !draft.plan_name} onClick={save}>{saving ? 'Saving…' : 'Create'}</button>
            </>
          }
        >
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Customer ID</label>
              <input className="g2a-input" type="number" value={draft.customer_id} onChange={(e) => setDraft({ ...draft, customer_id: e.target.value })} placeholder="Look up on Customers" />
            </div>
            <div className="g2a-field">
              <label>Plan name</label>
              <input className="g2a-input" value={draft.plan_name} onChange={(e) => setDraft({ ...draft, plan_name: e.target.value })} />
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Access level</label>
              <select className="g2a-select" value={draft.access_level} onChange={(e) => setDraft({ ...draft, access_level: e.target.value })}>
                {ACCESS.map((a) => <option key={a} value={a}>{a}</option>)}
              </select>
            </div>
            <div className="g2a-field">
              <label>Status</label>
              <select className="g2a-select" value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value })}>
                {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Start date</label>
              <input className="g2a-input" type="date" value={draft.start_date} onChange={(e) => setDraft({ ...draft, start_date: e.target.value })} />
            </div>
            <div className="g2a-field">
              <label>Renewal date</label>
              <input className="g2a-input" type="date" value={draft.renewal_date} onChange={(e) => setDraft({ ...draft, renewal_date: e.target.value })} />
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Billing status</label>
              <select className="g2a-select" value={draft.billing_status} onChange={(e) => setDraft({ ...draft, billing_status: e.target.value })}>
                {BILLING.map((b) => <option key={b} value={b}>{b.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
            <div className="g2a-field">
              <label>Monthly amount ($)</label>
              <input className="g2a-input" type="number" step="0.01" min="0" value={draft.monthly_amount} onChange={(e) => setDraft({ ...draft, monthly_amount: e.target.value })} />
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
