import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

export default function MessageDetail() {
  const { id } = useParams();
  const [m, setM] = useState(null);
  const [error, setError] = useState(null);

  const caps = api.capabilities;
  const canSend = caps.send_email || caps.send_sms;

  const load = () => {
    api.getMessage(id).then(setM).catch((e) => setError(e.message));
  };

  useEffect(load, [id]);

  const retry = async () => {
    try { await api.retryMessage(id); load(); } catch (e) { setError(e.message); }
  };
  const cancel = async () => {
    if (!confirm('Cancel this queued message?')) return;
    try { await api.cancelMessage(id); load(); } catch (e) { setError(e.message); }
  };

  if (error) return <div className="g2a-error">{error}</div>;
  if (!m) return <div className="g2a-loading">Loading message…</div>;

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/messages" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All messages</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>{m.rendered_subject || m.subject || '(no subject)'}</h2>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <StatusBadge value={m.status} />
            <span className="g2a-badge">{m.channel}</span>
            {m.provider && <span className="g2a-badge">{m.provider}</span>}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {canSend && ['failed', 'cancelled'].includes(m.status) && (
            <button className="g2a-btn primary" onClick={retry}>Retry</button>
          )}
          {canSend && ['queued', 'failed'].includes(m.status) && (
            <button className="g2a-btn danger" onClick={cancel}>Cancel</button>
          )}
        </div>
      </div>

      <div className="g2a-customer-layout">
        <aside className="g2a-card">
          <h4 className="g2a-section-title">Details</h4>
          <dl className="g2a-detail-list">
            <div><dt>Customer</dt><dd>{m.customer_id ? <Link to={`/customers/${m.customer_id}`}>#{m.customer_id}</Link> : '—'}</dd></div>
            {m.customer && <div><dt>Name</dt><dd>{m.customer.first_name} {m.customer.last_name}</dd></div>}
            <div><dt>To</dt><dd style={{ wordBreak: 'break-all' }}>{m.to_address || '—'}</dd></div>
            <div><dt>Attempts</dt><dd>{m.attempt_count}</dd></div>
            <div><dt>Sent at</dt><dd>{m.sent_at || '—'}</dd></div>
            {m.next_attempt_at && <div><dt>Next attempt</dt><dd style={{ fontSize: 12 }}>{m.next_attempt_at}</dd></div>}
            {m.template_id && <div><dt>Template</dt><dd>#{m.template_id}</dd></div>}
            {m.dedup_key && <div><dt>Dedup key</dt><dd style={{ fontSize: 11, fontFamily: 'monospace', wordBreak: 'break-all' }}>{m.dedup_key}</dd></div>}
            {m.provider_message_id && <div><dt>Provider ID</dt><dd style={{ fontSize: 11, fontFamily: 'monospace', wordBreak: 'break-all' }}>{m.provider_message_id}</dd></div>}
            <div><dt>Created</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{m.created_at}</dd></div>
          </dl>
        </aside>

        <section style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {m.error_text && (
            <div className="g2a-error">
              <strong>Error:</strong> {m.error_text}
            </div>
          )}

          {m.rendered_body ? (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Rendered (what was sent)</h4>
              {m.rendered_subject && <div style={{ fontWeight: 600, marginBottom: 8 }}>{m.rendered_subject}</div>}
              <pre style={{ whiteSpace: 'pre-wrap', fontFamily: 'inherit', margin: 0, background: 'var(--bg)', padding: 12, borderRadius: 6 }}>{m.rendered_body}</pre>
            </div>
          ) : null}

          <div className="g2a-card">
            <h4 className="g2a-section-title">Template (with variables)</h4>
            {m.subject && <div style={{ fontWeight: 600, marginBottom: 8, color: 'var(--text-secondary)' }}>{m.subject}</div>}
            <pre style={{ whiteSpace: 'pre-wrap', fontFamily: 'inherit', margin: 0, color: 'var(--text-secondary)', fontSize: 13 }}>{m.body}</pre>
          </div>
        </section>
      </div>
    </div>
  );
}
