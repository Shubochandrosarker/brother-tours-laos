import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

export default function CheckIn() {
  const [search, setSearch] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const caps = api.capabilities;

  const lookup = async (e) => {
    e?.preventDefault();
    if (!search.trim()) return;
    setLoading(true);
    setError(null);
    try {
      const res = await api.checkInLookup(search.trim());
      setResults(res.customers || []);
    } catch (e2) {
      setError(e2.message);
    } finally {
      setLoading(false);
    }
  };

  const checkInBooking = async (bookingId, override = false) => {
    try {
      await api.bookingCheckIn(bookingId, override ? { skip_waiver_check: true } : {});
      await lookup();
    } catch (e) {
      if (e.data?.code === 'g2a_crm_waiver_required' && caps.verify_waivers) {
        if (confirm('Customer has no valid waiver. Override and check in anyway? (Logged.)')) {
          return checkInBooking(bookingId, true);
        }
        setError('No valid waiver. Direct customer to the kiosk first.');
        return;
      }
      setError(e.message);
    }
  };

  const verifyWaiver = async (customerId) => {
    // Find the most recent submitted waiver for this customer.
    try {
      const res = await api.listWaivers({ customer_id: customerId, status: 'submitted', per_page: 1 });
      if (!res.data?.length) { setError('No pending waiver to verify.'); return; }
      await api.verifyWaiver(res.data[0].id);
      await lookup();
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <div>
      <form onSubmit={lookup} className="g2a-card" style={{ marginBottom: 20 }}>
        <h3 className="g2a-section-title">Mission Check-In</h3>
        <p style={{ color: 'var(--text-secondary)', fontSize: 13, marginTop: 0 }}>
          Look up a customer by name, email, or phone. The system shows waiver status and any upcoming bookings so you can verify and check in from one screen.
        </p>
        <div style={{ display: 'flex', gap: 8 }}>
          <input
            className="g2a-input"
            placeholder="Search name, email, or phone…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            autoFocus
          />
          <button className="g2a-btn primary" type="submit" disabled={loading}>{loading ? 'Searching…' : 'Search'}</button>
        </div>
      </form>

      {error && <div className="g2a-error">{error}</div>}

      {results.length === 0 && !loading ? (
        <div className="g2a-empty">Search for a customer to begin.</div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {results.map((r) => (
            <div key={r.customer.id} className="g2a-card">
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 14 }}>
                <div>
                  <Link to={`/customers/${r.customer.id}`} style={{ color: 'var(--gold)', textDecoration: 'none', fontSize: 18, fontWeight: 600 }}>
                    {r.customer.first_name} {r.customer.last_name}
                  </Link>
                  <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginTop: 4 }}>
                    {r.customer.email || '—'} · {r.customer.phone || '—'}
                  </div>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6, alignItems: 'flex-end' }}>
                  <div>
                    Waiver: {r.has_valid_waiver
                      ? <span className="g2a-badge green">Valid</span>
                      : <span className="g2a-badge red">None / Expired</span>}
                  </div>
                  {!r.has_valid_waiver && caps.verify_waivers && (
                    <button className="g2a-btn" onClick={() => verifyWaiver(r.customer.id)}>Verify pending waiver</button>
                  )}
                </div>
              </div>

              <h4 className="g2a-section-title">Upcoming bookings</h4>
              {r.upcoming.length === 0 ? (
                <div style={{ color: 'var(--text-secondary)', fontSize: 13 }}>No upcoming bookings.</div>
              ) : (
                <table className="g2a-table">
                  <thead>
                    <tr><th>When</th><th>Type</th><th>Status</th><th></th></tr>
                  </thead>
                  <tbody>
                    {r.upcoming.map((b) => (
                      <tr key={b.id}>
                        <td><Link to={`/bookings/${b.id}`}>{b.start_time}</Link></td>
                        <td><span className="g2a-badge">{b.booking_type.replace(/_/g, ' ')}</span></td>
                        <td><StatusBadge value={b.status} /></td>
                        <td style={{ textAlign: 'right' }}>
                          {caps.edit_bookings && (b.status === 'pending' || b.status === 'confirmed') && (
                            <button className="g2a-btn primary" onClick={() => checkInBooking(b.id)}>Check in</button>
                          )}
                          {b.status === 'checked_in' && <span className="g2a-badge green">Already in</span>}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
