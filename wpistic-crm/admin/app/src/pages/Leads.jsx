import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';
import Pagination from '../components/Pagination.jsx';
import BulkActionBar from '../components/BulkActionBar.jsx';
import SavedViewsMenu from '../components/SavedViewsMenu.jsx';

const STATUSES = ['new', 'contacted', 'qualified', 'waiting', 'appointment_booked', 'converted', 'lost', 'not_a_fit', 'archived'];
const INTEREST = ['range_visit', 'membership', 'training', 'private_lesson', 'retail', 'ffl_transfer', 'group_event', 'general'];

const EMPTY = { name: '', email: '', phone: '', interest_type: 'general', source: '', status: 'new', notes: '' };

export default function Leads() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);
  const [selected, setSelected] = useState(new Set());
  const [bulkBusy, setBulkBusy] = useState(false);
  const [bulkResult, setBulkResult] = useState(null);
  const [statusPicker, setStatusPicker] = useState({ open: false, value: '' });

  const caps = api.capabilities;

  const filters = useMemo(() => ({ search, status: statusFilter }), [search, statusFilter]);

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listLeads({ page, per_page: 25, ...filters })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, filters]);

  useEffect(() => { refresh(); }, [refresh]);

  useEffect(() => { setSelected(new Set()); }, [items]);

  const save = async () => {
    setSaving(true);
    try {
      await api.createLead(draft);
      setModal(null);
      setDraft(EMPTY);
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const updateStatus = async (lead, status) => {
    try {
      await api.updateLead(lead.id, { status });
      refresh();
    } catch (e) {
      setError(e.message);
    }
  };

  const toggleAll = () => {
    if (selected.size === items.length) {
      setSelected(new Set());
    } else {
      setSelected(new Set(items.map((l) => l.id)));
    }
  };

  const toggleOne = (id) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const runBulk = async (action, params) => {
    if (!selected.size) return;
    if (action === 'delete' && !window.confirm(`Delete ${selected.size} lead${selected.size === 1 ? '' : 's'}?`)) return;
    setBulkBusy(true);
    setBulkResult(null);
    try {
      const res = await api.bulkLeads(action, Array.from(selected), params);
      setBulkResult(res);
      refresh();
    } catch (e) {
      setBulkResult({ ok: 0, failed: [{ id: 0, message: e.message }] });
    } finally {
      setBulkBusy(false);
    }
  };

  const applyView = (f) => {
    setSearch(f.search || '');
    setStatusFilter(f.status || '');
    setPage(1);
  };

  const allChecked = items.length > 0 && selected.size === items.length;
  const someChecked = selected.size > 0 && selected.size < items.length;

  return (
    <div>
      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <input className="g2a-input" placeholder="Search leads…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} style={{ width: 240 }} />
          <select className="g2a-select" value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} style={{ width: 180 }}>
            <option value="">All statuses</option>
            {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </select>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          <SavedViewsMenu resource="leads" currentFilters={filters} onApply={applyView} />
          {caps.edit_leads && <button className="g2a-btn primary" onClick={() => { setDraft(EMPTY); setModal('create'); }}>+ New Lead</button>}
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <BulkActionBar
        selectedCount={selected.size}
        total={items.length}
        onClear={() => setSelected(new Set())}
        busy={bulkBusy}
        lastResult={bulkResult}
      >
        {caps.edit_leads && (
          <button className="g2a-btn" disabled={bulkBusy} onClick={() => setStatusPicker({ open: true, value: '' })}>Set status…</button>
        )}
        {caps.delete_leads && (
          <button className="g2a-btn danger" disabled={bulkBusy} onClick={() => runBulk('delete')}>Delete</button>
        )}
      </BulkActionBar>

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No leads found.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th style={{ width: 32 }}>
                  <input
                    type="checkbox"
                    checked={allChecked}
                    ref={(el) => { if (el) el.indeterminate = someChecked; }}
                    onChange={toggleAll}
                  />
                </th>
                <th>Name</th>
                <th>Contact</th>
                <th>Interest</th>
                <th>Source</th>
                <th>Status</th>
                <th>Follow-up</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((l) => (
                <tr key={l.id} style={selected.has(l.id) ? { background: 'var(--row-selected, rgba(214,168,79,0.08))' } : undefined}>
                  <td><input type="checkbox" checked={selected.has(l.id)} onChange={() => toggleOne(l.id)} /></td>
                  <td><Link to={`/leads/${l.id}`}>{l.name || '—'}</Link></td>
                  <td style={{ fontSize: 12 }}>{l.email}<br />{l.phone}</td>
                  <td><span className="g2a-badge">{l.interest_type.replace(/_/g, ' ')}</span></td>
                  <td>{l.source || '—'}</td>
                  <td>
                    <select className="g2a-select" value={l.status} onChange={(e) => updateStatus(l, e.target.value)} disabled={!caps.edit_leads} style={{ width: 160 }}>
                      {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
                    </select>
                  </td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{l.next_follow_up_at || '—'}</td>
                  <td><StatusBadge value={l.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={meta} onPage={setPage} />

      {statusPicker.open && (
        <Modal
          title="Set lead status"
          onClose={() => setStatusPicker({ open: false, value: '' })}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setStatusPicker({ open: false, value: '' })}>Cancel</button>
              <button
                className="g2a-btn primary"
                disabled={!statusPicker.value || bulkBusy}
                onClick={async () => {
                  await runBulk('status', { status: statusPicker.value });
                  setStatusPicker({ open: false, value: '' });
                }}
              >
                Apply to {selected.size}
              </button>
            </>
          }
        >
          <div className="g2a-field">
            <label>New status</label>
            <select className="g2a-select" value={statusPicker.value} onChange={(e) => setStatusPicker({ ...statusPicker, value: e.target.value })}>
              <option value="">— pick a status —</option>
              {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
            </select>
          </div>
        </Modal>
      )}

      {modal === 'create' && (
        <Modal
          title="New Lead"
          onClose={() => setModal(null)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setModal(null)}>Cancel</button>
              <button className="g2a-btn primary" disabled={saving} onClick={save}>{saving ? 'Saving…' : 'Create'}</button>
            </>
          }
        >
          <div className="g2a-field"><label>Name</label><input className="g2a-input" value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} /></div>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Email</label><input className="g2a-input" value={draft.email} onChange={(e) => setDraft({ ...draft, email: e.target.value })} /></div>
            <div className="g2a-field"><label>Phone</label><input className="g2a-input" value={draft.phone} onChange={(e) => setDraft({ ...draft, phone: e.target.value })} /></div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Interest</label>
              <select className="g2a-select" value={draft.interest_type} onChange={(e) => setDraft({ ...draft, interest_type: e.target.value })}>
                {INTEREST.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
            <div className="g2a-field"><label>Source</label><input className="g2a-input" value={draft.source} onChange={(e) => setDraft({ ...draft, source: e.target.value })} placeholder="web, walk_in, fb_lead…" /></div>
          </div>
          <div className="g2a-field"><label>Notes</label><textarea className="g2a-textarea" value={draft.notes} onChange={(e) => setDraft({ ...draft, notes: e.target.value })} /></div>
        </Modal>
      )}
    </div>
  );
}
