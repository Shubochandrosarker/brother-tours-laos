import React from 'react';

function daysAgo(n) {
  const d = new Date(Date.now() - n * 86400000);
  return d.toISOString().slice(0, 10);
}
function today() { return new Date().toISOString().slice(0, 10); }

const PRESETS = [
  { label: '7d', from: () => daysAgo(7), to: today },
  { label: '30d', from: () => daysAgo(30), to: today },
  { label: '90d', from: () => daysAgo(90), to: today },
  { label: 'YTD', from: () => `${new Date().getFullYear()}-01-01`, to: today },
  { label: '12m', from: () => daysAgo(365), to: today },
];

export default function DateRange({ from, to, onChange }) {
  const apply = (preset) => onChange({ from: preset.from(), to: preset.to() });

  return (
    <div className="g2a-toolbar-filters" style={{ alignItems: 'center' }}>
      <div style={{ display: 'flex', gap: 4 }}>
        {PRESETS.map((p) => (
          <button key={p.label} className="g2a-btn" style={{ padding: '4px 10px', fontSize: 12 }} onClick={() => apply(p)}>{p.label}</button>
        ))}
      </div>
      <input className="g2a-input" type="date" value={from || ''} onChange={(e) => onChange({ from: e.target.value, to })} style={{ width: 150 }} />
      <span style={{ color: 'var(--text-secondary)', fontSize: 12 }}>→</span>
      <input className="g2a-input" type="date" value={to || ''} onChange={(e) => onChange({ from, to: e.target.value })} style={{ width: 150 }} />
    </div>
  );
}
