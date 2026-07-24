import React, { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

const STATUSES = ['active', 'trial', 'pending_payment', 'past_due', 'cancelled', 'expired', 'suspended', 'vip'];
const BILLING = ['current', 'past_due', 'failed', 'paused', 'comp'];

export default function MembershipDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [m, setM] = useState(null);
  const [draft, setDraft] = useState(null);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  const caps = api.capabilities;

  const load = () => {
    api.getMembership(id).then((res) => { setM(res); setDraft(res); }).catch((e) => setError(e.message));
  };

  useEffect(load, [id]);

  const save = async () => {
    setSaving(true);
    try {
      await api.updateMembership(id, draft);
      setEditing(false);
      load();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const renew = async () => {
    const months = parseInt(prompt('Renew for how many months?', '1') || '0', 10);
    if (!months) return;
    try { await api.renewMembership(id, { months }); load(); } catch (e) { setError(e.message); }
  };

  const cancel = async () => {
    const reason = prompt('Cancellation reason (optional):') || '';
    if (reason === null) return;
    if (!confirm('Cancel this membership? (Logged.)')) return;
    try { await api.cancelMembership(id, { reason }); load(); } catch (e) { setError(e.message); }
  };

  const suspend = async () => {
    const reason = prompt('Suspension reason:') || '';
    if (!reason) return;
    try { await api.suspendMembership(id, { reason }); load(); } catch (e) { setError(e.message); }
  };

  const del = async () => {
    if (!confirm('Delete this membership? (Logged.)')) return;
    try { await api.deleteMembership(id); navigate('/memberships'); } catch (e) { setError(e.message); }
  };

  if (error) return <div className="g2a-error">{error}</div>;
  if (!m) return <div className="g2a-loading">Loading membership…</div>;

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/memberships" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All memberships</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>{m.plan_name}</h2>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <StatusBadge value={m.status} />
            <StatusBadge value={m.billing_status} />
            <span className="g2a-badge">{m.access_level}</span>
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {caps.edit_memberships && !editing && (
            <>
              <button className="g2a-btn primary" onClick={renew}>Renew</button>
              {m.status !== 'cancelled' && <button className="g2a-btn" onClick={suspend}>Suspend</button>}
              {!['cancelled', 'expired'].includes(m.status) && <button className="g2a-btn" onClick={cancel}>Cancel</button>}
              <button className="g2a-btn" onClick={() => setEditing(true)}>Edit</button>
              <button className="g2a-btn danger" onClick={del}>Delete</button>
            </>
          )}
        </div>
      </div>

      <div className="g2a-customer-layout">
        <aside className="g2a-card">
          <h4 className="g2a-section-title">Customer</h4>
          {m.customer ? (
            <>
              <Link to={`/customers/${m.customer.id}`} style={{ color: 'var(--gold)', textDecoration: 'none', fontSize: 16, fontWeight: 600 }}>
                {m.customer.first_name} {m.customer.last_name}
              </Link>
              <dl className="g2a-detail-list" style={{ marginTop: 8 }}>
                <div><dt>Email</dt><dd>{m.customer.email || '—'}</dd></div>
                <div><dt>Phone</dt><dd>{m.customer.phone || '—'}</dd></div>
                <div><dt>Waiver</dt><dd><StatusBadge value={m.customer.waiver_status} /></dd></div>
              </dl>
            </>
          ) : <p style={{ color: 'var(--text-secondary)' }}>Customer #{m.customer_id} not found.</p>}
        </aside>

        <section style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {editing ? (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Edit Membership</h4>
              <div className="g2a-field"><label>Plan name</label><input className="g2a-input" value={draft.plan_name} onChange={(e) => setDraft({ ...draft, plan_name: e.target.value })} /></div>
              <div className="g2a-form-row">
                <div className="g2a-field">
                  <label>Status</label>
                  <select className="g2a-select" value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value })}>
                    {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
                  </select>
                </div>
                <div className="g2a-field">
                  <label>Billing</label>
                  <select className="g2a-select" value={draft.billing_status} onChange={(e) => setDraft({ ...draft, billing_status: e.target.value })}>
                    {BILLING.map((b) => <option key={b} value={b}>{b.replace(/_/g, ' ')}</option>)}
                  </select>
                </div>
              </div>
              <div className="g2a-form-row">
                <div className="g2a-field"><label>Start date</label><input className="g2a-input" type="date" value={draft.start_date || ''} onChange={(e) => setDraft({ ...draft, start_date: e.target.value })} /></div>
                <div className="g2a-field"><label>Renewal date</label><input className="g2a-input" type="date" value={draft.renewal_date || ''} onChange={(e) => setDraft({ ...draft, renewal_date: e.target.value })} /></div>
              </div>
              <div className="g2a-field">
                <label>Monthly amount ($) — used in MRR calculations</label>
                <input className="g2a-input" type="number" step="0.01" min="0" value={draft.monthly_amount || 0} onChange={(e) => setDraft({ ...draft, monthly_amount: e.target.value })} />
              </div>
              <div className="g2a-field"><label>Notes</label><textarea className="g2a-textarea" value={draft.notes || ''} onChange={(e) => setDraft({ ...draft, notes: e.target.value })} /></div>
              <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <button className="g2a-btn" onClick={() => { setEditing(false); setDraft(m); }}>Cancel</button>
                <button className="g2a-btn primary" disabled={saving} onClick={save}>{saving ? 'Saving…' : 'Save'}</button>
              </div>
            </div>
          ) : (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Plan</h4>
              <dl className="g2a-detail-list">
                <div><dt>Start</dt><dd>{m.start_date || '—'}</dd></div>
                <div><dt>Renews</dt><dd>{m.renewal_date || '—'}</dd></div>
                <div><dt>Monthly amount</dt><dd>${parseFloat(m.monthly_amount || 0).toFixed(2)}</dd></div>
                {m.cancelled_at && <div><dt>Cancelled</dt><dd>{m.cancelled_at}</dd></div>}
                {m.cancellation_reason && <div><dt>Cancel reason</dt><dd>{m.cancellation_reason}</dd></div>}
                <div><dt>Payment ref</dt><dd>{m.payment_method_ref || '—'}</dd></div>
                <div><dt>Created</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{m.created_at}</dd></div>
                <div><dt>Updated</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{m.updated_at}</dd></div>
              </dl>
            </div>
          )}

          {m.notes && !editing && (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Notes</h4>
              <p style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{m.notes}</p>
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
