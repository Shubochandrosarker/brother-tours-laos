import React from 'react';

const COLOR_MAP = {
  // Customer
  active: 'green',
  archived: 'amber',
  banned: 'red',
  // Lead
  new: 'blue',
  contacted: 'blue',
  qualified: 'gold',
  waiting: 'amber',
  appointment_booked: 'gold',
  converted: 'green',
  lost: 'red',
  not_a_fit: 'red',
  // Task
  open: 'blue',
  in_progress: 'amber',
  completed: 'green',
  cancelled: 'red',
  overdue: 'red',
  // Booking
  pending: 'amber',
  confirmed: 'blue',
  checked_in: 'green',
  no_show: 'red',
  rescheduled: 'amber',
  refunded: 'red',
  needs_review: 'amber',
  // Waiver
  not_submitted: 'red',
  submitted: 'amber',
  verified: 'green',
  expired: 'red',
  needs_update: 'amber',
  rejected: 'red',
  // Membership
  none: '',
  trial: 'blue',
  pending_payment: 'amber',
  past_due: 'red',
  suspended: 'red',
  vip: 'gold',
  // Class
  scheduled: 'blue',
  // Class student
  registered: 'blue',
  attended: 'green',
  needs_follow_up: 'amber',
  // Billing status
  current: 'green',
  failed: 'red',
  paused: 'amber',
  comp: 'gold',
  // Message status
  queued: 'amber',
  sending: 'blue',
  sent: 'green',
  skipped: 'amber',
};

export default function StatusBadge({ value }) {
  if (!value) return null;
  const cls = COLOR_MAP[value] || '';
  const label = String(value).replace(/_/g, ' ');
  return <span className={`g2a-badge ${cls}`}>{label}</span>;
}
