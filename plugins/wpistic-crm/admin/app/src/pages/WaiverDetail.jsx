import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

export default function WaiverDetail() {
  const { id } = useParams();
  const [w, setW] = useState(null);
  const [error, setError] = useState(null);

  const caps = api.capabilities;

  const load = () => {
    api.getWaiver(id).then(setW).catch((e) => setError(e.message));
  };

  useEffect(load, [id]);

  const verify = async () => {
    try { await api.verifyWaiver(id); load(); } catch (e) { setError(e.message); }
  };
  const reject = async () => {
    const reason = prompt('Reject reason:') || '';
    if (!reason) return;
    try { await api.rejectWaiver(id, { reason }); load(); } catch (e) { setError(e.message); }
  };

  if (error) return <div className="g2a-error">{error}</div>;
  if (!w) return <div className="g2a-loading">Loading waiver…</div>;

  let device = {};
  try { device = w.device_info ? JSON.parse(w.device_info) : {}; } catch (_) {}

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/waivers" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All waivers</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>
            Waiver #{w.id} — <span style={{ color: 'var(--gold)' }}>{w.waiver_type}</span>
          </h2>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <StatusBadge value={w.status} />
            {w.expires_at && new Date(w.expires_at) < new Date() && <span className="g2a-badge red">Past expiry</span>}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {w.pdf_url && <a className="g2a-btn" href={w.pdf_url} target="_blank" rel="noreferrer">View / Print PDF</a>}
          {caps.verify_waivers && w.status === 'submitted' && (
            <>
              <button className="g2a-btn primary" onClick={verify}>Verify</button>
              <button className="g2a-btn danger" onClick={reject}>Reject</button>
            </>
          )}
        </div>
      </div>

      <div className="g2a-customer-layout">
        <aside className="g2a-card">
          <h4 className="g2a-section-title">Customer</h4>
          {w.customer ? (
            <>
              <Link to={`/customers/${w.customer.id}`} style={{ color: 'var(--gold)', textDecoration: 'none', fontSize: 16, fontWeight: 600 }}>
                {w.customer.first_name} {w.customer.last_name}
              </Link>
              <dl className="g2a-detail-list" style={{ marginTop: 8 }}>
                <div><dt>Email</dt><dd>{w.customer.email || '—'}</dd></div>
                <div><dt>Phone</dt><dd>{w.customer.phone || '—'}</dd></div>
                <div><dt>DOB</dt><dd>{w.customer.date_of_birth || '—'}</dd></div>
              </dl>
            </>
          ) : (
            <p style={{ color: 'var(--text-secondary)' }}>Customer #{w.customer_id} not found.</p>
          )}
        </aside>

        <section style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div className="g2a-card">
            <h4 className="g2a-section-title">Signing record</h4>
            <dl className="g2a-detail-list">
              <div><dt>Signed at</dt><dd>{w.signed_at || '—'}</dd></div>
              <div><dt>Expires at</dt><dd>{w.expires_at || 'No expiry'}</dd></div>
              <div><dt>Source</dt><dd>{device.source || '—'}</dd></div>
              <div><dt>Typed name</dt><dd>{device.typed_name || '—'}</dd></div>
              <div><dt>Signature present</dt><dd>{device.signature_present ? 'Yes' : 'No'}</dd></div>
              <div><dt>IP address</dt><dd>{w.ip_address || '—'}</dd></div>
              <div><dt>Verified by</dt><dd>{w.verified_by ? `User #${w.verified_by} at ${w.verified_at}` : 'Not verified'}</dd></div>
              <div><dt>Signature hash</dt><dd style={{ fontSize: 11, fontFamily: 'monospace', wordBreak: 'break-all' }}>{w.signature_hash}</dd></div>
            </dl>
          </div>
        </section>
      </div>
    </div>
  );
}
