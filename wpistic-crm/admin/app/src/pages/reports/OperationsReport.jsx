import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api.js';
import MiniChart from '../../components/MiniChart.jsx';
import DateRange from '../../components/DateRange.jsx';

function daysAgo(n) { return new Date(Date.now() - n * 86400000).toISOString().slice(0, 10); }
function today() { return new Date().toISOString().slice(0, 10); }

export default function OperationsReport() {
  const [range, setRange] = useState({ from: daysAgo(30), to: today() });
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    setData(null);
    api.reportOperations(range).then(setData).catch((e) => setError(e.message));
  }, [range.from, range.to]);

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/reports" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All reports</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>Operations</h2>
        </div>
        <DateRange from={range.from} to={range.to} onChange={setRange} />
      </div>

      {error && <div className="g2a-error">{error}</div>}
      {!data && !error ? <div className="g2a-loading">Loading…</div> : null}

      {data && (
        <>
          <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 20 }}>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Total Bookings</span><span className="g2a-stat-value gold">{data.totals.total}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Attended</span><span className="g2a-stat-value green">{data.totals.attended}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">No-Shows</span><span className={`g2a-stat-value ${data.totals.no_shows ? 'red' : ''}`}>{data.totals.no_shows}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">No-Show Rate</span><span className={`g2a-stat-value ${data.totals.no_show_rate > 10 ? 'red' : 'amber'}`}>{data.totals.no_show_rate}%</span></div></div>
          </div>

          <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 20 }}>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Waivers Submitted</span><span className="g2a-stat-value">{data.totals.waivers_submitted}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Waivers Verified</span><span className="g2a-stat-value green">{data.totals.waivers_verified}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Pending Now</span><span className={`g2a-stat-value ${data.totals.waivers_pending ? 'amber' : ''}`}>{data.totals.waivers_pending}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Verify Rate</span><span className="g2a-stat-value gold">{data.totals.verify_rate}%</span></div></div>
          </div>

          <div className="g2a-card" style={{ marginBottom: 16 }}>
            <div className="g2a-card-header">
              <h3>Daily Bookings</h3>
            </div>
            <MiniChart
              type="bar"
              height={120}
              data={data.daily.map((r) => ({ label: r.d?.slice(5), value: parseInt(r.cnt, 10) }))}
            />
          </div>

          <div className="g2a-grid" style={{ gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div className="g2a-card">
              <div className="g2a-card-header"><h3>Resource Utilization</h3></div>
              {data.utilization.length === 0 ? <div className="g2a-empty">No resource bookings in range.</div> : (
                <table className="g2a-table">
                  <thead><tr><th>Resource</th><th>Bookings</th><th>Hours</th></tr></thead>
                  <tbody>
                    {data.utilization.map((r, i) => (
                      <tr key={`${r.resource_type}-${r.resource_id || i}`}>
                        <td><span className="g2a-badge">{r.resource_type} {r.resource_id || '—'}</span></td>
                        <td>{r.bookings}</td>
                        <td>{((parseInt(r.minutes, 10) || 0) / 60).toFixed(1)}h</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>

            <div className="g2a-card">
              <div className="g2a-card-header"><h3>Status Breakdown</h3></div>
              {data.by_status.length === 0 ? <div className="g2a-empty">No bookings in range.</div> : (
                <table className="g2a-table">
                  <thead><tr><th>Status</th><th style={{ textAlign: 'right' }}>Count</th></tr></thead>
                  <tbody>
                    {data.by_status.map((r) => (
                      <tr key={r.status}>
                        <td>{r.status.replace(/_/g, ' ')}</td>
                        <td style={{ textAlign: 'right' }}>{r.cnt}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
