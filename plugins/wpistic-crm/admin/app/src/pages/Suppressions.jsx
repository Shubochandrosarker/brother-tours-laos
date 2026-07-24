import React, { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';
import Pagination from '../components/Pagination.jsx';

const CHANNELS = ['', 'email', 'sms'];
const SOURCES = ['', 'manual', 'hard_bounce', 'complaint', 'unsubscribe', 'send_failure'];

const EMPTY = { channel: 'email', address: '', reason: '' };

export default function Suppressions() {
  const caps = api.capabilities;
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [channelFilter, setChannelFilter] = useState('');
  const [sourceFilter, setSourceFilter] = useState('');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);

  const refresh = useCallback(() => {
    setLoading(true);
    api.listSuppressions({ page, per_page: 50, channel: channelFilter, source: sourceFilter, search })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, channelFilter, sourceFilter, search]);

  useEffect(() => { refresh(); }, [refresh]);

  if (!caps.manage_settings) {
    return <div className="g2a-error">You do not have permission to manage the suppression list.</div>;
  }

  const save = async () => {
    setSaving(true);
    setError(null);
    try {
      await api.createSuppression(draft);
      setModal(null);
      setDraft(EMPTY);
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (row) => {
    if (!window.confirm(`Remove ${row.address} (${row.channel}) from the suppression list? Future messages to this address will resume.`)) {
      return;
    }
    try {
      await api.deleteSuppression(row.id);
      refresh();
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <h2 style={{ margin: 0, fontSize: 22, color: 'var(--text)' }}>Suppressions</h2>
          <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginTop: 4 }}>
            Email + SMS addresses we must not message. Hard bounces, complaints, unsubscribes, and permanent send failures land here automatically. Remove a row only if you've verified the issue is resolved.
          </div>
        </div>
        <button className="g2a-btn primary" onClick={() => { setDraft(EMPTY); setModal('create'); }}>+ Add manually</button>
      </div>

      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <input className="g2a-input" placeholder="Search address…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} style={{ width: 260 }} />
          <select className="g2a-select" value={channelFilter} onChange={(e) => { setChannelFilter(e.target.value); setPage(1); }} style={{ width: 140 }}>
            {CHANNELS.map((c) => <option key={c} value={c}>{c || 'All channels'}</option>)}
          </select>
          <select className="g2a-select" value={sourceFilter} onChange={(e) => { setSourceFilter(e.target.value); setPage(1); }} style={{ width: 180 }}>
            {SOURCES.map((s) => <option key={s} value={s}>{s ? s.replace(/_/g, ' ') : 'All sources'}</option>)}
          </select>
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No suppressed addresses. That's a good thing.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Channel</th>
                <th>Address</th>
                <th>Source</th>
                <th>Reason</th>
                <th>Added</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((r) => (
                <tr key={r.id}>
                  <td><span className="g2a-badge">{r.channel}</span></td>
                  <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{r.address}</td>
                  <td><StatusBadge value={r.source} /></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.reason || '—'}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.created_at}</td>
                  <td><button className="g2a-btn" onClick={() => remove(r)} style={{ fontSize: 12 }}>Remove</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={meta} onPage={setPage} />

      <div className="g2a-card" style={{ padding: 16, marginTop: 16 }}>
        <h4 className="g2a-section-title" style={{ marginTop: 0 }}>Inbound bounce webhook</h4>
        <div style={{ fontSize: 12, color: 'var(--text-secondary)', lineHeight: 1.6 }}>
          <p>Wire your email/SMS provider's bounce + complaint webhooks through a relay (Zapier, Make, n8n) so bounces flow into this list automatically.</p>
          <p>Endpoint: <code>POST {(api.boot.restRoot || '/wp-json/g2a-crm/v1')}/inbound/bounce</code></p>
          <p>Header: <code>X-G2A-CRM-Bounce-Secret: &lt;value of inbound_bounce_secret in Settings&gt;</code></p>
          <p>Body: <code>{`{ "channel": "email"|"sms", "address": "...", "type": "hard_bounce"|"soft_bounce"|"complaint"|"unsubscribe", "reason": "..." }`}</code></p>
          <p>Set <code>inbound_bounce_secret</code> in the Settings page first — the endpoint is disabled (503) until you do.</p>
        </div>
      </div>

      {modal === 'create' && (
        <Modal
          title="Add to suppression list"
          onClose={() => setModal(null)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setModal(null)}>Cancel</button>
              <button className="g2a-btn primary" onClick={save} disabled={saving || !draft.address}>{saving ? 'Saving…' : 'Suppress'}</button>
            </>
          }
        >
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Channel</label>
              <select className="g2a-select" value={draft.channel} onChange={(e) => setDraft({ ...draft, channel: e.target.value })}>
                <option value="email">email</option>
                <option value="sms">sms</option>
              </select>
            </div>
            <div className="g2a-field" style={{ flex: 2 }}>
              <label>Address</label>
              <input className="g2a-input" value={draft.address} onChange={(e) => setDraft({ ...draft, address: e.target.value })} placeholder={draft.channel === 'email' ? 'someone@example.com' : '+15551234567'} />
            </div>
          </div>
          <div className="g2a-field">
            <label>Reason (optional)</label>
            <input className="g2a-input" value={draft.reason} onChange={(e) => setDraft({ ...draft, reason: e.target.value })} placeholder="e.g. customer requested in person" />
          </div>
        </Modal>
      )}
    </div>
  );
}
