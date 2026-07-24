import React, { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

const STATUSES = ['new', 'contacted', 'qualified', 'waiting', 'appointment_booked', 'converted', 'lost', 'not_a_fit', 'archived'];

export default function LeadDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [lead, setLead] = useState(null);
  const [activities, setActivities] = useState([]);
  const [error, setError] = useState(null);
  const [draft, setDraft] = useState(null);
  const [saving, setSaving] = useState(false);
  const [activityDraft, setActivityDraft] = useState({ activity_type: 'note', summary: '', body: '' });

  const caps = api.capabilities;

  const load = () => {
    api
      .leadActivity(id)
      .then((res) => {
        setLead(res.lead);
        setDraft(res.lead);
        setActivities(res.activities || []);
      })
      .catch((e) => setError(e.message));
  };

  useEffect(load, [id]);

  const save = async () => {
    setSaving(true);
    try {
      const updated = await api.updateLead(id, draft);
      setLead(updated);
      load();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const convert = async () => {
    if (!confirm('Convert this lead into a customer?')) return;
    try {
      const res = await api.convertLead(id);
      navigate(`/customers/${res.customer.id}`);
    } catch (e) {
      setError(e.message);
    }
  };

  const addActivity = async () => {
    if (!activityDraft.summary.trim()) return;
    try {
      await api.addLeadActivity(id, activityDraft);
      setActivityDraft({ activity_type: 'note', summary: '', body: '' });
      load();
    } catch (e) {
      setError(e.message);
    }
  };

  if (error) return <div className="g2a-error">{error}</div>;
  if (!lead) return <div className="g2a-loading">Loading lead…</div>;

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/leads" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All leads</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>{lead.name}</h2>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <StatusBadge value={lead.status} />
            <span className="g2a-badge">{lead.interest_type.replace(/_/g, ' ')}</span>
            {lead.source && <span className="g2a-badge">src: {lead.source}</span>}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {caps.edit_leads && lead.status !== 'converted' && (
            <button className="g2a-btn primary" onClick={convert}>Convert to Customer</button>
          )}
          {lead.customer_id && (
            <Link className="g2a-btn" to={`/customers/${lead.customer_id}`}>View Customer →</Link>
          )}
        </div>
      </div>

      <div className="g2a-customer-layout">
        <aside style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div className="g2a-card">
            <h4 className="g2a-section-title">Contact</h4>
            <dl className="g2a-detail-list">
              <div><dt>Email</dt><dd>{lead.email || '—'}</dd></div>
              <div><dt>Phone</dt><dd>{lead.phone || '—'}</dd></div>
              <div><dt>Score</dt><dd>{lead.lead_score}</dd></div>
              <div><dt>Created</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{lead.created_at}</dd></div>
            </dl>
          </div>

          {caps.edit_leads && (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Update</h4>
              <div className="g2a-field">
                <label>Status</label>
                <select className="g2a-select" value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value })}>
                  {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
                </select>
              </div>
              <div className="g2a-field">
                <label>Next follow-up</label>
                <input className="g2a-input" type="datetime-local" value={draft.next_follow_up_at?.replace(' ', 'T').slice(0, 16) || ''} onChange={(e) => setDraft({ ...draft, next_follow_up_at: e.target.value })} />
              </div>
              <div className="g2a-field">
                <label>Lost reason</label>
                <textarea className="g2a-textarea" value={draft.lost_reason || ''} onChange={(e) => setDraft({ ...draft, lost_reason: e.target.value })} />
              </div>
              <button className="g2a-btn primary" disabled={saving} onClick={save}>{saving ? 'Saving…' : 'Save'}</button>
            </div>
          )}
        </aside>

        <section style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {lead.notes && (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Notes</h4>
              <p style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{lead.notes}</p>
            </div>
          )}

          {caps.edit_leads && (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Log Activity</h4>
              <div className="g2a-form-row">
                <div className="g2a-field">
                  <label>Type</label>
                  <select className="g2a-select" value={activityDraft.activity_type} onChange={(e) => setActivityDraft({ ...activityDraft, activity_type: e.target.value })}>
                    <option value="note">Note</option>
                    <option value="call">Call</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="meeting">Meeting</option>
                  </select>
                </div>
                <div className="g2a-field">
                  <label>Summary</label>
                  <input className="g2a-input" value={activityDraft.summary} onChange={(e) => setActivityDraft({ ...activityDraft, summary: e.target.value })} />
                </div>
              </div>
              <div className="g2a-field">
                <label>Body (optional)</label>
                <textarea className="g2a-textarea" value={activityDraft.body} onChange={(e) => setActivityDraft({ ...activityDraft, body: e.target.value })} />
              </div>
              <button className="g2a-btn primary" onClick={addActivity}>Add activity</button>
            </div>
          )}

          <div className="g2a-card">
            <h4 className="g2a-section-title">Timeline</h4>
            {activities.length === 0 ? (
              <div className="g2a-empty">No activity yet.</div>
            ) : (
              <ul className="g2a-timeline" style={{ listStyle: 'none', padding: 0, margin: 0 }}>
                {activities.map((a) => (
                  <li key={a.id} className="g2a-timeline-item">
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <strong style={{ color: 'var(--gold)' }}>{a.activity_type}</strong>
                      <time>{a.created_at}</time>
                    </div>
                    <div>{a.summary}</div>
                    {a.body && <div style={{ marginTop: 6, color: 'var(--text-secondary)' }}>{a.body}</div>}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}
