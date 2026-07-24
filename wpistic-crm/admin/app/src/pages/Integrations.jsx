import React, { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

function fmtMoney(amount, currency) {
  if (amount === null || amount === undefined || amount === '') return '—';
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: (currency || 'USD').toUpperCase() }).format(Number(amount));
  } catch (_e) {
    return `${amount} ${currency || ''}`.trim();
  }
}

export default function Integrations() {
  const caps = api.capabilities;
  const [stats, setStats] = useState(null);
  const [rows, setRows] = useState([]);
  const [unlinked, setUnlinked] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [syncing, setSyncing] = useState(false);
  const [syncOrderId, setSyncOrderId] = useState('');

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const [s, list, un] = await Promise.all([
        api.integrationStats(),
        api.listIntegrations({ per_page: 25 }),
        api.listUnlinkedIntegrations({ per_page: 50 }),
      ]);
      setStats(s);
      setRows(list.data || []);
      setUnlinked(un.data || []);
      setError(null);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  if (!caps.manage_integrations) {
    return <div className="g2a-error">You do not have permission to manage integrations.</div>;
  }

  const manualSync = async (e) => {
    e.preventDefault();
    const id = parseInt(syncOrderId, 10);
    if (!id) return;
    setSyncing(true);
    try {
      await api.syncWooOrder(id);
      setSyncOrderId('');
      refresh();
    } catch (err) {
      setError(err.message);
    } finally {
      setSyncing(false);
    }
  };

  const wooActive = stats?.woocommerce_active;

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <h2 style={{ margin: 0, fontSize: 22, color: 'var(--text)' }}>Integrations</h2>
          <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginTop: 4 }}>
            Inbound references from external systems. WooCommerce orders are mirrored automatically as purchases on the matching customer record.
          </div>
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 16, marginBottom: 16 }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
          <div>
            <div style={{ fontWeight: 600, color: 'var(--text)' }}>WooCommerce</div>
            <div style={{ fontSize: 12, color: 'var(--text-secondary)' }}>
              {wooActive ? 'Active — orders sync automatically on create/update.' : 'Not detected on this site. Install or activate WooCommerce to enable purchase sync.'}
            </div>
          </div>
          <StatusBadge value={wooActive ? 'active' : 'inactive'} />
        </div>

        {wooActive && (
          <form onSubmit={manualSync} style={{ display: 'flex', gap: 8, marginTop: 12, alignItems: 'flex-end' }}>
            <div className="g2a-field" style={{ flex: '0 0 200px' }}>
              <label>Manual sync (Woo Order ID)</label>
              <input className="g2a-input" type="number" min="1" value={syncOrderId} onChange={(e) => setSyncOrderId(e.target.value)} placeholder="e.g. 1042" />
            </div>
            <button type="submit" className="g2a-btn primary" disabled={syncing || !syncOrderId}>{syncing ? 'Syncing…' : 'Sync order'}</button>
          </form>
        )}
      </div>

      <div className="g2a-card" style={{ padding: 0, marginBottom: 16 }}>
        <div style={{ padding: 12, borderBottom: '1px solid var(--border)', fontWeight: 600 }}>By provider</div>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : stats?.providers?.length ? (
          <table className="g2a-table">
            <thead>
              <tr><th>Provider</th><th>Type</th><th>Records</th><th>Last sync</th></tr>
            </thead>
            <tbody>
              {stats.providers.map((p, i) => (
                <tr key={i}>
                  <td style={{ fontWeight: 600 }}>{p.provider}</td>
                  <td><span className="g2a-badge">{p.external_type}</span></td>
                  <td>{p.total}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{p.last_synced_at || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <div className="g2a-empty">Nothing synced yet.</div>
        )}
      </div>

      {unlinked.length > 0 && (
        <div className="g2a-card" style={{ padding: 0, marginBottom: 16 }}>
          <div style={{ padding: 12, borderBottom: '1px solid var(--border)', fontWeight: 600, color: 'var(--warn, #D6A84F)' }}>
            Unlinked — {unlinked.length} record{unlinked.length === 1 ? '' : 's'} could not be matched to a CRM customer
          </div>
          <table className="g2a-table">
            <thead>
              <tr><th>Provider</th><th>External ID</th><th>Email on record</th><th>Status</th><th>Total</th><th>Synced</th></tr>
            </thead>
            <tbody>
              {unlinked.map((r) => (
                <tr key={r.id}>
                  <td>{r.provider}</td>
                  <td>#{r.external_id}</td>
                  <td style={{ fontSize: 12 }}>{r.payload?.billing_email || '—'}</td>
                  <td>{r.payload?.status ? <StatusBadge value={r.payload.status} /> : '—'}</td>
                  <td>{fmtMoney(r.payload?.total, r.payload?.currency)}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.last_synced_at}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <div className="g2a-card" style={{ padding: 0 }}>
        <div style={{ padding: 12, borderBottom: '1px solid var(--border)', fontWeight: 600 }}>Recent activity</div>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : rows.length === 0 ? (
          <div className="g2a-empty">No integration records yet.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr><th>Provider</th><th>External</th><th>Linked to</th><th>Status</th><th>Total</th><th>Synced</th></tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id}>
                  <td>{r.provider}</td>
                  <td>{r.external_type} #{r.external_id}</td>
                  <td>{r.local_type === 'customer' ? <a href={`#/customers/${r.local_id}`}>customer #{r.local_id}</a> : <em style={{ color: 'var(--text-secondary)' }}>unlinked</em>}</td>
                  <td>{r.payload?.status ? <StatusBadge value={r.payload.status} /> : '—'}</td>
                  <td>{fmtMoney(r.payload?.total, r.payload?.currency)}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.last_synced_at}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
