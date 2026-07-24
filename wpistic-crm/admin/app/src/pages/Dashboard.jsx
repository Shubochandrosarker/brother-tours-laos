import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

function Card({ label, value, tone, to }) {
  const content = (
    <div className="g2a-card" style={{ height: '100%' }}>
      <div className="g2a-stat">
        <span className="g2a-stat-label">{label}</span>
        <span className={`g2a-stat-value ${tone || ''}`}>{value ?? '—'}</span>
      </div>
    </div>
  );
  return to ? <Link to={to} style={{ textDecoration: 'none' }}>{content}</Link> : content;
}

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    api.dashboard().then(setData).catch((e) => setError(e.message));
  }, []);

  if (error) return <div className="g2a-error">Dashboard failed to load: {error}</div>;
  if (!data) return <div className="g2a-loading">Loading dashboard…</div>;

  const c = data.cards || {};
  return (
    <div>
      <h3 className="g2a-section-title">Today's Operations</h3>
      <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 24 }}>
        <Card label="Today's Bookings" value={c.bookings_today} tone="gold" to="/calendar" />
        <Card label="Classes Today" value={c.classes_today} tone="blue" to="/classes" />
        <Card label="Pending Confirms" value={c.bookings_pending} tone="amber" to="/bookings" />
        <Card label="Waivers Need Verify" value={c.waivers_needs_verification} tone={c.waivers_needs_verification ? 'red' : ''} to="/waivers" />
      </div>

      <h3 className="g2a-section-title">Needs Attention</h3>
      <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 24 }}>
        <Card label="Overdue Tasks" value={c.tasks_overdue} tone={c.tasks_overdue ? 'red' : ''} to="/tasks" />
        <Card label="Past-Due Members" value={c.members_past_due} tone={c.members_past_due ? 'red' : ''} to="/memberships" />
        <Card label="Renewals Due (10d)" value={c.renewals_due} tone="amber" to="/memberships" />
        <Card label="Expiring Waivers (14d)" value={c.waivers_expiring_soon} tone="amber" to="/waivers" />
      </div>

      <h3 className="g2a-section-title">Membership Health</h3>
      <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 24 }}>
        <Card label="Active Members" value={c.members_active} tone="green" to="/memberships" />
        <Card label="Cancellations (30d)" value={c.cancellations_30d} tone={c.cancellations_30d > 5 ? 'red' : 'amber'} to="/memberships" />
        <Card label="Open Tasks" value={c.tasks_open} to="/tasks" />
        <Card label="My Open Tasks" value={c.tasks_mine_open} tone="gold" to="/tasks" />
      </div>

      <h3 className="g2a-section-title">Pipeline</h3>
      <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 24 }}>
        <Card label="Total Customers" value={c.customers_total} tone="gold" to="/customers" />
        <Card label="New Leads (7d)" value={c.leads_new_week} tone="blue" to="/leads" />
        <Card label="Open Leads" value={c.leads_open} tone="amber" to="/leads" />
        <Card label="Classes (next 7d)" value={c.classes_upcoming_week} tone="blue" to="/classes" />
      </div>

      <h3 className="g2a-section-title">Communications</h3>
      <div className="g2a-grid g2a-grid-4" style={{ marginBottom: 24 }}>
        <Card label="Queued Messages" value={c.messages_queued} tone={c.messages_queued > 50 ? 'amber' : ''} to="/messages" />
        <Card label="Sent Today" value={c.messages_sent_today} tone="green" to="/messages" />
        <Card label="Failed (24h)" value={c.messages_failed_24h} tone={c.messages_failed_24h ? 'red' : ''} to="/messages" />
        <Card label="Cancellations (30d)" value={c.cancellations_30d} tone={c.cancellations_30d > 5 ? 'red' : 'amber'} to="/memberships" />
      </div>

      <div className="g2a-grid" style={{ gridTemplateColumns: '2fr 1fr', gap: 16, marginBottom: 16 }}>
        <div className="g2a-card">
          <div className="g2a-card-header">
            <h3>Today's Booking Schedule</h3>
            <Link className="g2a-link-button" to="/bookings">View all →</Link>
          </div>
          {data.today_bookings?.length ? (
            <table className="g2a-table">
              <thead><tr><th>Time</th><th>Customer</th><th>Type</th><th>Status</th><th>Waiver</th></tr></thead>
              <tbody>
                {data.today_bookings.map((b) => (
                  <tr key={b.id}>
                    <td><Link to={`/bookings/${b.id}`}>{b.start_time?.slice(11, 16)}</Link></td>
                    <td><Link to={`/customers/${b.customer_id}`}>{b.first_name} {b.last_name}</Link></td>
                    <td><span className="g2a-badge">{b.booking_type.replace(/_/g, ' ')}</span></td>
                    <td><StatusBadge value={b.status} /></td>
                    <td><StatusBadge value={b.waiver_status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <div className="g2a-empty">Nothing scheduled for today.</div>
          )}
        </div>

        <div className="g2a-card">
          <div className="g2a-card-header">
            <h3>Top Lead Sources</h3>
          </div>
          {data.leads_by_source?.length ? (
            <table className="g2a-table">
              <thead><tr><th>Source</th><th style={{ textAlign: 'right' }}>Leads</th></tr></thead>
              <tbody>
                {data.leads_by_source.map((row) => (
                  <tr key={row.source}>
                    <td>{row.source}</td>
                    <td style={{ textAlign: 'right' }}>{row.cnt}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <div className="g2a-empty">No lead-source data yet.</div>
          )}
        </div>
      </div>

      <div className="g2a-card">
        <div className="g2a-card-header">
          <h3>Today's Classes</h3>
          <Link className="g2a-link-button" to="/classes">View all →</Link>
        </div>
        {data.today_classes?.length ? (
          <table className="g2a-table">
            <thead><tr><th>Time</th><th>Class</th><th>Type</th><th>Enrolled</th><th>Status</th></tr></thead>
            <tbody>
              {data.today_classes.map((cl) => (
                <tr key={cl.id}>
                  <td><Link to={`/classes/${cl.id}`}>{cl.start_time?.slice(11, 16)}</Link></td>
                  <td>{cl.name}</td>
                  <td><span className="g2a-badge">{cl.class_type}</span></td>
                  <td>{cl.enrolled || 0}/{cl.capacity}</td>
                  <td><StatusBadge value={cl.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <div className="g2a-empty">No classes today.</div>
        )}
      </div>
    </div>
  );
}
