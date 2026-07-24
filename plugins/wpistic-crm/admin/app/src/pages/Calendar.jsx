import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

function startOfMonth(d) { return new Date(d.getFullYear(), d.getMonth(), 1); }
function endOfMonth(d) { return new Date(d.getFullYear(), d.getMonth() + 1, 0, 23, 59, 59); }
function ymd(d) {
  const z = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
}
function isoLocal(d) { return ymd(d) + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':' + String(d.getSeconds()).padStart(2, '0'); }

export default function Calendar() {
  const [cursor, setCursor] = useState(() => startOfMonth(new Date()));
  const [bookings, setBookings] = useState([]);
  const [error, setError] = useState(null);
  const [selectedDay, setSelectedDay] = useState(null);

  useEffect(() => {
    const from = isoLocal(startOfMonth(cursor));
    const to = isoLocal(endOfMonth(cursor));
    api.bookingsCalendar({ from, to })
      .then((res) => setBookings(res.bookings || []))
      .catch((e) => setError(e.message));
  }, [cursor]);

  const byDay = useMemo(() => {
    const map = {};
    for (const b of bookings) {
      const k = (b.start_time || '').slice(0, 10);
      if (!map[k]) map[k] = [];
      map[k].push(b);
    }
    return map;
  }, [bookings]);

  const monthLabel = cursor.toLocaleString(undefined, { month: 'long', year: 'numeric' });
  const firstDow = startOfMonth(cursor).getDay();
  const daysInMonth = endOfMonth(cursor).getDate();

  const cells = [];
  for (let i = 0; i < firstDow; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(new Date(cursor.getFullYear(), cursor.getMonth(), d));
  while (cells.length % 7 !== 0) cells.push(null);

  const todayKey = ymd(new Date());
  const selected = selectedDay ? (byDay[selectedDay] || []) : [];

  return (
    <div>
      <div className="g2a-toolbar">
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <button className="g2a-btn" onClick={() => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1))}>←</button>
          <h2 style={{ margin: 0, fontSize: 18 }}>{monthLabel}</h2>
          <button className="g2a-btn" onClick={() => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1))}>→</button>
          <button className="g2a-btn" onClick={() => setCursor(startOfMonth(new Date()))} style={{ marginLeft: 8 }}>Today</button>
        </div>
        <div style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{bookings.length} booking{bookings.length === 1 ? '' : 's'} this month</div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 12 }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 6, fontSize: 11, color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 6 }}>
          {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => <div key={d} style={{ padding: '6px 8px' }}>{d}</div>)}
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 6 }}>
          {cells.map((d, i) => {
            if (!d) return <div key={i} style={{ background: 'transparent' }} />;
            const key = ymd(d);
            const list = byDay[key] || [];
            const isToday = key === todayKey;
            const isSelected = key === selectedDay;
            return (
              <button
                key={i}
                onClick={() => setSelectedDay(isSelected ? null : key)}
                style={{
                  background: isSelected ? 'var(--panel-soft)' : 'var(--bg)',
                  border: '1px solid ' + (isToday ? 'var(--gold)' : 'var(--border)'),
                  borderRadius: 6,
                  minHeight: 92,
                  padding: 8,
                  textAlign: 'left',
                  color: 'var(--text)',
                  cursor: 'pointer',
                  font: 'inherit',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: 4,
                }}
              >
                <div style={{ fontSize: 12, fontWeight: 600, color: isToday ? 'var(--gold)' : 'var(--text-secondary)' }}>{d.getDate()}</div>
                {list.slice(0, 3).map((b) => (
                  <div key={b.id} style={{ fontSize: 10, color: 'var(--text)', background: 'var(--panel)', padding: '2px 6px', borderRadius: 3, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {b.start_time?.slice(11, 16)} {b.customer ? `${b.customer.first_name} ${b.customer.last_name?.[0] || ''}` : `#${b.customer_id}`}
                  </div>
                ))}
                {list.length > 3 && <div style={{ fontSize: 10, color: 'var(--text-secondary)' }}>+{list.length - 3} more</div>}
              </button>
            );
          })}
        </div>
      </div>

      {selectedDay && (
        <div className="g2a-card" style={{ marginTop: 20 }}>
          <div className="g2a-card-header">
            <h3>Bookings on {selectedDay}</h3>
            <button className="g2a-link-button" onClick={() => setSelectedDay(null)}>Close</button>
          </div>
          {selected.length === 0 ? (
            <div className="g2a-empty">Nothing scheduled.</div>
          ) : (
            <table className="g2a-table">
              <thead><tr><th>Time</th><th>Customer</th><th>Type</th><th>Status</th></tr></thead>
              <tbody>
                {selected.map((b) => (
                  <tr key={b.id}>
                    <td><Link to={`/bookings/${b.id}`}>{b.start_time?.slice(11, 16)}</Link></td>
                    <td>{b.customer ? <Link to={`/customers/${b.customer_id}`}>{b.customer.first_name} {b.customer.last_name}</Link> : `#${b.customer_id}`}</td>
                    <td><span className="g2a-badge">{b.booking_type.replace(/_/g, ' ')}</span></td>
                    <td><StatusBadge value={b.status} /></td>
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
