import React from 'react';

/**
 * Inline-SVG sparkline / bar chart. Dependency-free.
 *
 * Props:
 *   data: array of { label, value } or array of numbers
 *   type: 'line' | 'bar' (default 'line')
 *   height: px height (default 80)
 *   color: stroke/fill color (default gold)
 *   label: optional axis label
 *   format: optional function to format tooltip / labels (default toString)
 */
export default function MiniChart({ data, type = 'line', height = 80, color = 'var(--gold)', format }) {
  const normalized = (data || []).map((d) =>
    typeof d === 'number' ? { label: '', value: d } : { label: d.label ?? '', value: Number(d.value) || 0 }
  );

  if (normalized.length === 0) {
    return <div style={{ color: 'var(--text-secondary)', fontSize: 12, fontStyle: 'italic', padding: 12 }}>No data in range.</div>;
  }

  const max = Math.max(1, ...normalized.map((d) => d.value));
  const min = Math.min(0, ...normalized.map((d) => d.value));
  const range = max - min || 1;
  const w = Math.max(120, normalized.length * 20);
  const padTop = 8;
  const padBottom = 18;
  const chartH = height - padTop - padBottom;

  const fmt = format || ((v) => Math.round(v * 100) / 100);

  if (type === 'bar') {
    const barW = (w - 8) / normalized.length;
    return (
      <svg width="100%" viewBox={`0 0 ${w} ${height}`} preserveAspectRatio="none" style={{ display: 'block' }}>
        {normalized.map((d, i) => {
          const h = (d.value / max) * chartH;
          const x = 4 + i * barW;
          const y = padTop + (chartH - h);
          return (
            <g key={i}>
              <rect x={x} y={y} width={Math.max(2, barW - 2)} height={Math.max(0, h)} fill={color} opacity={0.85} />
              {normalized.length <= 14 && (
                <text x={x + barW / 2} y={height - 4} fontSize="9" fill="var(--text-secondary)" textAnchor="middle">
                  {String(d.label).slice(-5)}
                </text>
              )}
            </g>
          );
        })}
        <title>{normalized.map((d) => `${d.label}: ${fmt(d.value)}`).join('\n')}</title>
      </svg>
    );
  }

  // Line
  const stepX = w / Math.max(1, normalized.length - 1);
  const points = normalized.map((d, i) => {
    const x = i * stepX;
    const y = padTop + chartH - ((d.value - min) / range) * chartH;
    return `${x},${y}`;
  });
  const areaPath = `M 0 ${padTop + chartH} L ${points.join(' L ')} L ${w} ${padTop + chartH} Z`;
  const linePath = `M ${points.join(' L ')}`;

  return (
    <svg width="100%" viewBox={`0 0 ${w} ${height}`} preserveAspectRatio="none" style={{ display: 'block' }}>
      <path d={areaPath} fill={color} opacity={0.12} />
      <path d={linePath} fill="none" stroke={color} strokeWidth="2" />
      {normalized.map((d, i) => {
        const x = i * stepX;
        const y = padTop + chartH - ((d.value - min) / range) * chartH;
        return <circle key={i} cx={x} cy={y} r="2" fill={color} />;
      })}
      <title>{normalized.map((d) => `${d.label}: ${fmt(d.value)}`).join('\n')}</title>
    </svg>
  );
}
