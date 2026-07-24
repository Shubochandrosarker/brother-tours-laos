import React, { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';
import Pagination from '../components/Pagination.jsx';

const STATUSES = ['open', 'in_progress', 'waiting', 'completed', 'cancelled'];
const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
const RELATED = ['general', 'customer', 'lead', 'booking', 'class', 'membership', 'waiver'];

const EMPTY = { title: '', description: '', priority: 'normal', status: 'open', due_at: '', related_type: 'general', related_id: 0 };

export default function Tasks() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [mineOnly, setMineOnly] = useState(false);
  const [overdueOnly, setOverdueOnly] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);

  const caps = api.capabilities;

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listTasks({ page, per_page: 25, status: statusFilter, mine: mineOnly ? 1 : '', overdue: overdueOnly ? 1 : '' })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, statusFilter, mineOnly, overdueOnly]);

  useEffect(() => { refresh(); }, [refresh]);

  const save = async () => {
    setSaving(true);
    try {
      await api.createTask(draft);
      setModal(null);
      setDraft(EMPTY);
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const complete = async (task) => {
    try {
      await api.completeTask(task.id);
      refresh();
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <div>
      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <select className="g2a-select" value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} style={{ width: 160 }}>
            <option value="">All statuses</option>
            {STATUSES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
          </select>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={mineOnly} onChange={(e) => { setMineOnly(e.target.checked); setPage(1); }} />
            Mine only
          </label>
          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)' }}>
            <input type="checkbox" checked={overdueOnly} onChange={(e) => { setOverdueOnly(e.target.checked); setPage(1); }} />
            Overdue only
          </label>
        </div>
        {caps.edit_tasks && <button className="g2a-btn primary" onClick={() => { setDraft(EMPTY); setModal('create'); }}>+ New Task</button>}
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No tasks found.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Related</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Due</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((t) => (
                <tr key={t.id}>
                  <td>
                    <div style={{ fontWeight: 600 }}>{t.title}</div>
                    {t.description && <div style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{t.description.slice(0, 100)}{t.description.length > 100 && '…'}</div>}
                  </td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>
                    {t.related_type !== 'general' ? `${t.related_type} #${t.related_id}` : '—'}
                  </td>
                  <td><span className="g2a-badge">{t.priority}</span></td>
                  <td><StatusBadge value={t.status} /></td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{t.due_at || '—'}</td>
                  <td>
                    <div className="g2a-row-actions">
                      {caps.edit_tasks && t.status !== 'completed' && (
                        <button className="g2a-btn" onClick={() => complete(t)}>Complete</button>
                      )}
                    </div>
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
          title="New Task"
          onClose={() => setModal(null)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setModal(null)}>Cancel</button>
              <button className="g2a-btn primary" disabled={saving || !draft.title.trim()} onClick={save}>{saving ? 'Saving…' : 'Create'}</button>
            </>
          }
        >
          <div className="g2a-field"><label>Title</label><input className="g2a-input" value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} /></div>
          <div className="g2a-field"><label>Description</label><textarea className="g2a-textarea" value={draft.description} onChange={(e) => setDraft({ ...draft, description: e.target.value })} /></div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Priority</label>
              <select className="g2a-select" value={draft.priority} onChange={(e) => setDraft({ ...draft, priority: e.target.value })}>
                {PRIORITIES.map((p) => <option key={p} value={p}>{p}</option>)}
              </select>
            </div>
            <div className="g2a-field">
              <label>Due</label>
              <input className="g2a-input" type="datetime-local" value={draft.due_at} onChange={(e) => setDraft({ ...draft, due_at: e.target.value })} />
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Related to</label>
              <select className="g2a-select" value={draft.related_type} onChange={(e) => setDraft({ ...draft, related_type: e.target.value })}>
                {RELATED.map((r) => <option key={r} value={r}>{r}</option>)}
              </select>
            </div>
            <div className="g2a-field">
              <label>Related ID</label>
              <input className="g2a-input" type="number" value={draft.related_id} onChange={(e) => setDraft({ ...draft, related_id: parseInt(e.target.value || '0', 10) })} />
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
