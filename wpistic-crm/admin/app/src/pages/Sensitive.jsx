import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Pagination from '../components/Pagination.jsx';
import SensitiveRecordModal from '../components/SensitiveRecordModal.jsx';

export default function Sensitive() {
  const caps = api.capabilities;
  const [items, setItems] = useState([]);
  const [paginationMeta, setPaginationMeta] = useState(null);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [recordType, setRecordType] = useState('');
  const [status, setStatus] = useState('');
  const [openOnly, setOpenOnly] = useState(true);
  const [mineOnly, setMineOnly] = useState(false);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [openId, setOpenId] = useState(null);
  const [creating, setCreating] = useState(false);

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listSensitive({
        page,
        per_page: 25,
        record_type: recordType,
        status,
        open_only: openOnly ? 1 : '',
        handled_by: mineOnly ? api.currentUser.id : '',
        search,
      })
      .then((res) => {
        setItems(res.data || []);
        setPaginationMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, recordType, status, openOnly, mineOnly, search]);

  useEffect(() => {
    if (!meta) {
      api.sensitiveMeta().then(setMeta).catch((e) => setError(e.message));
    }
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  if (!caps.view_sensitive) {
    return (
      <div className="g2a-error">
        You do not have permission to view compliance records.
      </div>
    );
  }

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <h2 style={{ margin: 0, fontSize: 22, color: 'var(--accent-red, #D94141)' }}>Compliance Workflow</h2>
          <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginTop: 4 }}>
            Restricted compliance records — FFL transfer, NICS reference, A&D, legal hold. All access is logged.
          </div>
        </div>
        {caps.edit_sensitive && (
          <button className="g2a-btn primary" onClick={() => setCreating(true)}>+ New record</button>
        )}
      </div>

      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <input
            className="g2a-input"
            placeholder="Search reference / notes…"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            style={{ width: 220 }}
          />
          <select className="g2a-select" value={recordType} onChange={(e) => { setRecordType(e.target.value); setPage(1); }} style={{ width: 180 }}>
            <option value="">All record types</option>
            {(meta?.record_types || []).map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
          </select>
          <select className="g2a-select" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} style={{ width: 220 }}>
            <option value="">All statuses</option>
            {(meta?.statuses || []).map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </select>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={openOnly} onChange={(e) => { setOpenOnly(e.target.checked); setPage(1); }} />
            Open only
          </label>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={mineOnly} onChange={(e) => { setMineOnly(e.target.checked); setPage(1); }} />
            Assigned to me
          </label>
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0, borderColor: 'var(--accent-red, #D94141)' }}>
        {loading ? (
          <div className="g2a-loading">Loading records…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No compliance records match your filters.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Type</th>
                <th>Status</th>
                <th>Reference</th>
                <th>Handled by</th>
                <th>Updated</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((r) => (
                <tr key={r.id}>
                  <td>
                    <Link to={`/customers/${r.customer_id}`}>Customer #{r.customer_id}</Link>
                  </td>
                  <td><span className="g2a-badge">{r.record_type.replace(/_/g, ' ')}</span></td>
                  <td><StatusBadge value={r.status} /></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.reference_code || '—'}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.handled_by ? `user #${r.handled_by}` : '—'}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.updated_at}</td>
                  <td>
                    <button className="g2a-btn" onClick={() => setOpenId(r.id)} style={{ fontSize: 12 }}>Open</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={paginationMeta} onPage={setPage} />

      {creating && meta && (
        <SensitiveRecordModal
          meta={meta}
          onClose={() => setCreating(false)}
          onSaved={() => { setCreating(false); refresh(); }}
        />
      )}
      {openId && meta && (
        <SensitiveRecordModal
          recordId={openId}
          meta={meta}
          onClose={() => setOpenId(null)}
          onSaved={() => { setOpenId(null); refresh(); }}
        />
      )}
    </div>
  );
}
