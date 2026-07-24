import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';
import Pagination from '../components/Pagination.jsx';

const TYPES = ['ccw', 'safety', 'intro', 'private', 'group', 'orientation', 'event'];
const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

function localDT(d = new Date()) {
  const z = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}T${z(d.getHours())}:${z(d.getMinutes())}`;
}

const EMPTY = () => {
  const start = new Date(Date.now() + 24 * 60 * 60 * 1000);
  const end   = new Date(start.getTime() + 3 * 60 * 60 * 1000);
  return {
    name: '',
    class_type: 'safety',
    instructor_id: '',
    start_time: localDT(start),
    end_time: localDT(end),
    capacity: 10,
    price: 0,
    required_waiver: true,
    status: 'scheduled',
    notes: '',
  };
};

export default function Classes() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [upcoming, setUpcoming] = useState(false);
  const [mineOnly, setMineOnly] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);

  const caps = api.capabilities;

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listClasses({
        page,
        per_page: 25,
        class_type: typeFilter,
        status: statusFilter,
        upcoming: upcoming ? 1 : '',
        mine_as_instructor: mineOnly ? 1 : '',
      })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, typeFilter, statusFilter, upcoming, mineOnly]);

  useEffect(() => { refresh(); }, [refresh]);

  const save = async () => {
    setSaving(true);
    try {
      await api.createClass({
        ...draft,
        instructor_id: draft.instructor_id ? parseInt(draft.instructor_id, 10) : null,
        capacity: parseInt(draft.capacity, 10),
        price: parseFloat(draft.price),
      });
      setModal(null);
      setDraft(EMPTY());
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <select className="g2a-select" value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }} style={{ width: 160 }}>
            <option value="">All types</option>
            {TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
          </select>
          <select className="g2a-select" value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} style={{ width: 160 }}>
            <option value="">All statuses</option>
            {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </select>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={upcoming} onChange={(e) => { setUpcoming(e.target.checked); setPage(1); }} />
            Upcoming only
          </label>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={mineOnly} onChange={(e) => { setMineOnly(e.target.checked); setPage(1); }} />
            I teach
          </label>
        </div>
        {caps.edit_classes && <button className="g2a-btn primary" onClick={() => { setDraft(EMPTY()); setModal('create'); }}>+ New Class</button>}
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No classes found.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>When</th>
                <th>Capacity</th>
                <th>Status</th>
                <th>Instructor</th>
              </tr>
            </thead>
            <tbody>
              {items.map((c) => (
                <tr key={c.id}>
                  <td><Link to={`/classes/${c.id}`}>{c.name}</Link></td>
                  <td><span className="g2a-badge">{c.class_type}</span></td>
                  <td style={{ fontSize: 13 }}>{c.start_time}</td>
                  <td style={{ fontSize: 12 }}>
                    {c.enrolled || 0}/{c.capacity} {c.seats_left <= 0 && c.capacity > 0 && <span className="g2a-badge red">FULL</span>}
                  </td>
                  <td><StatusBadge value={c.status} /></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>
                    {c.instructor_id ? `#${c.instructor_id}` : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={meta} onPage={setPage} />

      {modal === 'create' && (
        <Modal
          title="New Class"
          onClose={() => setModal(null)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setModal(null)}>Cancel</button>
              <button className="g2a-btn primary" disabled={saving || !draft.name.trim()} onClick={save}>{saving ? 'Saving…' : 'Create'}</button>
            </>
          }
        >
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Name</label><input className="g2a-input" value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} /></div>
            <div className="g2a-field">
              <label>Type</label>
              <select className="g2a-select" value={draft.class_type} onChange={(e) => setDraft({ ...draft, class_type: e.target.value })}>
                {TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Start</label><input className="g2a-input" type="datetime-local" value={draft.start_time} onChange={(e) => setDraft({ ...draft, start_time: e.target.value })} /></div>
            <div className="g2a-field"><label>End</label><input className="g2a-input" type="datetime-local" value={draft.end_time} onChange={(e) => setDraft({ ...draft, end_time: e.target.value })} /></div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Capacity</label><input className="g2a-input" type="number" value={draft.capacity} onChange={(e) => setDraft({ ...draft, capacity: e.target.value })} /></div>
            <div className="g2a-field"><label>Price</label><input className="g2a-input" type="number" step="0.01" value={draft.price} onChange={(e) => setDraft({ ...draft, price: e.target.value })} /></div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Instructor WP user ID</label><input className="g2a-input" type="number" value={draft.instructor_id} onChange={(e) => setDraft({ ...draft, instructor_id: e.target.value })} placeholder="Leave blank if unassigned" /></div>
            <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 22 }}>
              <input type="checkbox" id="rw" checked={draft.required_waiver} onChange={(e) => setDraft({ ...draft, required_waiver: e.target.checked })} />
              <label htmlFor="rw" style={{ margin: 0 }}>Waiver required</label>
            </div>
          </div>
          <div className="g2a-field"><label>Notes</label><textarea className="g2a-textarea" value={draft.notes} onChange={(e) => setDraft({ ...draft, notes: e.target.value })} /></div>
        </Modal>
      )}
    </div>
  );
}
