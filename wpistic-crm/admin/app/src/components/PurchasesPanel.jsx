import React, { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import StatusBadge from './StatusBadge.jsx';

function fmtMoney(amount, currency) {
  if (amount === null || amount === undefined || amount === '') return '—';
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: (currency || 'USD').toUpperCase() }).format(Number(amount));
  } catch (_e) {
    return `${amount} ${currency || ''}`.trim();
  }
}

export default function PurchasesPanel({ customerId, initialRows }) {
  const caps = api.capabilities;
  const [rows, setRows] = useState(initialRows || []);
  const [loading, setLoading] = useState(false);
  const [backfilling, setBackfilling] = useState(false);
  const [error, setError] = useState(null);
  const [lastBackfill, setLastBackfill] = useState(null);

  useEffect(() => {
    if (initialRows) setRows(initialRows);
  }, [initialRows]);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.customerPurchases(customerId, { per_page: 50 });
      setRows(res.data || []);
      setError(null);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [customerId]);

  const backfill = async () => {
    setBackfilling(true);
    setLastBackfill(null);
    try {
      const res = await api.backfillWooCustomer(customerId);
      setLastBackfill(res.orders);
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setBackfilling(false);
    }
  };

  const total = rows.reduce((sum, r) => sum + Number(r.payload?.total || 0), 0);
  const currency = rows[0]?.payload?.currency || 'USD';

  return (
    <div className="g2a-card">
      <div className="g2a-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h3>Purchases</h3>
        <div style={{ display: 'flex', gap: 8 }}>
          {caps.manage_integrations && (
            <button className="g2a-btn" onClick={backfill} disabled={backfilling} title="Re-scan WooCommerce for orders matching this customer's email">
              {backfilling ? 'Backfilling…' : 'Backfill from Woo'}
            </button>
          )}
          <button className="g2a-btn" onClick={refresh} disabled={loading}>{loading ? 'Loading…' : 'Refresh'}</button>
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}
      {lastBackfill !== null && (
        <div className="g2a-empty" style={{ marginBottom: 8 }}>Backfill complete — {lastBackfill} order{lastBackfill === 1 ? '' : 's'} synced.</div>
      )}

      {rows.length === 0 ? (
        <div className="g2a-empty">No purchases linked to this customer yet.</div>
      ) : (
        <>
          <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginBottom: 8 }}>
            {rows.length} order{rows.length === 1 ? '' : 's'} · Lifetime value <strong style={{ color: 'var(--text)' }}>{fmtMoney(total, currency)}</strong>
          </div>
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Order</th>
                <th>Status</th>
                <th>Items</th>
                <th>Total</th>
                <th>Payment</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => {
                const items = Array.isArray(r.payload?.line_items) ? r.payload.line_items : [];
                const refunded = Number(r.payload?.refunded_total || 0);
                return (
                  <tr key={r.id}>
                    <td style={{ fontSize: 12, color: 'var(--text-secondary)', whiteSpace: 'nowrap' }}>{r.payload?.date_created || r.last_synced_at}</td>
                    <td>#{r.payload?.order_number || r.external_id}</td>
                    <td>{r.payload?.status ? <StatusBadge value={r.payload.status} /> : '—'}</td>
                    <td style={{ fontSize: 12 }}>
                      {items.length > 0 ? (
                        <details>
                          <summary style={{ cursor: 'pointer' }}>{r.payload?.items_count ?? items.length} item{(r.payload?.items_count ?? items.length) === 1 ? '' : 's'}</summary>
                          <ul style={{ margin: '4px 0 0', paddingLeft: 16, fontSize: 11 }}>
                            {items.map((li, idx) => (
                              <li key={idx} style={{ color: 'var(--text-secondary)' }}>
                                {li.name} × {li.qty} — {fmtMoney(li.total, r.payload?.currency)}
                              </li>
                            ))}
                          </ul>
                        </details>
                      ) : (
                        r.payload?.items_count ?? '—'
                      )}
                    </td>
                    <td>
                      {fmtMoney(r.payload?.total, r.payload?.currency)}
                      {refunded > 0 && (
                        <div style={{ fontSize: 11, color: 'var(--warn, #D6A84F)' }}>−{fmtMoney(refunded, r.payload?.currency)} refunded</div>
                      )}
                    </td>
                    <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{r.payload?.payment_method || '—'}</td>
                    <td>
                      {r.payload?.edit_url && (
                        <a className="g2a-btn" href={r.payload.edit_url} target="_blank" rel="noreferrer" style={{ fontSize: 12 }}>Open in Woo</a>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}
