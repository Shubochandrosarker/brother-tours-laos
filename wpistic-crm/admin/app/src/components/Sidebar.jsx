import React from 'react';
import { NavLink } from 'react-router-dom';
import { api } from '../api.js';

// Tours CRM navigation — firearms-era modules removed.
const NAV = [
  { to: '/', label: 'Dashboard', cap: 'view_customers', end: true },
  { to: '/calendar', label: 'Calendar', cap: 'view_bookings' },
  { to: '/bookings', label: 'Bookings', cap: 'view_bookings' },
  { to: '/customers', label: 'Travelers', cap: 'view_customers' },
  { to: '/leads', label: 'Leads', cap: 'view_leads' },
  { to: '/tasks', label: 'Tasks', cap: 'view_tasks' },
  { to: '/messages', label: 'Messages', cap: 'view_customers' },
  { to: '/templates', label: 'Templates', cap: 'send_email' },
  { to: '/reports', label: 'Reports', cap: 'view_reports' },
  { to: '/exports', label: 'Exports', cap: 'export_data' },
  { to: '/webhooks', label: 'Webhooks', cap: 'manage_webhooks' },
  { to: '/integrations', label: 'Integrations', cap: 'manage_integrations' },
  { to: '/suppressions', label: 'Suppressions', cap: 'manage_settings' },
  { to: '/audit-logs', label: 'Audit Logs', cap: 'view_audit_logs' },
  { to: '/settings', label: 'Settings', cap: 'manage_settings' },
];

export default function Sidebar() {
  const caps = api.capabilities || {};
  return (
    <aside className="g2a-sidebar">
      <div className="g2a-sidebar-brand">
        <h1>WPistic CRM</h1>
        <small>Tours CRM · WordPressistic</small>
      </div>
      <nav className="g2a-nav">
        {NAV.filter((item) => caps[item.cap]).map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            className={({ isActive }) => `g2a-nav-link${isActive ? ' active' : ''}`}
          >
            {item.label}
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}
