import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import Modal from './Modal.jsx';

const EMPTY = {
  customer_id: 0,
  record_type: 'ffl_transfer',
  status: 'inquiry_received',
  reference_code: '',
  document_ref: '',
  body: '',
  handled_by: 0,
  checklist: [],
  closed_reason: '',
};

/**
 * Modal for creating or editing a sensitive (compliance) record. Renders an
 * inline status pipeline, a checklist editor, and a free-form notes field.
 * The customer_id is required either via `initialCustomerId` (create) or
 * inferred from the loaded record (edit).
 */
export default function SensitiveRecordModal({ recordId, initialCustomerId, meta, onClose, onSaved }) {
  const caps = api.capabilities;
  const isEdit = !!recordId;

  const [data, setData] = useState({ ...EMPTY, customer_id: initialCustomerId || 0 });
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [newChecklistItem, setNewChecklistItem] = useState('');

  useEffect(() => {
    if (!isEdit) return;
    setLoading(true);
    api
      .getSensitive(recordId)
      .then((row) => {
        let checklist = [];
        if (row.checklist) {
          try { checklist = JSON.parse(row.checklist); } catch (_e) { checklist = []; }
        }
        setData({ ...row, checklist });
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [recordId, isEdit]);

  const set = (key, value) => setData({ ...data, [key]: value });

  const addChecklistItem = () => {
    const label = newChecklistItem.trim();
    if (!label) return;
    set('checklist', [...(data.checklist || []), { label, done: false }]);
    setNewChecklistItem('');
  };

  const toggleChecklistItem = (idx) => {
    const next = [...(data.checklist || [])];
    const wasDone = !!next[idx].done;
    next[idx] = {
      ...next[idx],
      done: !wasDone,
      completed_by: !wasDone ? api.currentUser.id : null,
      completed_at: !wasDone ? new Date().toISOString().slice(0, 19).replace('T', ' ') : null,
    };
    set('checklist', next);
  };

  const removeChecklistItem = (idx) => {
    const next = [...(data.checklist || [])];
    next.splice(idx, 1);
    set('checklist', next);
  };

  const save = async () => {
    setSaving(true);
    setError(null);
    try {
      const payload = {
        customer_id: parseInt(data.customer_id, 10) || 0,
        record_type: data.record_type,
        status: data.status,
        reference_code: data.reference_code,
        document_ref: data.document_ref,
        body: data.body,
        handled_by: data.handled_by ? parseInt(data.handled_by, 10) : 0,
        checklist: data.checklist || [],
        closed_reason: data.closed_reason || '',
      };
      const result = isEdit ? await api.updateSensitive(recordId, payload) : await api.createSensitive(payload);
      onSaved && onSaved(result);
      onClose();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!confirm('Delete this sensitive record? This is logged and permanent.')) return;
    try {
      await api.deleteSensitive(recordId);
      onSaved && onSaved(null);
      onClose();
    } catch (e) {
      setError(e.message);
    }
  };

  const recordTypes = meta?.record_types || [];
  const statuses = meta?.statuses || [];
  const openStatuses = new Set(meta?.open_statuses || []);
  const isClosed = data.status && !openStatuses.has(data.status);

  return (
    <Modal
      title={isEdit ? `Sensitive Record #${recordId}` : 'New Sensitive Record'}
      onClose={onClose}
      actions={
        <>
          {isEdit && caps.edit_sensitive && (
            <button className="g2a-btn danger" onClick={remove} style={{ marginRight: 'auto' }}>Delete</button>
          )}
          <button className="g2a-btn" onClick={onClose}>Cancel</button>
          <button className="g2a-btn primary" disabled={saving || loading || !data.customer_id} onClick={save}>
            {saving ? 'Saving…' : (isEdit ? 'Save changes' : 'Create record')}
          </button>
        </>
      }
    >
      {error && <div className="g2a-error">{error}</div>}
      {loading ? (
        <div className="g2a-loading">Loading record…</div>
      ) : (
        <>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Customer ID</label>
              <input
                className="g2a-input"
                type="number"
                value={data.customer_id || 0}
                disabled={isEdit || !!initialCustomerId}
                onChange={(e) => set('customer_id', parseInt(e.target.value || '0', 10))}
              />
            </div>
            <div className="g2a-field">
              <label>Record type</label>
              <select className="g2a-select" value={data.record_type} onChange={(e) => set('record_type', e.target.value)}>
                {recordTypes.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
          </div>

          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Status</label>
              <select className="g2a-select" value={data.status} onChange={(e) => set('status', e.target.value)}>
                {statuses.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
            <div className="g2a-field">
              <label>Reference code (ATF, NICS, etc.)</label>
              <input className="g2a-input" value={data.reference_code || ''} onChange={(e) => set('reference_code', e.target.value)} placeholder="optional" />
            </div>
          </div>

          <div className="g2a-field">
            <label>Document reference URL (link to stored document)</label>
            <input className="g2a-input" value={data.document_ref || ''} onChange={(e) => set('document_ref', e.target.value)} placeholder="https://…" />
          </div>

          {isClosed && (
            <div className="g2a-field">
              <label>Closed reason</label>
              <textarea className="g2a-textarea" value={data.closed_reason || ''} onChange={(e) => set('closed_reason', e.target.value)} />
            </div>
          )}

          <div className="g2a-field">
            <label>Staff checklist</label>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
              {(data.checklist || []).map((item, idx) => (
                <label key={idx} style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13 }}>
                  <input type="checkbox" checked={!!item.done} onChange={() => toggleChecklistItem(idx)} />
                  <span style={{ flex: 1, textDecoration: item.done ? 'line-through' : 'none', color: item.done ? 'var(--text-secondary)' : 'var(--text)' }}>
                    {item.label}
                    {item.done && item.completed_at && (
                      <span style={{ fontSize: 11, color: 'var(--text-secondary)', marginLeft: 6 }}>
                        ✓ {item.completed_at}{item.completed_by ? ` by user #${item.completed_by}` : ''}
                      </span>
                    )}
                  </span>
                  <button type="button" className="g2a-btn" style={{ padding: '2px 8px', fontSize: 11 }} onClick={() => removeChecklistItem(idx)}>×</button>
                </label>
              ))}
              <div style={{ display: 'flex', gap: 6 }}>
                <input
                  className="g2a-input"
                  placeholder="Add a checklist item (e.g. NICS submitted)…"
                  value={newChecklistItem}
                  onChange={(e) => setNewChecklistItem(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addChecklistItem(); } }}
                />
                <button type="button" className="g2a-btn" onClick={addChecklistItem}>Add</button>
              </div>
            </div>
          </div>

          <div className="g2a-field">
            <label>Notes</label>
            <textarea
              className="g2a-textarea"
              style={{ minHeight: 140 }}
              value={data.body || ''}
              onChange={(e) => set('body', e.target.value)}
              placeholder="Staff notes, decisions, escalation context — visible to compliance-cleared staff only."
            />
          </div>

          <div style={{ fontSize: 11, color: 'var(--text-secondary)', borderTop: '1px solid var(--border)', paddingTop: 8, marginTop: 4 }}>
            This record is restricted. Every read and write is logged to the audit trail. The CRM does not make legal decisions — required reviews and approvals must be completed by authorized staff.
          </div>
        </>
      )}
    </Modal>
  );
}
