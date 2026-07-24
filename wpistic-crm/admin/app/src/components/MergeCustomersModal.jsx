import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import Modal from './Modal.jsx';

/**
 * Merge a customer into a candidate (or a candidate into this customer).
 * The "anchor" customer is the one whose detail page launched the modal;
 * the operator picks a direction with the radio buttons.
 */
export default function MergeCustomersModal({ anchor, onClose, onMerged }) {
  const [candidates, setCandidates] = useState([]);
  const [includeInactive, setIncludeInactive] = useState(false);
  const [picked, setPicked] = useState(null);
  const [direction, setDirection] = useState('keep_anchor'); // 'keep_anchor' or 'keep_other'
  const [loading, setLoading] = useState(true);
  const [merging, setMerging] = useState(false);
  const [error, setError] = useState(null);
  const [result, setResult] = useState(null);

  const load = () => {
    setLoading(true);
    api
      .customerDuplicates(anchor.id, { include_inactive: includeInactive ? 1 : '' })
      .then((rows) => {
        setCandidates(rows || []);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(load, [anchor.id, includeInactive]);

  const merge = async () => {
    if (!picked) return;
    const target_id = direction === 'keep_anchor' ? anchor.id : picked.customer.id;
    const source_id = direction === 'keep_anchor' ? picked.customer.id : anchor.id;
    if (!confirm(`Merge customer #${source_id} into customer #${target_id}? The source customer will be deleted. This action is logged.`)) return;

    setMerging(true);
    setError(null);
    try {
      const body = direction === 'keep_anchor' ? { source_id } : { target_id };
      const res = await api.mergeCustomer(anchor.id, body);
      setResult(res);
      onMerged && onMerged(res);
    } catch (e) {
      setError(e.message);
    } finally {
      setMerging(false);
    }
  };

  return (
    <Modal
      title={`Merge customer #${anchor.id}`}
      onClose={onClose}
      actions={
        <>
          <button className="g2a-btn" onClick={onClose}>Close</button>
          {!result && (
            <button className="g2a-btn primary" onClick={merge} disabled={merging || !picked}>
              {merging ? 'Merging…' : 'Merge'}
            </button>
          )}
        </>
      }
    >
      {error && <div className="g2a-error">{error}</div>}

      {result ? (
        <div>
          <div className="g2a-empty" style={{ borderColor: 'var(--success, #33C27F)' }}>
            <strong>Merge complete.</strong> Customer #{result.source_id} was absorbed into #{result.target_id}.
          </div>
          <h4 className="g2a-section-title">Rows reassigned</h4>
          <table className="g2a-table">
            <tbody>
              {Object.entries(result.counts || {}).map(([table, count]) => (
                <tr key={table}>
                  <td style={{ fontSize: 12 }}>{table}</td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{Array.isArray(count) ? (count.length ? count.join(', ') : 'none') : count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <>
          <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginBottom: 10 }}>
            Anchor: <strong style={{ color: 'var(--text)' }}>#{anchor.id} {anchor.first_name} {anchor.last_name}</strong>
            {anchor.email && <span> · {anchor.email}</span>}
            {anchor.phone && <span> · {anchor.phone}</span>}
          </div>

          <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--text-secondary)', marginBottom: 12 }}>
            <input type="checkbox" checked={includeInactive} onChange={(e) => setIncludeInactive(e.target.checked)} />
            Include archived / banned customers
          </label>

          {loading ? (
            <div className="g2a-loading">Searching for matches…</div>
          ) : candidates.length === 0 ? (
            <div className="g2a-empty">No likely duplicates found by email, phone, or full name.</div>
          ) : (
            <>
              <h4 className="g2a-section-title">Likely duplicates</h4>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                {candidates.map((cand) => {
                  const c = cand.customer;
                  const isPicked = picked && picked.customer.id === c.id;
                  return (
                    <button
                      key={c.id}
                      type="button"
                      onClick={() => setPicked(cand)}
                      style={{
                        textAlign: 'left',
                        background: isPicked ? 'var(--panel-soft)' : 'var(--panel)',
                        border: '1px solid',
                        borderColor: isPicked ? 'var(--gold, #D6A84F)' : 'var(--border)',
                        borderRadius: 4,
                        padding: '8px 10px',
                        cursor: 'pointer',
                        color: 'var(--text)',
                      }}
                    >
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <div>
                          <strong>#{c.id} {c.first_name} {c.last_name}</strong>
                          <div style={{ fontSize: 11, color: 'var(--text-secondary)' }}>
                            {c.email || 'no email'} · {c.phone || 'no phone'} · {c.customer_type}
                          </div>
                        </div>
                        <div style={{ textAlign: 'right' }}>
                          <span className="g2a-badge" style={{ borderColor: 'var(--gold, #D6A84F)', color: 'var(--gold, #D6A84F)' }}>
                            {cand.score}% match
                          </span>
                          <div style={{ fontSize: 10, color: 'var(--text-secondary)', marginTop: 4 }}>
                            {cand.reasons.join(' + ')}
                          </div>
                        </div>
                      </div>
                    </button>
                  );
                })}
              </div>

              {picked && (
                <div style={{ marginTop: 14, padding: 10, border: '1px solid var(--border)', borderRadius: 4 }}>
                  <h4 className="g2a-section-title" style={{ marginTop: 0 }}>Which customer survives?</h4>
                  <label style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6, cursor: 'pointer' }}>
                    <input
                      type="radio"
                      checked={direction === 'keep_anchor'}
                      onChange={() => setDirection('keep_anchor')}
                    />
                    <span style={{ fontSize: 13 }}>
                      Keep <strong>#{anchor.id} {anchor.first_name} {anchor.last_name}</strong>;
                      delete <strong>#{picked.customer.id}</strong>
                    </span>
                  </label>
                  <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                    <input
                      type="radio"
                      checked={direction === 'keep_other'}
                      onChange={() => setDirection('keep_other')}
                    />
                    <span style={{ fontSize: 13 }}>
                      Keep <strong>#{picked.customer.id} {picked.customer.first_name} {picked.customer.last_name}</strong>;
                      delete <strong>#{anchor.id}</strong>
                    </span>
                  </label>
                  <div style={{ fontSize: 11, color: 'var(--text-secondary)', marginTop: 8 }}>
                    Conflict resolution: surviving customer's contact fields win. Blanks on the survivor are backfilled from the deleted record. Every row reassignment is audit-logged.
                  </div>
                </div>
              )}
            </>
          )}
        </>
      )}
    </Modal>
  );
}
