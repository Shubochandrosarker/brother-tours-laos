import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api.js';
import MiniChart from '../../components/MiniChart.jsx';
import DateRange from '../../components/DateRange.jsx';
import StatusBadge from '../../components/StatusBadge.jsx';

function daysAgo(n) { return new Date(Date.now() - n * 86400000).toISOString().slice(0, 10); }
function today() { return new Date().toISOString().slice(0, 10); }

export default function CommunicationsReport() {
  const [range, setRange] = useState({ from: daysAgo(30), to: today() });
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    setData(null);
    api.reportCommunications(range).then(setData).catch((e) => setError(e.message));
  }, [range.from, range.to]);

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/reports" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All reports</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>Communications</h2>
        </div>
        <DateRange from={range.from} to={range.to} onChange={setRange} />
      </div>

      {error && <div className="g2a-error">{error}</div>}
      {!data && !error ? <div className="g2a-loading">Loading…</div> : null}

      {data && (
        <>
          <div className="g2a-card" style={{ marginBottom: 16 }}>
            <div className="g2a-card-header"><h3>Delivery by Channel</h3></div>
            {data.by_channel.length === 0 ? <div className="g2a-empty">No messages in range.</div> : (
              <table className="g2a-table">
                <thead><tr><th>Channel</th><th>Total</th><th>Sent</th><th>Failed</th><th>Skipped</th><th>Delivery Rate</th></tr></thead>
                <tbody>
                  {data.by_channel.map((r) => (
                    <tr key={r.channel}>
                      <td><span className="g2a-badge">{r.channel}</span></td>
                      <td>{r.total}</td>
                      <td><span className="g2a-badge green">{r.sent}</span></td>
                      <td>{r.failed > 0 ? <span className="g2a-badge red">{r.failed}</span> : 0}</td>
                      <td>{r.skipped}</td>
                      <td><span className={`g2a-badge ${r.delivery_rate >= 95 ? 'green' : r.delivery_rate >= 80 ? 'amber' : 'red'}`}>{r.delivery_rate}%</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <div className="g2a-card" style={{ marginBottom: 16 }}>
            <div className="g2a-card-header"><h3>Daily Volume</h3></div>
            <MiniChart
              type="bar"
              height={120}
              data={data.daily.map((r) => ({ label: r.d?.slice(5), value: parseInt(r.total, 10) }))}
            />
          </div>

          <div className="g2a-grid" style={{ gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div className="g2a-card">
              <div className="g2a-card-header"><h3>Top Failure Reasons</h3></div>
              {data.failures.length === 0 ? <div className="g2a-empty">No failures in range. ✓</div> : (
                <table className="g2a-table">
                  <thead><tr><th>Error</th><th style={{ textAlign: 'right' }}>Count</th></tr></thead>
                  <tbody>
                    {data.failures.map((r, i) => (
                      <tr key={i}>
                        <td style={{ fontSize: 12, fontFamily: 'monospace' }}>{r.error}</td>
                        <td style={{ textAlign: 'right' }}>{r.cnt}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>

            <div className="g2a-card">
              <div className="g2a-card-header"><h3>Status Breakdown</h3></div>
              <table className="g2a-table">
                <thead><tr><th>Status</th><th style={{ textAlign: 'right' }}>Count</th></tr></thead>
                <tbody>
                  {data.by_status.map((r) => (
                    <tr key={r.status}><td><StatusBadge value={r.status} /></td><td style={{ textAlign: 'right' }}>{r.cnt}</td></tr>
                  ))}
                </tbody>
              </table>
              <div style={{ marginTop: 12, padding: 12, background: 'var(--bg)', borderRadius: 6, fontSize: 12, color: 'var(--text-secondary)' }}>
                <div><strong>{data.opted_out.email_out || 0}</strong> customer{(data.opted_out.email_out || 0) === 1 ? '' : 's'} opted out of email</div>
                <div><strong>{data.opted_out.sms_out || 0}</strong> opted out of SMS</div>
                <div style={{ marginTop: 4 }}>Out of {data.opted_out.total} total customer{data.opted_out.total === 1 ? '' : 's'}.</div>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
