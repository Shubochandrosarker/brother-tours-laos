import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api.js';
import MiniChart from '../../components/MiniChart.jsx';
import DateRange from '../../components/DateRange.jsx';

function daysAgo(n) { return new Date(Date.now() - n * 86400000).toISOString().slice(0, 10); }
function today() { return new Date().toISOString().slice(0, 10); }
function money(n) { return '$' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

export default function RevenueReport() {
  const [range, setRange] = useState({ from: daysAgo(30), to: today() });
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    setData(null);
    api.reportRevenue(range).then(setData).catch((e) => setError(e.message));
  }, [range.from, range.to]);

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/reports" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All reports</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>Revenue</h2>
        </div>
        <DateRange from={range.from} to={range.to} onChange={setRange} />
      </div>

      {error && <div className="g2a-error">{error}</div>}
      {!data && !error ? <div className="g2a-loading">Loading…</div> : null}

      {data && (
        <>
          <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 20 }}>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Booking Revenue</span><span className="g2a-stat-value gold">{money(data.totals.booking_revenue)}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Class Revenue</span><span className="g2a-stat-value gold">{money(data.totals.class_revenue)}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Total in Range</span><span className="g2a-stat-value green">{money(data.totals.total_in_range)}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">MRR (current)</span><span className="g2a-stat-value gold">{money(data.totals.mrr_current)}</span></div></div>
          </div>

          <div className="g2a-card" style={{ marginBottom: 16 }}>
            <div className="g2a-card-header">
              <h3>Daily Booking Revenue</h3>
              <span style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{data.range.from_date} → {data.range.to_date}</span>
            </div>
            <MiniChart
              type="bar"
              height={120}
              data={(data.daily_series || []).map((r) => ({ label: r.d?.slice(5), value: parseFloat(r.amount) }))}
              format={money}
            />
          </div>

          <div className="g2a-card">
            <div className="g2a-card-header">
              <h3>Top Classes by Revenue</h3>
            </div>
            {data.top_classes?.length ? (
              <table className="g2a-table">
                <thead><tr><th>Class</th><th>Type</th><th>Students</th><th style={{ textAlign: 'right' }}>Revenue</th></tr></thead>
                <tbody>
                  {data.top_classes.map((c) => (
                    <tr key={c.id}>
                      <td><Link to={`/classes/${c.id}`}>{c.name}</Link></td>
                      <td><span className="g2a-badge">{c.class_type}</span></td>
                      <td>{c.students}</td>
                      <td style={{ textAlign: 'right' }}>{money(c.revenue)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : <div className="g2a-empty">No class revenue in this range.</div>}
          </div>
        </>
      )}
    </div>
  );
}
