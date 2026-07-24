import React, { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';

/**
 * Dropdown menu of saved filter views for the current user + this resource.
 * onApply receives the saved view's filters object.
 * currentFilters is captured when "Save current as view" is clicked.
 */
export default function SavedViewsMenu({ resource, currentFilters, onApply }) {
  const [views, setViews] = useState([]);
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  const refresh = useCallback(() => {
    setLoading(true);
    api.listSavedViews(resource)
      .then((rows) => setViews(rows || []))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [resource]);

  useEffect(() => { refresh(); }, [refresh]);

  const saveCurrent = async () => {
    const name = (window.prompt('Name this view:') || '').trim();
    if (!name) return;
    setSaving(true);
    setError(null);
    try {
      await api.createSavedView({ resource, name, filters: currentFilters || {} });
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (id) => {
    if (!window.confirm('Delete this saved view?')) return;
    try {
      await api.deleteSavedView(id);
      refresh();
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <div style={{ position: 'relative', display: 'inline-block' }}>
      <button className="g2a-btn" onClick={() => setOpen((o) => !o)} disabled={loading}>
        Views{views.length ? ` (${views.length})` : ''} ▾
      </button>
      {open && (
        <div
          style={{
            position: 'absolute',
            right: 0,
            top: '100%',
            marginTop: 4,
            minWidth: 220,
            background: 'var(--card, #11161C)',
            border: '1px solid var(--border, #2A333D)',
            borderRadius: 6,
            zIndex: 50,
            boxShadow: '0 6px 20px rgba(0,0,0,0.4)',
            padding: 4,
          }}
        >
          {error && <div className="g2a-error" style={{ margin: 4, fontSize: 11 }}>{error}</div>}
          {views.length === 0 && (
            <div style={{ padding: 8, fontSize: 12, color: 'var(--text-secondary)' }}>No saved views yet.</div>
          )}
          {views.map((v) => (
            <div key={v.id} style={{ display: 'flex', alignItems: 'center', gap: 4, padding: 4 }}>
              <button
                className="g2a-btn"
                style={{ flex: 1, justifyContent: 'flex-start', fontSize: 12 }}
                onClick={() => { onApply(v.filters || {}); setOpen(false); }}
              >
                {v.name}
              </button>
              <button
                className="g2a-btn"
                onClick={() => remove(v.id)}
                title="Delete view"
                style={{ fontSize: 11, padding: '4px 8px' }}
              >
                ✕
              </button>
            </div>
          ))}
          <div style={{ borderTop: '1px solid var(--border, #2A333D)', marginTop: 4, paddingTop: 4 }}>
            <button
              className="g2a-btn primary"
              style={{ width: '100%', fontSize: 12 }}
              onClick={saveCurrent}
              disabled={saving}
            >
              {saving ? 'Saving…' : '+ Save current as view'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
