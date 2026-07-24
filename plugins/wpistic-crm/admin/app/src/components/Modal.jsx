import React, { useEffect } from 'react';

export default function Modal({ title, onClose, children, actions }) {
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose?.();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="g2a-modal-overlay" onClick={onClose}>
      <div className="g2a-modal" onClick={(e) => e.stopPropagation()}>
        {title && <h3>{title}</h3>}
        {children}
        {actions && <div className="g2a-modal-actions">{actions}</div>}
      </div>
    </div>
  );
}
