import React from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';

const CARDS = [
  { to: '/reports/revenue', title: 'Revenue', tone: 'gold', body: 'Booking + class revenue, MRR, top classes by revenue.' },
  { to: '/reports/pipeline', title: 'Lead Pipeline', tone: 'blue', body: 'Leads by source, conversion rate, status funnel, lead-score buckets.' },
  { to: '/reports/operations', title: 'Operations', tone: 'amber', body: 'Range-lane utilization, no-show rate, waiver completion.' },
  { to: '/reports/membership', title: 'Membership Health', tone: 'green', body: 'MRR, churn, by-plan breakdown, cancellation reasons, upcoming renewals.' },
  { to: '/reports/staff', title: 'Staff Performance', tone: 'gold', body: 'Tasks completed, bookings handled, leads converted, classes taught per user.' },
  { to: '/reports/communications', title: 'Communications', tone: 'blue', body: 'Delivery rate by channel, failure reasons, opt-out totals.' },
];

export default function Reports() {
  return (
    <div>
      <div className="g2a-toolbar">
        <p style={{ color: 'var(--text-secondary)', fontSize: 13, margin: 0 }}>
          Detailed business reports with date-range filters. All numbers refresh on each page load — no caching.
        </p>
        {api.capabilities.export_data && (
          <Link className="g2a-btn primary" to="/exports">Export data →</Link>
        )}
      </div>

      <div className="g2a-grid" style={{ gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }}>
        {CARDS.map((c) => (
          <Link key={c.to} to={c.to} style={{ textDecoration: 'none' }}>
            <div className="g2a-card" style={{ height: '100%' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <h3 style={{ margin: 0, color: 'var(--text)', fontSize: 16 }}>{c.title}</h3>
                <span className={`g2a-badge ${c.tone}`}>open →</span>
              </div>
              <p style={{ margin: '8px 0 0', color: 'var(--text-secondary)', fontSize: 13, lineHeight: 1.5 }}>{c.body}</p>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
