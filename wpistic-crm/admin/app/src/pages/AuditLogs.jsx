import React, { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import Pagination from '../components/Pagination.jsx';

export default function AuditLogs() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [objectType, setObjectType] = useState('');
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);
  const [expanded, setExpanded] = useState(null);

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .auditLogs({ page, per_page: 50, object_type: objectType })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, objectType]);

  useEffect(() => { refresh(); }, [refresh]);

  return (
    <div>
      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <select className="g2a-select" value={objectType} onChange={(e) => { setObjectType(e.target.value); setPage(1); }} style={{ width: 180 }}>
            <option value="">All record types</option>
            <option value="customer">Customer</option>
            <option value="lead">Lead</option>
            <option value="task">Task</option>
          </select>
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No audit records.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>When</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Object</th>
                <th>IP</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((row) => (
                <React.Fragment key={row.id}>
                  <tr>
                    <td style={{ fontSize: 12, color: 'var(--text-secondary)', whiteSpace: 'nowrap' }}>{row.created_at}</td>
                    <td>{row.actor_id || '—'}</td>
                    <td><span className="g2a-badge gold">{row.action}</span></td>
                    <td>{row.object_type} #{row.object_id}</td>
                    <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{row.ip_address || '—'}</td>
                    <td>
                      <button className="g2a-link-button" onClick={() => setExpanded(expanded === row.id ? null : row.id)}>
                        {expanded === row.id ? 'Hide' : 'Diff'}
                      </button>
                    </td>
                  </tr>
                  {expanded === row.id && (
                    <tr>
                      <td colSpan={6} style={{ background: 'var(--panel-soft)' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                          <div>
                            <div style={{ fontSize: 11, color: 'var(--red)', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 6 }}>Before</div>
                            <pre style={{ background: 'var(--bg)', padding: 10, borderRadius: 4, fontSize: 11, overflow: 'auto', margin: 0, maxHeight: 240 }}>{row.old_value || '—'}</pre>
                          </div>
                          <div>
                            <div style={{ fontSize: 11, color: 'var(--green)', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 6 }}>After</div>
                            <pre style={{ background: 'var(--bg)', padding: 10, borderRadius: 4, fontSize: 11, overflow: 'auto', margin: 0, maxHeight: 240 }}>{row.new_value || '—'}</pre>
                          </div>
                        </div>
                      </td>
                    </tr>
                  )}
                </React.Fragment>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}
