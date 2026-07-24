import React, { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';

const EMPTY = { name: '', event: '*', target_url: '', secret: '', is_active: true };

export default function Webhooks() {
  const caps = api.capabilities;
  const [items, setItems] = useState([]);
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [editing, setEditing] = useState(null);
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);
  const [revealedSecret, setRevealedSecret] = useState(null);
  const [deliveries, setDeliveries] = useState(null);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState(null);
  const [dispatching, setDispatching] = useState(false);

  const refresh = useCallback(() => {
    setLoading(true);
    Promise.all([
      api.listWebhooks({ per_page: 100 }),
      events.length ? Promise.resolve({ events }) : api.webhookEvents(),
    ])
      .then(([res, evs]) => {
        setItems(res.data || []);
        if (!events.length && evs.events) setEvents(evs.events);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { refresh(); }, []);

  if (!caps.manage_webhooks) {
    return <div className="g2a-error">You do not have permission to manage webhooks.</div>;
  }

  const openCreate = () => { setDraft(EMPTY); setRevealedSecret(null); setEditing('new'); setDeliveries(null); setTestResult(null); };
  const openEdit = async (item) => {
    setRevealedSecret(null);
    setEditing(item.id);
    setDraft({ ...item, is_active: !!item.is_active });
    setTestResult(null);
    try {
      const d = await api.webhookDeliveries(item.id, { limit: 25 });
      setDeliveries(d || []);
    } catch (e) {
      setDeliveries([]);
    }
  };
  const close = () => { setEditing(null); setDraft(EMPTY); setRevealedSecret(null); setDeliveries(null); setTestResult(null); };

  const save = async () => {
    setSaving(true);
    setError(null);
    try {
      if (editing === 'new') {
        const created = await api.createWebhook(draft);
        // Capture the one-time-visible secret right after creation.
        setRevealedSecret(created.secret);
        setEditing(created.id);
        setDraft({ ...created, is_active: !!created.is_active });
      } else {
        await api.updateWebhook(editing, draft);
      }
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (id) => {
    if (!confirm('Delete this webhook? Subscribers will stop receiving events immediately.')) return;
    try {
      await api.deleteWebhook(id);
      close();
      refresh();
    } catch (e) {
      setError(e.message);
    }
  };

  const test = async () => {
    if (!editing || editing === 'new') return;
    setTesting(true);
    setTestResult(null);
    try {
      const result = await api.testWebhook(editing);
      setTestResult(result);
      const d = await api.webhookDeliveries(editing, { limit: 25 });
      setDeliveries(d || []);
    } catch (e) {
      setTestResult({ error: e.message });
    } finally {
      setTesting(false);
    }
  };

  const dispatchNow = async () => {
    setDispatching(true);
    try {
      await api.webhookDispatchNow();
      refresh();
      if (editing && editing !== 'new') {
        const d = await api.webhookDeliveries(editing, { limit: 25 });
        setDeliveries(d || []);
      }
    } catch (e) {
      setError(e.message);
    } finally {
      setDispatching(false);
    }
  };

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <h2 style={{ margin: 0, fontSize: 22, color: 'var(--text)' }}>Webhooks</h2>
          <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginTop: 4 }}>
            HMAC-signed outbound events. Subscribe to a specific event or use <code>*</code> for everything.
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          <button className="g2a-btn" onClick={dispatchNow} disabled={dispatching}>{dispatching ? 'Dispatching…' : 'Run dispatcher now'}</button>
          <button className="g2a-btn primary" onClick={openCreate}>+ New webhook</button>
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading webhooks…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No webhooks configured yet. Create one to start streaming events to Zapier, Make, n8n, or any HTTPS endpoint.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Event</th>
                <th>Target</th>
                <th>Active</th>
                <th>Last status</th>
                <th>Last sent</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((w) => (
                <tr key={w.id}>
                  <td style={{ fontWeight: 600 }}>{w.name}</td>
                  <td><span className="g2a-badge">{w.event}</span></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)', maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{w.target_url}</td>
                  <td>{w.is_active ? <StatusBadge value="active" /> : <span className="g2a-badge">paused</span>}</td>
                  <td>{w.last_status ? <StatusBadge value={w.last_status} /> : '—'}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{w.last_sent_at || '—'}</td>
                  <td><button className="g2a-btn" onClick={() => openEdit(w)} style={{ fontSize: 12 }}>Open</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {editing && (
        <Modal
          title={editing === 'new' ? 'New Webhook' : `Webhook #${editing}`}
          onClose={close}
          actions={
            <>
              {editing !== 'new' && (
                <button className="g2a-btn danger" onClick={() => remove(editing)} style={{ marginRight: 'auto' }}>Delete</button>
              )}
              {editing !== 'new' && (
                <button className="g2a-btn" onClick={test} disabled={testing}>{testing ? 'Sending…' : 'Send test'}</button>
              )}
              <button className="g2a-btn" onClick={close}>Close</button>
              <button className="g2a-btn primary" onClick={save} disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
            </>
          }
        >
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Name</label><input className="g2a-input" value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} placeholder="e.g. Zapier production" /></div>
            <div className="g2a-field">
              <label>Event</label>
              <select className="g2a-select" value={draft.event} onChange={(e) => setDraft({ ...draft, event: e.target.value })}>
                <option value="*">* (all events)</option>
                {events.map((ev) => <option key={ev} value={ev}>{ev}</option>)}
              </select>
            </div>
          </div>
          <div className="g2a-field">
            <label>Target URL (HTTPS recommended)</label>
            <input className="g2a-input" value={draft.target_url} onChange={(e) => setDraft({ ...draft, target_url: e.target.value })} placeholder="https://hooks.zapier.com/…" />
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Signing secret</label>
              <input
                className="g2a-input"
                type={revealedSecret ? 'text' : 'password'}
                value={revealedSecret || draft.secret || ''}
                onChange={(e) => setDraft({ ...draft, secret: e.target.value })}
                placeholder={editing === 'new' ? '(auto-generated on save)' : '(stored — replace to rotate)'}
              />
              <small style={{ color: 'var(--text-secondary)', fontSize: 11 }}>
                {revealedSecret
                  ? 'This is the only time the full secret will be shown — copy it now.'
                  : editing === 'new'
                    ? 'Leave blank to auto-generate a 48-character secret.'
                    : 'Existing secret is hidden. Type a new value to rotate.'}
              </small>
            </div>
            <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'flex-end', gap: 8 }}>
              <input id="hook-active" type="checkbox" checked={!!draft.is_active} onChange={(e) => setDraft({ ...draft, is_active: e.target.checked })} />
              <label htmlFor="hook-active" style={{ margin: 0 }}>Active</label>
            </div>
          </div>

          <div style={{ fontSize: 11, color: 'var(--text-secondary)', border: '1px solid var(--border)', padding: 10, borderRadius: 4, marginTop: 10 }}>
            <strong style={{ color: 'var(--text)' }}>Verifying signatures:</strong> recompute <code>HMAC-SHA256(timestamp + "." + raw_body, secret)</code> using the headers
            <code> X-G2A-CRM-Signature</code> and <code>X-G2A-CRM-Timestamp</code>. Reject requests with a timestamp older than 5 minutes to prevent replay.
          </div>

          {testResult && (
            <div className={testResult.error || (testResult.code && (testResult.code < 200 || testResult.code >= 300)) ? 'g2a-error' : 'g2a-empty'} style={{ marginTop: 10 }}>
              <strong>Test result:</strong> {testResult.error || `HTTP ${testResult.code || '?'}`}
              {testResult.body && <pre style={{ marginTop: 6, maxHeight: 120, overflow: 'auto', whiteSpace: 'pre-wrap' }}>{testResult.body}</pre>}
            </div>
          )}

          {editing !== 'new' && deliveries && (
            <div style={{ marginTop: 16 }}>
              <h4 className="g2a-section-title">Recent deliveries</h4>
              {deliveries.length === 0 ? (
                <div className="g2a-empty">No deliveries yet.</div>
              ) : (
                <table className="g2a-table">
                  <thead>
                    <tr>
                      <th>When</th>
                      <th>Event</th>
                      <th>Status</th>
                      <th>Code</th>
                      <th>Attempts</th>
                      <th>Next retry</th>
                    </tr>
                  </thead>
                  <tbody>
                    {deliveries.map((d) => (
                      <tr key={d.id}>
                        <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{d.delivered_at || d.created_at}</td>
                        <td><span className="g2a-badge">{d.event}</span></td>
                        <td><StatusBadge value={d.status} /></td>
                        <td style={{ fontSize: 12 }}>{d.response_code || '—'}</td>
                        <td style={{ fontSize: 12 }}>{d.attempt_count}</td>
                        <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{d.next_attempt_at || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          )}
        </Modal>
      )}
    </div>
  );
}
