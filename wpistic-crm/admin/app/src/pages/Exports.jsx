import React, { useState } from 'react';
import { api } from '../api.js';

function daysAgo(n) { return new Date(Date.now() - n * 86400000).toISOString().slice(0, 10); }
function today() { return new Date().toISOString().slice(0, 10); }

const EXPORTS = [
  { type: 'customers', label: 'Customers', desc: 'Full customer roster with contact info, opt-in flags, and waiver/membership status.' },
  { type: 'leads', label: 'Leads', desc: 'Leads with source, interest, status, follow-up dates, and conversion value.' },
  { type: 'bookings', label: 'Bookings', desc: 'All bookings with resource, status, payment, and amount.' },
  { type: 'memberships', label: 'Memberships', desc: 'Memberships with plan, status, billing, monthly amount, and renewal dates.' },
  { type: 'messages', label: 'Messages', desc: 'Message log with channel, recipient, status, attempts, and provider IDs.' },
  { type: 'waivers', label: 'Waivers', desc: 'Signed waiver records with status, expiry, IP, and signature hash.' },
];

export default function Exports() {
  const [range, setRange] = useState({ from: daysAgo(90), to: today() });
  const [error, setError] = useState(null);

  const download = (type) => {
    setError(null);
    const url = `${api.boot.restRoot}/reports/export?type=${encodeURIComponent(type)}&from=${encodeURIComponent(range.from)}&to=${encodeURIComponent(range.to)}&_wpnonce=${encodeURIComponent(api.boot.nonce)}`;
    // Browser-native download via a hidden anchor (carries cookies + nonce).
    const a = document.createElement('a');
    a.href = url;
    a.rel = 'noopener';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  if (!api.capabilities.export_data) {
    return <div className="g2a-error">You don't have permission to export CRM data.</div>;
  }

  return (
    <div>
      <div className="g2a-toolbar">
        <p style={{ margin: 0, color: 'var(--text-secondary)', fontSize: 13 }}>
          Range filter applies to the time-of-creation column for each entity. Each export is audit-logged (action <code>export.download</code>).
        </p>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <input className="g2a-input" type="date" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} style={{ width: 150 }} />
          <span style={{ color: 'var(--text-secondary)', fontSize: 12 }}>→</span>
          <input className="g2a-input" type="date" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} style={{ width: 150 }} />
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-grid" style={{ gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }}>
        {EXPORTS.map((x) => (
          <div key={x.type} className="g2a-card">
            <h3 style={{ margin: 0, fontSize: 16 }}>{x.label}</h3>
            <p style={{ color: 'var(--text-secondary)', fontSize: 12, lineHeight: 1.5, margin: '8px 0 14px' }}>{x.desc}</p>
            <button className="g2a-btn primary" onClick={() => download(x.type)}>↓ Download CSV</button>
          </div>
        ))}
      </div>
    </div>
  );
}
