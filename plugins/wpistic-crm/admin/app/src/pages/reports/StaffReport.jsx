import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api.js';
import DateRange from '../../components/DateRange.jsx';

function daysAgo(n) { return new Date(Date.now() - n * 86400000).toISOString().slice(0, 10); }
function today() { return new Date().toISOString().slice(0, 10); }

export default function StaffReport() {
  const [range, setRange] = useState({ from: daysAgo(30), to: today() });
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [sortBy, setSortBy] = useState('tasks_completed');

  useEffect(() => {
    setData(null);
    api.reportStaff(range).then(setData).catch((e) => setError(e.message));
  }, [range.from, range.to]);

  const sorted = data ? [...data.staff].sort((a, b) => (b[sortBy] || 0) - (a[sortBy] || 0)) : [];

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/reports" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All reports</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>Staff Performance</h2>
        </div>
        <DateRange from={range.from} to={range.to} onChange={setRange} />
      </div>

      {error && <div className="g2a-error">{error}</div>}
      {!data && !error ? <div className="g2a-loading">Loading…</div> : null}

      {data && (
        <div className="g2a-card" style={{ padding: 0 }}>
          {sorted.length === 0 ? (
            <div className="g2a-empty">No staff activity in this range. Make sure tasks, bookings, and leads have an assigned user.</div>
          ) : (
            <table className="g2a-table">
              <thead>
                <tr>
                  <th>Staff Member</th>
                  <Th label="Tasks Open" sortKey="tasks_open" sortBy={sortBy} setSortBy={setSortBy} />
                  <Th label="Tasks Done" sortKey="tasks_completed" sortBy={sortBy} setSortBy={setSortBy} />
                  <Th label="Overdue" sortKey="tasks_overdue" sortBy={sortBy} setSortBy={setSortBy} />
                  <Th label="Bookings" sortKey="bookings" sortBy={sortBy} setSortBy={setSortBy} />
                  <Th label="Leads" sortKey="leads" sortBy={sortBy} setSortBy={setSortBy} />
                  <Th label="Converted" sortKey="leads_converted" sortBy={sortBy} setSortBy={setSortBy} />
                  <Th label="Classes" sortKey="classes" sortBy={sortBy} setSortBy={setSortBy} />
                </tr>
              </thead>
              <tbody>
                {sorted.map((u) => (
                  <tr key={u.user_id}>
                    <td>
                      <div style={{ fontWeight: 600 }}>{u.name}</div>
                      <div style={{ fontSize: 11, color: 'var(--text-secondary)' }}>WP user #{u.user_id}</div>
                    </td>
                    <td>{u.tasks_open}</td>
                    <td><span className="g2a-badge green">{u.tasks_completed}</span></td>
                    <td>{u.tasks_overdue > 0 ? <span className="g2a-badge red">{u.tasks_overdue}</span> : 0}</td>
                    <td>{u.bookings}</td>
                    <td>{u.leads}</td>
                    <td><span className="g2a-badge green">{u.leads_converted}</span></td>
                    <td>{u.classes}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}
    </div>
  );
}

function Th({ label, sortKey, sortBy, setSortBy }) {
  const active = sortBy === sortKey;
  return (
    <th style={{ cursor: 'pointer', userSelect: 'none' }} onClick={() => setSortBy(sortKey)}>
      <span style={{ color: active ? 'var(--gold)' : 'inherit' }}>{label}{active ? ' ↓' : ''}</span>
    </th>
  );
}
