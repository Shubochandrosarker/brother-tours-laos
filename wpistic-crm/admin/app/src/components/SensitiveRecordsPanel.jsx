import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import StatusBadge from './StatusBadge.jsx';
import SensitiveRecordModal from './SensitiveRecordModal.jsx';

/**
 * Compliance panel for the Customer Detail page. Renders nothing for users
 * without `view_sensitive` — the REST layer also enforces this, but hiding the
 * card client-side keeps regulated content out of unauthorized eyes.
 */
export default function SensitiveRecordsPanel({ customerId }) {
  const caps = api.capabilities;
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [openId, setOpenId] = useState(null);
  const [creating, setCreating] = useState(false);

  if (!caps.view_sensitive) return null;

  const load = () => {
    setLoading(true);
    Promise.all([
      api.listSensitive({ customer_id: customerId, per_page: 50 }),
      meta ? Promise.resolve(meta) : api.sensitiveMeta(),
    ])
      .then(([res, metaRes]) => {
        setItems(res.data || []);
        if (!meta) setMeta(metaRes);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [customerId]);

  const openStatuses = new Set(meta?.open_statuses || []);

  return (
    <div className="g2a-card" style={{ borderColor: 'var(--accent-red, #D94141)', borderWidth: 2 }}>
      <div className="g2a-card-header">
        <h3 style={{ color: 'var(--accent-red, #D94141)' }}>Compliance Records (restricted)</h3>
        {caps.edit_sensitive && (
          <button className="g2a-btn primary" onClick={() => setCreating(true)} style={{ fontSize: 12 }}>
            + New record
          </button>
        )}
      </div>

      <div style={{ fontSize: 11, color: 'var(--text-secondary)', marginBottom: 10 }}>
        FFL transfer, NICS reference, A&D status, legal hold, compliance review. All access logged.
      </div>

      {error && <div className="g2a-error">{error}</div>}

      {loading ? (
        <div className="g2a-loading">Loading records…</div>
      ) : items.length === 0 ? (
        <div className="g2a-empty">No compliance records on file.</div>
      ) : (
        <table className="g2a-table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Status</th>
              <th>Reference</th>
              <th>Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {items.map((r) => (
              <tr key={r.id}>
                <td><span className="g2a-badge">{r.record_type.replace(/_/g, ' ')}</span></td>
                <td>
                  <StatusBadge value={r.status} />
                  {!openStatuses.has(r.status) && r.closed_at && (
                    <span style={{ fontSize: 10, color: 'var(--text-secondary)', marginLeft: 6 }}>
                      closed {r.closed_at.slice(0, 10)}
                    </span>
                  )}
                </td>
                <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.reference_code || '—'}</td>
                <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.updated_at}</td>
                <td>
                  <button className="g2a-btn" onClick={() => setOpenId(r.id)} style={{ fontSize: 12 }}>Open</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {creating && meta && (
        <SensitiveRecordModal
          initialCustomerId={customerId}
          meta={meta}
          onClose={() => setCreating(false)}
          onSaved={() => { setCreating(false); load(); }}
        />
      )}
      {openId && meta && (
        <SensitiveRecordModal
          recordId={openId}
          meta={meta}
          onClose={() => setOpenId(null)}
          onSaved={() => { setOpenId(null); load(); }}
        />
      )}
    </div>
  );
}
