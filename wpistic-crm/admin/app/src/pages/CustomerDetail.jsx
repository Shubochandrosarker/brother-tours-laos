import React, { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';
import CustomerTags from '../components/CustomerTags.jsx';
import NotesPanel from '../components/NotesPanel.jsx';
import SensitiveRecordsPanel from '../components/SensitiveRecordsPanel.jsx';
import PurchasesPanel from '../components/PurchasesPanel.jsx';
import MergeCustomersModal from '../components/MergeCustomersModal.jsx';

const CUSTOMER_TYPES = ['walk_in', 'range_visitor', 'member', 'class_student', 'retail', 'ffl_transfer', 'lead', 'corporate', 'vip', 'inactive'];
const STATUSES = ['active', 'archived', 'banned'];
const WAIVER = ['not_submitted', 'submitted', 'verified', 'expired', 'needs_update'];
const MEMBERSHIP = ['none', 'active', 'trial', 'pending_payment', 'past_due', 'cancelled', 'expired', 'suspended', 'vip'];

export default function CustomerDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [events, setEvents] = useState([]);
  const [bookings, setBookings] = useState([]);
  const [waivers, setWaivers] = useState([]);
  const [memberships, setMemberships] = useState([]);
  const [classHistory, setClassHistory] = useState([]);
  const [messages, setMessages] = useState([]);
  const [purchases, setPurchases] = useState([]);
  const [composeOpen, setComposeOpen] = useState(false);
  const [composeChannel, setComposeChannel] = useState('email');
  const [composeDraft, setComposeDraft] = useState({ subject: '', body: '', template_slug: '', send_now: true });
  const [composeSaving, setComposeSaving] = useState(false);
  const [templates, setTemplates] = useState([]);
  const [error, setError] = useState(null);
  const [saving, setSaving] = useState(false);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(null);
  const [mergeOpen, setMergeOpen] = useState(false);

  const caps = api.capabilities;

  const load = () => {
    api
      .customerTimeline(id)
      .then((res) => {
        setData(res.customer);
        setDraft(res.customer);
        setEvents(res.events || []);
        setBookings(res.bookings || []);
        setWaivers(res.waivers || []);
        setMemberships(res.memberships || []);
        setClassHistory(res.class_history || []);
        setMessages(res.messages || []);
        setPurchases(res.purchases || []);
      })
      .catch((e) => setError(e.message));
  };

  useEffect(load, [id]);

  const openCompose = async (channel) => {
    setComposeChannel(channel);
    setComposeDraft({ subject: '', body: '', template_slug: '', send_now: true });
    setComposeOpen(true);
    if (!templates.length) {
      try {
        const res = await api.listTemplates({ channel });
        setTemplates(res.rows || []);
      } catch (e) {
        setError(e.message);
      }
    }
  };

  const applyTemplate = (slug) => {
    const tpl = templates.find((t) => t.slug === slug);
    setComposeDraft({
      ...composeDraft,
      template_slug: slug,
      subject: tpl ? tpl.subject : composeDraft.subject,
      body: tpl ? tpl.body : composeDraft.body,
    });
  };

  const sendMessage = async () => {
    setComposeSaving(true);
    try {
      const fn = composeChannel === 'email' ? api.sendEmail : api.sendSms;
      await fn({
        customer_id: parseInt(id, 10),
        subject: composeDraft.subject,
        body: composeDraft.body,
        template_slug: composeDraft.template_slug || undefined,
        send_now: composeDraft.send_now,
      });
      setComposeOpen(false);
      load();
    } catch (e) {
      setError(e.message);
    } finally {
      setComposeSaving(false);
    }
  };

  const toggleOptIn = async (key) => {
    try {
      const next = await api.updateCustomer(id, { [key]: !data[key] });
      setData(next);
    } catch (e) {
      setError(e.message);
    }
  };

  const save = async () => {
    setSaving(true);
    try {
      const updated = await api.updateCustomer(id, draft);
      setData(updated);
      setEditing(false);
      load();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const del = async () => {
    if (!confirm('Delete this customer? This action is logged.')) return;
    try {
      await api.deleteCustomer(id);
      navigate('/customers');
    } catch (e) {
      setError(e.message);
    }
  };

  if (error) return <div className="g2a-error">{error}</div>;
  if (!data) return <div className="g2a-loading">Loading customer…</div>;

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/customers" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All customers</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22, color: 'var(--text)' }}>
            {data.first_name} {data.last_name}
          </h2>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <StatusBadge value={data.status} />
            <span className="g2a-badge">{data.customer_type.replace(/_/g, ' ')}</span>
            <StatusBadge value={data.waiver_status} />
            <StatusBadge value={data.membership_status} />
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {caps.send_email && (
            <button className="g2a-btn" onClick={() => openCompose('email')}>✉ Email</button>
          )}
          {caps.send_sms && (
            <button className="g2a-btn" onClick={() => openCompose('sms')}>💬 SMS</button>
          )}
          {caps.edit_customers && !editing && (
            <button className="g2a-btn" onClick={() => setEditing(true)}>Edit</button>
          )}
          {caps.delete_customers && (
            <button className="g2a-btn" onClick={() => setMergeOpen(true)}>Merge / Dedupe</button>
          )}
          {caps.delete_customers && (
            <button className="g2a-btn danger" onClick={del}>Delete</button>
          )}
        </div>
      </div>

      <div className="g2a-customer-layout">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <aside className="g2a-card">
          <h4 className="g2a-section-title">Contact</h4>
          <dl className="g2a-detail-list">
            <div><dt>Email</dt><dd>{data.email || '—'}</dd></div>
            <div><dt>Phone</dt><dd>{data.phone || '—'}</dd></div>
            <div><dt>Source</dt><dd>{data.source || '—'}</dd></div>
            <div><dt>Preferred contact</dt><dd>{data.preferred_contact}</dd></div>
            {data.date_of_birth && <div><dt>DOB</dt><dd>{data.date_of_birth}</dd></div>}
            <div><dt>Created</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{data.created_at}</dd></div>
            <div><dt>Last activity</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{data.last_activity_at || '—'}</dd></div>
          </dl>

          <h4 className="g2a-section-title" style={{ marginTop: 18 }}>Communication Opt-In</h4>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13 }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: caps.edit_customers ? 'pointer' : 'default' }}>
              <input
                type="checkbox"
                disabled={!caps.edit_customers}
                checked={!!data.email_opt_in}
                onChange={() => toggleOptIn('email_opt_in')}
              />
              Email
              {data.email_opt_out_at && !data.email_opt_in && (
                <span style={{ fontSize: 10, color: 'var(--text-secondary)' }}>opted out {data.email_opt_out_at.slice(0, 10)}</span>
              )}
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: caps.edit_customers ? 'pointer' : 'default' }}>
              <input
                type="checkbox"
                disabled={!caps.edit_customers}
                checked={!!data.sms_opt_in}
                onChange={() => toggleOptIn('sms_opt_in')}
              />
              SMS
              {data.sms_opt_out_at && !data.sms_opt_in && (
                <span style={{ fontSize: 10, color: 'var(--text-secondary)' }}>opted out {data.sms_opt_out_at.slice(0, 10)}</span>
              )}
            </label>
          </div>
        </aside>

        <CustomerTags customerId={id} />
        </div>

        <section style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {editing ? (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Edit Customer</h4>
              <div className="g2a-form-row">
                <div className="g2a-field"><label>First name</label><input className="g2a-input" value={draft.first_name || ''} onChange={(e) => setDraft({ ...draft, first_name: e.target.value })} /></div>
                <div className="g2a-field"><label>Last name</label><input className="g2a-input" value={draft.last_name || ''} onChange={(e) => setDraft({ ...draft, last_name: e.target.value })} /></div>
              </div>
              <div className="g2a-form-row">
                <div className="g2a-field"><label>Email</label><input className="g2a-input" value={draft.email || ''} onChange={(e) => setDraft({ ...draft, email: e.target.value })} /></div>
                <div className="g2a-field"><label>Phone</label><input className="g2a-input" value={draft.phone || ''} onChange={(e) => setDraft({ ...draft, phone: e.target.value })} /></div>
              </div>
              <div className="g2a-form-row">
                <div className="g2a-field">
                  <label>Type</label>
                  <select className="g2a-select" value={draft.customer_type} onChange={(e) => setDraft({ ...draft, customer_type: e.target.value })}>
                    {CUSTOMER_TYPES.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
                  </select>
                </div>
                <div className="g2a-field">
                  <label>Status</label>
                  <select className="g2a-select" value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value })}>
                    {STATUSES.map((t) => <option key={t} value={t}>{t}</option>)}
                  </select>
                </div>
              </div>
              <div className="g2a-form-row">
                <div className="g2a-field">
                  <label>Waiver</label>
                  <select className="g2a-select" value={draft.waiver_status} onChange={(e) => setDraft({ ...draft, waiver_status: e.target.value })}>
                    {WAIVER.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
                  </select>
                </div>
                <div className="g2a-field">
                  <label>Membership</label>
                  <select className="g2a-select" value={draft.membership_status} onChange={(e) => setDraft({ ...draft, membership_status: e.target.value })}>
                    {MEMBERSHIP.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
                  </select>
                </div>
              </div>
              <div className="g2a-field">
                <label>Notes</label>
                <textarea className="g2a-textarea" value={draft.notes_summary || ''} onChange={(e) => setDraft({ ...draft, notes_summary: e.target.value })} />
              </div>
              <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <button className="g2a-btn" onClick={() => { setEditing(false); setDraft(data); }}>Cancel</button>
                <button className="g2a-btn primary" disabled={saving} onClick={save}>{saving ? 'Saving…' : 'Save'}</button>
              </div>
            </div>
          ) : (
            data.notes_summary && (
              <div className="g2a-card">
                <h4 className="g2a-section-title">Notes</h4>
                <p style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{data.notes_summary}</p>
              </div>
            )
          )}

          <div className="g2a-card">
            <div className="g2a-card-header">
              <h3>Bookings</h3>
            </div>
            {bookings.length === 0 ? (
              <div className="g2a-empty">No bookings on file.</div>
            ) : (
              <table className="g2a-table">
                <thead><tr><th>When</th><th>Type</th><th>Resource</th><th>Status</th></tr></thead>
                <tbody>
                  {bookings.map((b) => (
                    <tr key={b.id}>
                      <td><Link to={`/bookings/${b.id}`}>{b.start_time}</Link></td>
                      <td><span className="g2a-badge">{b.booking_type.replace(/_/g, ' ')}</span></td>
                      <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{b.resource_type !== 'none' ? `${b.resource_type} ${b.resource_id || ''}` : '—'}</td>
                      <td><StatusBadge value={b.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <div className="g2a-card">
            <div className="g2a-card-header">
              <h3>Memberships</h3>
            </div>
            {memberships.length === 0 ? (
              <div className="g2a-empty">No memberships on file.</div>
            ) : (
              <table className="g2a-table">
                <thead><tr><th>Plan</th><th>Status</th><th>Billing</th><th>Renews</th></tr></thead>
                <tbody>
                  {memberships.map((m) => (
                    <tr key={m.id}>
                      <td><Link to={`/memberships/${m.id}`}>{m.plan_name}</Link></td>
                      <td><StatusBadge value={m.status} /></td>
                      <td><StatusBadge value={m.billing_status} /></td>
                      <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{m.renewal_date || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <div className="g2a-card">
            <div className="g2a-card-header">
              <h3>Class History</h3>
            </div>
            {classHistory.length === 0 ? (
              <div className="g2a-empty">No classes attended.</div>
            ) : (
              <table className="g2a-table">
                <thead><tr><th>Class</th><th>When</th><th>Status</th><th>Payment</th></tr></thead>
                <tbody>
                  {classHistory.map((s) => (
                    <tr key={s.id}>
                      <td><Link to={`/classes/${s.class_id}`}>{s.class_name || `Class #${s.class_id}`}</Link></td>
                      <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{s.class_start_time || '—'}</td>
                      <td><StatusBadge value={s.status} /></td>
                      <td><StatusBadge value={s.payment_status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <div className="g2a-card">
            <div className="g2a-card-header">
              <h3>Waivers</h3>
            </div>
            {waivers.length === 0 ? (
              <div className="g2a-empty">No waiver records.</div>
            ) : (
              <table className="g2a-table">
                <thead><tr><th>Type</th><th>Status</th><th>Signed</th><th>Expires</th><th></th></tr></thead>
                <tbody>
                  {waivers.map((w) => (
                    <tr key={w.id}>
                      <td><Link to={`/waivers/${w.id}`}>{w.waiver_type}</Link></td>
                      <td><StatusBadge value={w.status} /></td>
                      <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{w.signed_at}</td>
                      <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{w.expires_at || 'No expiry'}</td>
                      <td>{w.pdf_url && <a href={w.pdf_url} target="_blank" rel="noreferrer" className="g2a-link-button">View</a>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <div className="g2a-card">
            <div className="g2a-card-header">
              <h3>Messages</h3>
            </div>
            {messages.length === 0 ? (
              <div className="g2a-empty">No messages sent or queued.</div>
            ) : (
              <table className="g2a-table">
                <thead><tr><th>When</th><th>Channel</th><th>Subject</th><th>Status</th></tr></thead>
                <tbody>
                  {messages.map((m) => (
                    <tr key={m.id}>
                      <td style={{ fontSize: 12, color: 'var(--text-secondary)', whiteSpace: 'nowrap' }}>{m.sent_at || m.created_at}</td>
                      <td><span className="g2a-badge">{m.channel}</span></td>
                      <td><Link to={`/messages/${m.id}`}>{m.rendered_subject || m.subject || '(no subject)'}</Link></td>
                      <td><StatusBadge value={m.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <PurchasesPanel customerId={parseInt(id, 10)} initialRows={purchases} />

          <NotesPanel relatedType="customer" relatedId={parseInt(id, 10)} canEdit={!!caps.edit_customers} />

          <SensitiveRecordsPanel customerId={parseInt(id, 10)} />

          <div className="g2a-card">
            <h4 className="g2a-section-title">Activity Timeline</h4>
            {events.length === 0 ? (
              <div className="g2a-empty">No audit events recorded yet.</div>
            ) : (
              <ul className="g2a-timeline" style={{ listStyle: 'none', padding: 0, margin: 0 }}>
                {events.map((e) => (
                  <li key={e.id} className="g2a-timeline-item">
                    <strong style={{ color: 'var(--gold)' }}>{e.action}</strong>
                    <time style={{ float: 'right' }}>{e.created_at}</time>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </section>
      </div>

      {mergeOpen && (
        <MergeCustomersModal
          anchor={data}
          onClose={() => setMergeOpen(false)}
          onMerged={(res) => {
            // If the anchor (current page) was deleted in the merge, redirect to the survivor.
            if (res && res.source_id === data.id && res.target_id !== data.id) {
              navigate(`/customers/${res.target_id}`);
            } else {
              load();
            }
          }}
        />
      )}

      {composeOpen && (
        <Modal
          title={`Send ${composeChannel === 'email' ? 'Email' : 'SMS'} to ${data.first_name} ${data.last_name}`}
          onClose={() => setComposeOpen(false)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setComposeOpen(false)}>Cancel</button>
              <button className="g2a-btn primary" disabled={composeSaving || !composeDraft.body.trim()} onClick={sendMessage}>
                {composeSaving ? 'Sending…' : (composeDraft.send_now ? 'Send now' : 'Queue')}
              </button>
            </>
          }
        >
          {composeChannel === 'email' && !data.email_opt_in && (
            <div className="g2a-error">Customer has opted out of email. The message will be skipped at send time.</div>
          )}
          {composeChannel === 'sms' && !data.sms_opt_in && (
            <div className="g2a-error">Customer has opted out of SMS. The message will be skipped at send time.</div>
          )}

          <div className="g2a-field">
            <label>Template (optional)</label>
            <select className="g2a-select" value={composeDraft.template_slug} onChange={(e) => applyTemplate(e.target.value)}>
              <option value="">— start from scratch —</option>
              {templates.filter((t) => t.channel === composeChannel).map((t) => (
                <option key={t.id} value={t.slug}>{t.slug}</option>
              ))}
            </select>
          </div>
          {composeChannel === 'email' && (
            <div className="g2a-field">
              <label>Subject</label>
              <input className="g2a-input" value={composeDraft.subject} onChange={(e) => setComposeDraft({ ...composeDraft, subject: e.target.value })} />
            </div>
          )}
          <div className="g2a-field">
            <label>Body</label>
            <textarea className="g2a-textarea" style={{ minHeight: 160 }} value={composeDraft.body} onChange={(e) => setComposeDraft({ ...composeDraft, body: e.target.value })} placeholder="Use {{first_name}} for variables." />
          </div>
          <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <input type="checkbox" id="sn" checked={composeDraft.send_now} onChange={(e) => setComposeDraft({ ...composeDraft, send_now: e.target.checked })} />
            <label htmlFor="sn" style={{ margin: 0 }}>Send immediately (otherwise queues for the next dispatcher run)</label>
          </div>
        </Modal>
      )}
    </div>
  );
}
