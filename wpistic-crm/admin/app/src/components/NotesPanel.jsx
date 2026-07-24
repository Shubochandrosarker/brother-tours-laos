import React, { useEffect, useState } from 'react';
import { api } from '../api.js';

/**
 * Polymorphic notes panel. Drop onto any detail page by passing related_type
 * + related_id. Edit/delete capability is enforced by the REST layer; the UI
 * shows controls based on `canEdit` and lets the backend reject if needed.
 */
export default function NotesPanel({ relatedType, relatedId, canEdit = true }) {
  const [items, setItems] = useState([]);
  const [body, setBody] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [editingId, setEditingId] = useState(0);
  const [editBody, setEditBody] = useState('');

  const load = () => {
    setLoading(true);
    api
      .listNotes({ related_type: relatedType, related_id: relatedId, per_page: 50 })
      .then((res) => {
        setItems(res.data || []);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [relatedType, relatedId]);

  const add = async () => {
    if (!body.trim()) return;
    setSaving(true);
    try {
      await api.createNote({ related_type: relatedType, related_id: relatedId, body, is_internal: true });
      setBody('');
      load();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const startEdit = (note) => {
    setEditingId(note.id);
    setEditBody(note.body);
  };

  const saveEdit = async () => {
    try {
      await api.updateNote(editingId, { body: editBody });
      setEditingId(0);
      setEditBody('');
      load();
    } catch (e) {
      setError(e.message);
    }
  };

  const remove = async (id) => {
    if (!confirm('Delete this note? This action is logged.')) return;
    try {
      await api.deleteNote(id);
      load();
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <div className="g2a-card">
      <div className="g2a-card-header">
        <h3>Notes</h3>
      </div>
      {error && <div className="g2a-error">{error}</div>}

      {canEdit && (
        <div className="g2a-field">
          <textarea
            className="g2a-textarea"
            placeholder="Internal staff note…"
            value={body}
            onChange={(e) => setBody(e.target.value)}
            style={{ minHeight: 70 }}
          />
          <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 6 }}>
            <button className="g2a-btn primary" onClick={add} disabled={saving || !body.trim()}>
              {saving ? 'Saving…' : 'Add note'}
            </button>
          </div>
        </div>
      )}

      {loading ? (
        <div className="g2a-loading">Loading notes…</div>
      ) : items.length === 0 ? (
        <div className="g2a-empty">No notes yet.</div>
      ) : (
        <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 10 }}>
          {items.map((n) => (
            <li key={n.id} className="g2a-timeline-item" style={{ display: 'block' }}>
              {editingId === n.id ? (
                <>
                  <textarea
                    className="g2a-textarea"
                    value={editBody}
                    onChange={(e) => setEditBody(e.target.value)}
                    style={{ minHeight: 70 }}
                  />
                  <div style={{ display: 'flex', gap: 6, justifyContent: 'flex-end', marginTop: 6 }}>
                    <button className="g2a-btn" onClick={() => { setEditingId(0); setEditBody(''); }}>Cancel</button>
                    <button className="g2a-btn primary" onClick={saveEdit} disabled={!editBody.trim()}>Save</button>
                  </div>
                </>
              ) : (
                <>
                  <div style={{ whiteSpace: 'pre-wrap' }}>{n.body}</div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 6, fontSize: 11, color: 'var(--text-secondary)' }}>
                    <span>{n.updated_at || n.created_at}{n.author_id ? ` · user #${n.author_id}` : ''}</span>
                    {canEdit && (
                      <div style={{ display: 'flex', gap: 6 }}>
                        <button className="g2a-btn" style={{ padding: '2px 8px', fontSize: 11 }} onClick={() => startEdit(n)}>Edit</button>
                        <button className="g2a-btn danger" style={{ padding: '2px 8px', fontSize: 11 }} onClick={() => remove(n.id)}>Delete</button>
                      </div>
                    )}
                  </div>
                </>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
