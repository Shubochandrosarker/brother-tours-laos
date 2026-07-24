import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import StatusBadge from '../components/StatusBadge.jsx';
import Modal from '../components/Modal.jsx';
import Pagination from '../components/Pagination.jsx';
import BulkActionBar from '../components/BulkActionBar.jsx';
import SavedViewsMenu from '../components/SavedViewsMenu.jsx';

const CUSTOMER_TYPES = ['walk_in', 'range_visitor', 'member', 'class_student', 'retail', 'ffl_transfer', 'lead', 'corporate', 'vip', 'inactive'];
const STATUSES = ['active', 'archived', 'banned'];

const EMPTY = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  customer_type: 'walk_in',
  source: '',
};

export default function Customers() {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(EMPTY);
  const [saving, setSaving] = useState(false);
  const [selected, setSelected] = useState(new Set());
  const [bulkBusy, setBulkBusy] = useState(false);
  const [bulkResult, setBulkResult] = useState(null);
  const [tags, setTags] = useState([]);
  const [tagPicker, setTagPicker] = useState({ open: false, action: null, tagId: '' });

  const caps = api.capabilities;

  const filters = useMemo(() => ({
    search,
    customer_type: typeFilter,
    status: statusFilter,
  }), [search, typeFilter, statusFilter]);

  const refresh = useCallback(() => {
    setLoading(true);
    api
      .listCustomers({ page, per_page: 25, ...filters })
      .then((res) => {
        setItems(res.data || []);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [page, filters]);

  useEffect(() => { refresh(); }, [refresh]);

  useEffect(() => {
    if (!caps.manage_tags) return;
    api.listTags({ per_page: 100 }).then((res) => setTags(res.data || res.rows || [])).catch(() => {});
  }, [caps.manage_tags]);

  // Clear selection whenever the underlying list changes.
  useEffect(() => { setSelected(new Set()); }, [items]);

  const openCreate = () => { setDraft(EMPTY); setModal('create'); };

  const save = async () => {
    setSaving(true);
    try {
      await api.createCustomer(draft);
      setModal(null);
      refresh();
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const toggleAll = () => {
    if (selected.size === items.length) {
      setSelected(new Set());
    } else {
      setSelected(new Set(items.map((c) => c.id)));
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
    const verb = { archive: 'archive', activate: 'restore', delete: 'delete', attach_tag: 'tag', detach_tag: 'untag' }[action] || action;
    if (action === 'delete' && !window.confirm(`Delete ${selected.size} customer${selected.size === 1 ? '' : 's'}? This cannot be undone.`)) {
      return;
    }
    setBulkBusy(true);
    setBulkResult(null);
    try {
      const res = await api.bulkCustomers(action, Array.from(selected), params);
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
    setTypeFilter(f.customer_type || '');
    setStatusFilter(f.status || '');
    setPage(1);
  };

  const allChecked = items.length > 0 && selected.size === items.length;
  const someChecked = selected.size > 0 && selected.size < items.length;

  return (
    <div>
      <div className="g2a-toolbar">
        <div className="g2a-toolbar-filters">
          <input
            className="g2a-input"
            placeholder="Search name, email, phone…"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            style={{ width: 260 }}
          />
          <select className="g2a-select" value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }} style={{ width: 180 }}>
            <option value="">All types</option>
            {CUSTOMER_TYPES.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
          </select>
          <select className="g2a-select" value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} style={{ width: 160 }}>
            <option value="">All statuses</option>
            {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          <SavedViewsMenu resource="customers" currentFilters={filters} onApply={applyView} />
          {caps.edit_customers && (
            <button className="g2a-btn primary" onClick={openCreate}>+ New Customer</button>
          )}
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
        {caps.edit_customers && (
          <>
            <button className="g2a-btn" disabled={bulkBusy} onClick={() => runBulk('archive')}>Archive</button>
            <button className="g2a-btn" disabled={bulkBusy} onClick={() => runBulk('activate')}>Activate</button>
          </>
        )}
        {caps.edit_customers && caps.manage_tags && (
          <>
            <button className="g2a-btn" disabled={bulkBusy} onClick={() => setTagPicker({ open: true, action: 'attach_tag', tagId: '' })}>Add tag…</button>
            <button className="g2a-btn" disabled={bulkBusy} onClick={() => setTagPicker({ open: true, action: 'detach_tag', tagId: '' })}>Remove tag…</button>
          </>
        )}
        {caps.delete_customers && (
          <button className="g2a-btn danger" disabled={bulkBusy} onClick={() => runBulk('delete')}>Delete</button>
        )}
      </BulkActionBar>

      <div className="g2a-card" style={{ padding: 0 }}>
        {loading ? (
          <div className="g2a-loading">Loading…</div>
        ) : items.length === 0 ? (
          <div className="g2a-empty">No customers found.</div>
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
                <th>Email</th>
                <th>Phone</th>
                <th>Type</th>
                <th>Status</th>
                <th>Waiver</th>
                <th>Member</th>
                <th>Last activity</th>
              </tr>
            </thead>
            <tbody>
              {items.map((c) => (
                <tr key={c.id} style={selected.has(c.id) ? { background: 'var(--row-selected, rgba(214,168,79,0.08))' } : undefined}>
                  <td>
                    <input type="checkbox" checked={selected.has(c.id)} onChange={() => toggleOne(c.id)} />
                  </td>
                  <td><Link to={`/customers/${c.id}`}>{c.first_name} {c.last_name}</Link></td>
                  <td>{c.email || <span style={{ color: 'var(--text-secondary)' }}>—</span>}</td>
                  <td>{c.phone || <span style={{ color: 'var(--text-secondary)' }}>—</span>}</td>
                  <td><span className="g2a-badge">{c.customer_type.replace(/_/g, ' ')}</span></td>
                  <td><StatusBadge value={c.status} /></td>
                  <td><StatusBadge value={c.waiver_status} /></td>
                  <td><StatusBadge value={c.membership_status} /></td>
                  <td style={{ color: 'var(--text-secondary)', fontSize: 12 }}>
                    {c.last_activity_at || c.updated_at}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Pagination meta={meta} onPage={setPage} />

      {tagPicker.open && (
        <Modal
          title={tagPicker.action === 'attach_tag' ? 'Add tag to selected' : 'Remove tag from selected'}
          onClose={() => setTagPicker({ open: false, action: null, tagId: '' })}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setTagPicker({ open: false, action: null, tagId: '' })}>Cancel</button>
              <button
                className="g2a-btn primary"
                disabled={!tagPicker.tagId || bulkBusy}
                onClick={async () => {
                  await runBulk(tagPicker.action, { tag_id: parseInt(tagPicker.tagId, 10) });
                  setTagPicker({ open: false, action: null, tagId: '' });
                }}
              >
                Apply to {selected.size}
              </button>
            </>
          }
        >
          <div className="g2a-field">
            <label>Tag</label>
            <select className="g2a-select" value={tagPicker.tagId} onChange={(e) => setTagPicker({ ...tagPicker, tagId: e.target.value })}>
              <option value="">— pick a tag —</option>
              {tags.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select>
          </div>
        </Modal>
      )}

      {modal === 'create' && (
        <Modal
          title="New Customer"
          onClose={() => setModal(null)}
          actions={
            <>
              <button className="g2a-btn" onClick={() => setModal(null)}>Cancel</button>
              <button className="g2a-btn primary" disabled={saving} onClick={save}>{saving ? 'Saving…' : 'Create'}</button>
            </>
          }
        >
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>First name</label>
              <input className="g2a-input" value={draft.first_name} onChange={(e) => setDraft({ ...draft, first_name: e.target.value })} />
            </div>
            <div className="g2a-field">
              <label>Last name</label>
              <input className="g2a-input" value={draft.last_name} onChange={(e) => setDraft({ ...draft, last_name: e.target.value })} />
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Email</label>
              <input className="g2a-input" type="email" value={draft.email} onChange={(e) => setDraft({ ...draft, email: e.target.value })} />
            </div>
            <div className="g2a-field">
              <label>Phone</label>
              <input className="g2a-input" value={draft.phone} onChange={(e) => setDraft({ ...draft, phone: e.target.value })} />
            </div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Type</label>
              <select className="g2a-select" value={draft.customer_type} onChange={(e) => setDraft({ ...draft, customer_type: e.target.value })}>
                {CUSTOMER_TYPES.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
            <div className="g2a-field">
              <label>Source</label>
              <input className="g2a-input" value={draft.source} onChange={(e) => setDraft({ ...draft, source: e.target.value })} placeholder="e.g. walk_in, web, referral" />
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
