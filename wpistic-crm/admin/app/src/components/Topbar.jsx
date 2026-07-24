import React from 'react';
import { useLocation } from 'react-router-dom';
import { api } from '../api.js';

const TITLES = {
  '/': 'Dashboard',
  '/calendar': 'Booking Calendar',
  '/bookings': 'Bookings',
  '/customers': 'Travelers',
  '/leads': 'Lead Pipeline',
  '/tasks': 'Task Board',
  '/messages': 'Communication Center',
  '/templates': 'Message Templates',
  '/reports': 'Reports',
  '/exports': 'Data Exports',
  '/webhooks': 'Webhooks',
  '/integrations': 'Integrations',
  '/suppressions': 'Suppressions',
  '/audit-logs': 'Audit Logs',
  '/settings': 'Settings',
};

export default function Topbar() {
  const { pathname } = useLocation();
  const root = `/${pathname.split('/').filter(Boolean)[0] || ''}`;
  const title = TITLES[root === '/' ? '/' : root] || 'WPistic CRM';

  return (
    <header className="g2a-topbar">
      <h2>{title}</h2>
      <div className="g2a-topbar-user">
        Signed in as <strong style={{ color: 'var(--gold)' }}>{api.currentUser.name || 'Staff'}</strong>
      </div>
    </header>
  );
}
