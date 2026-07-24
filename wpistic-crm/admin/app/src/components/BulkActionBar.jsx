import React from 'react';

/**
 * Floating bulk-action bar that appears once the user has selected ≥1 row.
 * Renders inline at the top of the list. Parent owns the selection state.
 */
export default function BulkActionBar({ selectedCount, total, onClear, children, busy, lastResult }) {
  if (!selectedCount) {
    return null;
  }
  return (
    <div className="g2a-card" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8, padding: 12, marginBottom: 12 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <strong style={{ color: 'var(--text)' }}>
          {selectedCount} of {total} selected
        </strong>
        <button className="g2a-btn" onClick={onClear} disabled={busy} style={{ fontSize: 12 }}>Clear</button>
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
        {children}
      </div>
      {lastResult && (
        <div style={{ flexBasis: '100%', fontSize: 12, color: lastResult.failed?.length ? 'var(--warn, #D6A84F)' : 'var(--text-secondary)' }}>
          {lastResult.ok} succeeded
          {lastResult.failed?.length ? ` — ${lastResult.failed.length} failed (${lastResult.failed.slice(0, 3).map((f) => `#${f.id}: ${f.message}`).join('; ')}${lastResult.failed.length > 3 ? '…' : ''})` : ''}
        </div>
      )}
    </div>
  );
}
