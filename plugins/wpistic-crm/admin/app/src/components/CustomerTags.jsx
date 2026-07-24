import React, { useEffect, useState } from 'react';
import { api } from '../api.js';

export default function CustomerTags({ customerId }) {
  const caps = api.capabilities;
  const [attached, setAttached] = useState([]);
  const [all, setAll] = useState([]);
  const [picker, setPicker] = useState(false);
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [creating, setCreating] = useState(false);

  const load = () => {
    setLoading(true);
    Promise.all([api.customerTags(customerId), api.listTags({ per_page: 100 })])
      .then(([mine, all]) => {
        setAttached(mine || []);
        setAll(all.data || []);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [customerId]);

  const detach = async (tagId) => {
    try {
      const next = await api.detachCustomerTag(customerId, tagId);
      setAttached(next || []);
    } catch (e) {
      setError(e.message);
    }
  };

  const attach = async (tagId) => {
    try {
      const next = await api.attachCustomerTag(customerId, { tag_id: tagId });
      setAttached(next || []);
      setPicker(false);
      setQuery('');
    } catch (e) {
      setError(e.message);
    }
  };

  const createAndAttach = async () => {
    const name = query.trim();
    if (!name) return;
    setCreating(true);
    try {
      const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
      const next = await api.attachCustomerTag(customerId, { slug, name, create_if_missing: true });
      setAttached(next || []);
      const refreshed = await api.listTags({ per_page: 100 });
      setAll(refreshed.data || []);
      setPicker(false);
      setQuery('');
    } catch (e) {
      setError(e.message);
    } finally {
      setCreating(false);
    }
  };

  const attachedIds = new Set(attached.map((t) => t.id));
  const filtered = all
    .filter((t) => !attachedIds.has(t.id))
    .filter((t) => !query || t.name.toLowerCase().includes(query.toLowerCase()) || t.slug.includes(query.toLowerCase()));
  const exactMatch = filtered.some((t) => t.name.toLowerCase() === query.trim().toLowerCase());

  return (
    <div className="g2a-card">
      <h4 className="g2a-section-title">Tags</h4>
      {error && <div className="g2a-error">{error}</div>}
      {loading ? (
        <div className="g2a-loading">Loading tags…</div>
      ) : (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, alignItems: 'center' }}>
          {attached.length === 0 && !picker && (
            <span style={{ fontSize: 12, color: 'var(--text-secondary)' }}>No tags yet.</span>
          )}
          {attached.map((t) => (
            <span
              key={t.id}
              className="g2a-badge"
              style={t.color ? { background: t.color + '22', borderColor: t.color, color: t.color } : undefined}
            >
              {t.name}
              {caps.edit_customers && (
                <button
                  type="button"
                  onClick={() => detach(t.id)}
                  title="Remove tag"
                  style={{ background: 'none', border: 0, color: 'inherit', cursor: 'pointer', marginLeft: 6, padding: 0, fontSize: 12 }}
                >×</button>
              )}
            </span>
          ))}
          {caps.edit_customers && !picker && (
            <button className="g2a-btn" onClick={() => setPicker(true)} style={{ padding: '2px 10px', fontSize: 12 }}>+ Add</button>
          )}
        </div>
      )}

      {picker && (
        <div style={{ marginTop: 10 }}>
          <input
            className="g2a-input"
            placeholder="Search or create a tag…"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            autoFocus
          />
          <div style={{ maxHeight: 180, overflowY: 'auto', marginTop: 6, border: '1px solid var(--border)', borderRadius: 4 }}>
            {filtered.length === 0 && (
              <div style={{ padding: 8, fontSize: 12, color: 'var(--text-secondary)' }}>No matching tags.</div>
            )}
            {filtered.map((t) => (
              <button
                key={t.id}
                onClick={() => attach(t.id)}
                style={{ display: 'block', width: '100%', textAlign: 'left', background: 'none', border: 0, color: 'var(--text)', padding: '6px 10px', cursor: 'pointer', fontSize: 13 }}
              >
                {t.name}
                <span style={{ color: 'var(--text-secondary)', fontSize: 11, marginLeft: 8 }}>{t.slug}</span>
              </button>
            ))}
          </div>
          <div style={{ display: 'flex', gap: 6, marginTop: 6, justifyContent: 'space-between' }}>
            <button className="g2a-btn" onClick={() => { setPicker(false); setQuery(''); }} style={{ fontSize: 12 }}>Cancel</button>
            {query.trim() && !exactMatch && caps.manage_tags && (
              <button className="g2a-btn primary" onClick={createAndAttach} disabled={creating} style={{ fontSize: 12 }}>
                {creating ? 'Creating…' : `Create "${query.trim()}"`}
              </button>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
