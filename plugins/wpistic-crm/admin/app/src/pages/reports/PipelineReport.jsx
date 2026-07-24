import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api.js';
import MiniChart from '../../components/MiniChart.jsx';
import DateRange from '../../components/DateRange.jsx';

function daysAgo(n) { return new Date(Date.now() - n * 86400000).toISOString().slice(0, 10); }
function today() { return new Date().toISOString().slice(0, 10); }

export default function PipelineReport() {
  const [range, setRange] = useState({ from: daysAgo(30), to: today() });
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    setData(null);
    api.reportPipeline(range).then(setData).catch((e) => setError(e.message));
  }, [range.from, range.to]);

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/reports" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All reports</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>Lead Pipeline</h2>
        </div>
        <DateRange from={range.from} to={range.to} onChange={setRange} />
      </div>

      {error && <div className="g2a-error">{error}</div>}
      {!data && !error ? <div className="g2a-loading">Loading…</div> : null}

      {data && (
        <>
          <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 20 }}>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Total Leads</span><span className="g2a-stat-value gold">{data.totals.total}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Converted</span><span className="g2a-stat-value green">{data.totals.converted}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Lost</span><span className="g2a-stat-value red">{data.totals.lost}</span></div></div>
            <div className="g2a-card"><div className="g2a-stat"><span className="g2a-stat-label">Conversion Rate</span><span className="g2a-stat-value gold">{data.totals.conversion_rate}%</span></div></div>
          </div>

          <div className="g2a-grid" style={{ gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div className="g2a-card">
              <div className="g2a-card-header"><h3>By Source</h3></div>
              {data.by_source.length === 0 ? <div className="g2a-empty">No leads in range.</div> : (
                <table className="g2a-table">
                  <thead><tr><th>Source</th><th>Leads</th><th>Converted</th><th>Rate</th></tr></thead>
                  <tbody>
                    {data.by_source.map((r) => (
                      <tr key={r.source || '(none)'}>
                        <td>{r.source || <em style={{ color: 'var(--text-secondary)' }}>(none)</em>}</td>
                        <td>{r.leads}</td>
                        <td>{r.converted}</td>
                        <td>{r.conversion_rate}%</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>

            <div className="g2a-card">
              <div className="g2a-card-header"><h3>By Interest</h3></div>
              {data.by_interest.length === 0 ? <div className="g2a-empty">No leads in range.</div> : (
                <table className="g2a-table">
                  <thead><tr><th>Interest</th><th>Leads</th><th>Converted</th></tr></thead>
                  <tbody>
                    {data.by_interest.map((r) => (
                      <tr key={r.interest_type}>
                        <td><span className="g2a-badge">{r.interest_type.replace(/_/g, ' ')}</span></td>
                        <td>{r.leads}</td>
                        <td>{r.converted}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>

          <div className="g2a-grid" style={{ gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div className="g2a-card">
              <div className="g2a-card-header"><h3>Status Funnel</h3></div>
              <MiniChart
                type="bar"
                height={140}
                data={data.by_status.map((r) => ({ label: r.status, value: parseInt(r.cnt, 10) }))}
              />
              <div style={{ marginTop: 8, fontSize: 12, color: 'var(--text-secondary)' }}>
                {data.by_status.map((r) => `${r.status}: ${r.cnt}`).join(' · ')}
              </div>
            </div>

            <div className="g2a-card">
              <div className="g2a-card-header"><h3>Lead Score Distribution</h3></div>
              {data.score_distribution.length === 0 ? <div className="g2a-empty">No leads scored.</div> : (
                <table className="g2a-table">
                  <thead><tr><th>Bucket</th><th>Count</th></tr></thead>
                  <tbody>
                    {data.score_distribution.map((r) => (
                      <tr key={r.bucket}><td><span className="g2a-badge">{r.bucket.replace(/_/g, ' ')}</span></td><td>{r.cnt}</td></tr>
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
