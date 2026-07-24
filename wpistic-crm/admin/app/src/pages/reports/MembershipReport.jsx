import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api.js';
import DateRange from '../../components/DateRange.jsx';
import StatusBadge from '../../components/StatusBadge.jsx';

function daysAgo(n) { return new Date(Date.now() - n * 86400000).toISOString().slice(0, 10); }
function today() { return new Date().toISOString().slice(0, 10); }
function money(n) { return '$' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

export default function MembershipReport() {
  const [range, setRange] = useState({ from: daysAgo(30), to: today() });
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    setData(null);
    api.reportMembership(range).then(setData).catch((e) => setError(e.message));
  }, [range.from, range.to]);

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/reports" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All reports</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>Membership Health</h2>
        </div>
        <DateRange from={range.from} to={range.to} onChange={setRange} />
      </div>

      {error && <div className="g2a-error">{error}</div>}
      {!data && !error ? <div className="g2a-loading">Loading…</div> : null}

      {data && (
        <>
          <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 20 }}>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Active Members</span><span className="g2a-stat-value green">{data.totals.active_members}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">MRR</span><span className="g2a-stat-value gold">{money(data.totals.mrr)}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Churn (range)</span><span className={`g2a-stat-value ${data.totals.churn_rate_pct > 5 ? 'red' : 'amber'}`}>{data.totals.churn_rate_pct}%</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Net Change</span><span className={`g2a-stat-value ${data.totals.net_change >= 0 ? 'green' : 'red'}`}>{data.totals.net_change >= 0 ? '+' : ''}{data.totals.net_change}</span></div></div>
          </div>

          <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 20 }}>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">New in range</span><span className="g2a-stat-value gold">{data.totals.new_in_range}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Cancellations</span><span className={`g2a-stat-value ${data.totals.cancellations ? 'red' : ''}`}>{data.totals.cancellations}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Renewals (next 30d)</span><span className="g2a-stat-value amber">{data.totals.renewals_30d}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Range</span><span style={{ fontSize: 14, color: 'var(--text-secondary)' }}>{data.range.from_date} → {data.range.to_date}</span></div></div>
          </div>

          <div className="g2a-grid" style={{ gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div className="g2a-card">
              <div className="g2a-card-header"><h3>By Status</h3></div>
              <table className="g2a-table">
                <thead><tr><th>Status</th><th>Count</th><th style={{ textAlign: 'right' }}>MRR</th></tr></thead>
                <tbody>
                  {data.by_status.map((r) => (
                    <tr key={r.status}>
                      <td><StatusBadge value={r.status} /></td>
                      <td>{r.cnt}</td>
                      <td style={{ textAlign: 'right' }}>{money(r.mrr)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="g2a-card">
              <div className="g2a-card-header"><h3>Active by Plan</h3></div>
              {data.by_plan.length === 0 ? <div className="g2a-empty">No active members yet.</div> : (
                <table className="g2a-table">
                  <thead><tr><th>Plan</th><th>Members</th><th style={{ textAlign: 'right' }}>MRR</th></tr></thead>
                  <tbody>
                    {data.by_plan.map((r) => (
                      <tr key={r.plan_name}>
                        <td>{r.plan_name}</td>
                        <td>{r.cnt}</td>
                        <td style={{ textAlign: 'right' }}>{money(r.mrr)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>

          {data.cancellation_reasons.length > 0 && (
            <div className="g2a-card">
              <div className="g2a-card-header"><h3>Cancellation Reasons</h3></div>
              <table className="g2a-table">
                <thead><tr><th>Reason</th><th style={{ textAlign: 'right' }}>Count</th></tr></thead>
                <tbody>
                  {data.cancellation_reasons.map((r, i) => (
                    <tr key={i}>
                      <td style={{ fontSize: 13 }}>{r.cancellation_reason}</td>
                      <td style={{ textAlign: 'right' }}>{r.cnt}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </div>
  );
}
