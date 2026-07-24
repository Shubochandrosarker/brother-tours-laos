import React from 'react';

export default function Pagination({ meta, onPage }) {
  if (!meta) return null;
  const { page, total_pages, total, per_page } = meta;
  if (total === 0) return null;

  const from = (page - 1) * per_page + 1;
  const to = Math.min(page * per_page, total);

  return (
    <div className="g2a-pagination">
      <div>
        Showing <strong>{from}</strong>–<strong>{to}</strong> of <strong>{total}</strong>
      </div>
      <div style={{ display: 'flex', gap: 8 }}>
        <button className="g2a-btn" disabled={page <= 1} onClick={() => onPage(page - 1)}>← Prev</button>
        <button className="g2a-btn" disabled={page >= total_pages} onClick={() => onPage(page + 1)}>Next →</button>
      </div>
    </div>
  );
}
