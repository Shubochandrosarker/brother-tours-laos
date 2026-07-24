import React, { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';

const STUDENT_STATUSES = ['registered', 'confirmed', 'checked_in', 'attended', 'completed', 'no_show', 'cancelled', 'needs_follow_up'];
const PAYMENT = ['unpaid', 'paid', 'partial', 'refunded', 'comp'];

export default function ClassDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [c, setC] = useState(null);
  const [error, setError] = useState(null);
  const [registerId, setRegisterId] = useState('');

  const caps = api.capabilities;

  const load = () => {
    api.getClass(id).then(setC).catch((e) => setError(e.message));
  };

  useEffect(load, [id]);

  const register = async () => {
    const cid = parseInt(registerId, 10);
    if (!cid) return;
    try {
      await api.registerStudent(id, { customer_id: cid });
      setRegisterId('');
      load();
    } catch (e) {
      setError(e.message);
    }
  };

  const setStatus = async (studentId, status) => {
    try { await api.updateStudent(id, studentId, { status }); load(); } catch (e) { setError(e.message); }
  };
  const setPayment = async (studentId, payment_status) => {
    try { await api.updateStudent(id, studentId, { payment_status }); load(); } catch (e) { setError(e.message); }
  };

  const cancelClass = async () => {
    const reason = prompt('Reason (optional):') || '';
    if (!confirm('Cancel this entire class? All enrollments will be marked cancelled.')) return;
    try { await api.cancelClass(id, { reason }); load(); } catch (e) { setError(e.message); }
  };

  const del = async () => {
    if (!confirm('Delete this class? (Logged.)')) return;
    try { await api.deleteClass(id); navigate('/classes'); } catch (e) { setError(e.message); }
  };

  if (error) return <div className="g2a-error">{error}</div>;
  if (!c) return <div className="g2a-loading">Loading class…</div>;

  const enrolled = (c.roster || []).filter((s) => s.status !== 'cancelled').length;

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <Link to="/classes" style={{ color: 'var(--text-secondary)', textDecoration: 'none', fontSize: 12 }}>← All classes</Link>
          <h2 style={{ margin: '4px 0 0', fontSize: 22 }}>{c.name}</h2>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <StatusBadge value={c.status} />
            <span className="g2a-badge">{c.class_type}</span>
            <span className="g2a-badge">{enrolled}/{c.capacity} enrolled</span>
            {c.required_waiver ? <span className="g2a-badge gold">Waiver required</span> : null}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {caps.edit_classes && c.status !== 'cancelled' && (
            <button className="g2a-btn" onClick={cancelClass}>Cancel class</button>
          )}
          {caps.edit_classes && <button className="g2a-btn danger" onClick={del}>Delete</button>}
        </div>
      </div>

      <div className="g2a-customer-layout">
        <aside className="g2a-card">
          <h4 className="g2a-section-title">Details</h4>
          <dl className="g2a-detail-list">
            <div><dt>Start</dt><dd>{c.start_time}</dd></div>
            <div><dt>End</dt><dd>{c.end_time || '—'}</dd></div>
            <div><dt>Capacity</dt><dd>{c.capacity}</dd></div>
            <div><dt>Seats left</dt><dd>{c.seats_left}</dd></div>
            <div><dt>Price</dt><dd>${parseFloat(c.price).toFixed(2)}</dd></div>
            <div><dt>Instructor</dt><dd>{c.instructor_name || (c.instructor_id ? `#${c.instructor_id}` : '—')}</dd></div>
            <div><dt>Created</dt><dd style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{c.created_at}</dd></div>
          </dl>
          {c.notes && (
            <>
              <h4 className="g2a-section-title" style={{ marginTop: 14 }}>Notes</h4>
              <p style={{ margin: 0, fontSize: 13, whiteSpace: 'pre-wrap' }}>{c.notes}</p>
            </>
          )}
        </aside>

        <section style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {caps.manage_class_roster && c.status !== 'cancelled' && (
            <div className="g2a-card">
              <h4 className="g2a-section-title">Register Student</h4>
              <div style={{ display: 'flex', gap: 8 }}>
                <input
                  className="g2a-input"
                  type="number"
                  placeholder="Customer ID"
                  value={registerId}
                  onChange={(e) => setRegisterId(e.target.value)}
                />
                <button className="g2a-btn primary" onClick={register} disabled={!registerId}>Register</button>
              </div>
              <p style={{ fontSize: 12, color: 'var(--text-secondary)', marginTop: 8, marginBottom: 0 }}>
                Look up a customer ID on the Customers page first, then paste it here.
              </p>
            </div>
          )}

          <div className="g2a-card">
            <div className="g2a-card-header">
              <h3>Roster ({enrolled}/{c.capacity})</h3>
            </div>
            {(c.roster || []).length === 0 ? (
              <div className="g2a-empty">No students registered yet.</div>
            ) : (
              <table className="g2a-table">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Waiver</th>
                  </tr>
                </thead>
                <tbody>
                  {c.roster.map((s) => (
                    <tr key={s.id}>
                      <td>
                        {s.first_name ? (
                          <Link to={`/customers/${s.customer_id}`}>{s.first_name} {s.last_name}</Link>
                        ) : (
                          <span style={{ color: 'var(--text-secondary)' }}>#{s.customer_id}</span>
                        )}
                        {s.email && <div style={{ fontSize: 11, color: 'var(--text-secondary)' }}>{s.email}</div>}
                      </td>
                      <td>
                        {caps.manage_class_roster ? (
                          <select className="g2a-select" value={s.status} onChange={(e) => setStatus(s.id, e.target.value)} style={{ width: 150 }}>
                            {STUDENT_STATUSES.map((st) => <option key={st} value={st}>{st.replace(/_/g, ' ')}</option>)}
                          </select>
                        ) : <StatusBadge value={s.status} />}
                      </td>
                      <td>
                        {caps.manage_class_roster ? (
                          <select className="g2a-select" value={s.payment_status} onChange={(e) => setPayment(s.id, e.target.value)} style={{ width: 130 }}>
                            {PAYMENT.map((p) => <option key={p} value={p}>{p}</option>)}
                          </select>
                        ) : <StatusBadge value={s.payment_status} />}
                      </td>
                      <td><StatusBadge value={s.waiver_status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}
