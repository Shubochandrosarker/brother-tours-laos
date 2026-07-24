import React, { useEffect, useState, useCallback } from 'react';
import { api } from '../api.js';
import Modal from '../components/Modal.jsx';

const CHANNELS = ['email', 'sms'];
const KNOWN_VARS = [
  'first_name', 'last_name', 'full_name', 'email', 'phone',
  'business_name', 'business_phone', 'business_email', 'site_url', 'waiver_link',
  'booking_type', 'booking_time', 'booking_date',
  'class_name', 'class_time',
  'plan_name', 'renewal_date', 'waiver_expires',
  'unsubscribe_email_url', 'unsubscribe_sms_url',
];

const EMPTY = { slug: '', channel: 'email', subject: '', body: '', is_active: true };

export default function Templates() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [editing, setEditing] = useState(null); // template object or 'new'
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);
  const [preview, setPreview] = useState(null);

  const caps = api.capabilities;
  const canManage = caps.manage_templates;

  const refresh = useCallback(() => {
    setLoading(true);
    api.listTemplates({}).then((res) => {
      setItems(res.rows || []);
      setError(null);
    }).catch((e) => setError(e.message)).finally(() => setLoading(false));
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  const openEdit = (tpl) => {
    setDraft(tpl ? { ...tpl, is_active: !!tpl.is_active } : EMPTY);
    setEditing(tpl || 'new');
    setPreview(null);
  };

  const save = async () => {
    setSaving(true);
    try {
      if (editing === 'new') {
        await api.upsertTemplate(draft);
      } else {
        await api.updateTemplate(editing.id, draft);
      }
      setEditing(null);
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const del = async (tpl) => {
    if (!confirm(`Delete template "${tpl.slug}"?`)) return;
    try { await api.deleteTemplate(tpl.id); refresh(); } catch (e) { setError(e.message); }
  };

  const runPreview = async () => {
    try {
      const res = await api.previewTemplate({ subject: draft.subject, body: draft.body });
      setPreview(res);
    } catch (e) {
      setError(e.message);
    }
  };

  const insertVar = (v) => {
    setDraft({ ...draft, body: (draft.body || '') + `{{${v}}}` });
  };

  return (
    <div>
      <div className="g2a-toolbar">
        <p style={{ color: 'var(--text-secondary)', fontSize: 13, margin: 0 }}>
          Slug-based templates the dispatcher and the manual "Send" flow look up by name. Edit the body and subject — variables in <code>{'{{variable_name}}'}</code> are rendered at send time.
        </p>
        {canManage && <button className="g2a-btn primary" onClick={() => openEdit(null)}>+ New Template</button>}
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No templates yet. Activation seeded a default set — try reactivating the plugin.</div>
        ) : (
          <table className="g2a-table">
            <thead>
              <tr>
                <th>Slug</th>
                <th>Channel</th>
                <th>Subject</th>
                <th>Active</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((t) => (
                <tr key={t.id}>
                  <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{t.slug}</td>
                  <td><span className="g2a-badge">{t.channel}</span></td>
                  <td style={{ fontSize: 13 }}>{t.subject || <em style={{ color: 'var(--text-secondary)' }}>(no subject)</em>}</td>
                  <td>{t.is_active ? <span className="g2a-badge green">active</span> : <span className="g2a-badge">off</span>}</td>
                  <td>
                    <div className="g2a-row-actions">
                      <button className="g2a-btn" onClick={() => openEdit(t)}>{canManage ? 'Edit' : 'View'}</button>
                      {canManage && <button className="g2a-btn danger" onClick={() => del(t)}>Delete</button>}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {editing !== null && (
        <Modal
          title={editing === 'new' ? 'New Template' : `Edit: ${draft.slug}`}
          onClose={() => setEditing(null)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setEditing(null)}>Close</button>
              {canManage && <button className="g2a-btn" onClick={runPreview}>Preview</button>}
              {canManage && <button className="g2a-btn primary" disabled={saving || !draft.slug} onClick={save}>{saving ? 'Saving…' : 'Save'}</button>}
            </>
          }
        >
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Slug</label>
              <input className="g2a-input" value={draft.slug} disabled={editing !== 'new' || !canManage} onChange={(e) => setDraft({ ...draft, slug: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_') })} />
            </div>
            <div className="g2a-field">
              <label>Channel</label>
              <select className="g2a-select" value={draft.channel} disabled={!canManage} onChange={(e) => setDraft({ ...draft, channel: e.target.value })}>
                {CHANNELS.map((c) => <option key={c} value={c}>{c}</option>)}
              </select>
            </div>
          </div>
          {draft.channel === 'email' && (
            <div className="g2a-field">
              <label>Subject</label>
              <input className="g2a-input" value={draft.subject || ''} disabled={!canManage} onChange={(e) => setDraft({ ...draft, subject: e.target.value })} />
            </div>
          )}
          <div className="g2a-field">
            <label>Body</label>
            <textarea className="g2a-textarea" style={{ minHeight: 180, fontFamily: 'monospace', fontSize: 13 }} value={draft.body || ''} disabled={!canManage} onChange={(e) => setDraft({ ...draft, body: e.target.value })} />
          </div>
          {canManage && (
            <div className="g2a-field">
              <label>Available variables (click to insert)</label>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                {KNOWN_VARS.map((v) => (
                  <button key={v} type="button" className="g2a-btn" style={{ fontSize: 11, padding: '4px 8px' }} onClick={() => insertVar(v)}>{`{{${v}}}`}</button>
                ))}
              </div>
            </div>
          )}
          <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <input type="checkbox" id="act" checked={!!draft.is_active} disabled={!canManage} onChange={(e) => setDraft({ ...draft, is_active: e.target.checked })} />
            <label htmlFor="act" style={{ margin: 0 }}>Active</label>
          </div>

          {preview && (
            <div className="g2a-card" style={{ marginTop: 12, background: 'var(--bg)' }}>
              <h4 className="g2a-section-title">Preview (Sample Customer)</h4>
              {preview.subject && <div style={{ fontWeight: 600, marginBottom: 8 }}>{preview.subject}</div>}
              <pre style={{ whiteSpace: 'pre-wrap', fontFamily: 'inherit', margin: 0, fontSize: 13 }}>{preview.body}</pre>
            </div>
          )}
        </Modal>
      )}
    </div>
  );
}
