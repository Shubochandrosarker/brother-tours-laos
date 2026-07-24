import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Pagination from '../components/Pagination.jsx';

const STATUSES = ['submitted', 'verified', 'expired', 'needs_update', 'rejected', 'archived'];

export default function Waivers() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [needsVerification, setNeedsVerification] = useState(false);
  const [expiringSoon, setExpiringSoon] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const caps = api.capabilities;

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listWaivers({
        page,
        per_page: 25,
        status: statusFilter,
        needs_verification: needsVerification ? 1 : '',
        expiring_within_days: expiringSoon ? 14 : '',
      })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, statusFilter, needsVerification, expiringSoon]);

  useEffect(() => { refresh(); }, [refresh]);

  const verify = async (id) => {
    try { await api.verifyWaiver(id); refresh(); } catch (e) { setError(e.message); }
  };
  const reject = async (id) => {
    const reason = prompt('Reject reason:') || '';
    if (!reason) return;
    try { await api.rejectWaiver(id, { reason }); refresh(); } catch (e) { setError(e.message); }
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
            <input type="checkbox" checked={needsVerification} onChange={(e) => { setNeedsVerification(e.target.checked); setPage(1); }} />
            Needs verification
          </label>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={expiringSoon} onChange={(e) => { setExpiringSoon(e.target.checked); setPage(1); }} />
            Expiring within 14 days
          </label>
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No waivers found.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Type</th>
                <th>Status</th>
                <th>Signed</th>
                <th>Expires</th>
                <th>PDF</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((w) => (
                <tr key={w.id}>
                  <td><Link to={`/customers/${w.customer_id}`}>#{w.customer_id}</Link></td>
                  <td><span className="g2a-badge">{w.waiver_type}</span></td>
                  <td><StatusBadge value={w.status} /></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{w.signed_at || '—'}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{w.expires_at || 'No expiry'}</td>
                  <td>
                    {w.pdf_url && <a href={w.pdf_url} target="_blank" rel="noreferrer" className="g2a-link-button">View</a>}
                  </td>
                  <td>
                    <div className="g2a-row-actions">
                      <Link className="g2a-btn" to={`/waivers/${w.id}`}>Open</Link>
                      {caps.verify_waivers && w.status === 'submitted' && (
                        <>
                          <button className="g2a-btn primary" onClick={() => verify(w.id)}>Verify</button>
                          <button className="g2a-btn danger" onClick={() => reject(w.id)}>Reject</button>
                        </>
                      )}
                    </div>
                  </td>
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
