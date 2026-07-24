import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Pagination from '../components/Pagination.jsx';

const STATUSES = ['queued', 'sending', 'sent', 'failed', 'cancelled', 'skipped'];
const CHANNELS = ['email', 'sms', 'reminder', 'internal'];

export default function Messages() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [channel, setChannel] = useState('');
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [dispatching, setDispatching] = useState(false);

  const caps = api.capabilities;
  const canSend = caps.send_email || caps.send_sms;

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listMessages({ page, per_page: 25, search, channel, status })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, search, channel, status]);

  useEffect(() => { refresh(); }, [refresh]);

  const dispatchNow = async () => {
    setDispatching(true);
    try {
      const res = await api.dispatchNow();
      alert(`Dispatched ${res.processed} message${res.processed === 1 ? '' : 's'}`);
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setDispatching(false);
    }
  };

  return (
    <div>
      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <input className="g2a-input" placeholder="Search subject / recipient…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} style={{ width: 260 }} />
          <select className="g2a-select" value={channel} onChange={(e) => { setChannel(e.target.value); setPage(1); }} style={{ width: 140 }}>
            <option value="">All channels</option>
            {CHANNELS.map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
          <select className="g2a-select" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} style={{ width: 140 }}>
            <option value="">All statuses</option>
            {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        {canSend && (
          <button className="g2a-btn primary" disabled={dispatching} onClick={dispatchNow}>
            {dispatching ? 'Dispatching…' : 'Dispatch now'}
          </button>
        )}
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No messages found.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>When</th>
                <th>Channel</th>
                <th>To</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Attempts</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((m) => (
                <tr key={m.id}>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)', whiteSpace: 'nowrap' }}>{m.sent_at || m.created_at}</td>
                  <td><span className="g2a-badge">{m.channel}</span></td>
                  <td style={{ fontSize: 12 }}>
                    {m.customer_id ? <Link to={`/customers/${m.customer_id}`}>#{m.customer_id}</Link> : '—'}<br />
                    <span style={{ color: 'var(--text-secondary)' }}>{m.to_address || '—'}</span>
                  </td>
                  <td><Link to={`/messages/${m.id}`}>{m.rendered_subject || m.subject || '(no subject)'}</Link></td>
                  <td><StatusBadge value={m.status} /></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{m.attempt_count}</td>
                  <td><Link className="g2a-btn" to={`/messages/${m.id}`}>Open</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}
