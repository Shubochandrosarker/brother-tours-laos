// Thin REST client over fetch. WP's wp_localize_script provides the root + nonce.
const boot = window.G2A_CRM_BOOT || {
  restRoot: '/wp-json/g2a-crm/v1',
  nonce: '',
  capabilities: {},
  currentUser: { id: 0, name: 'Guest' },
};

async function request(path, options = {}) {
  const url = `${boot.restRoot}${path.startsWith('/') ? path : `/${path}`}`;
  const init = {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': boot.nonce,
      ...(options.headers || {}),
    },
    credentials: 'same-origin',
  };
  if (options.body !== undefined) {
    init.body = JSON.stringify(options.body);
  }

  const res = await fetch(url, init);
  const text = await res.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch (_err) {
    data = text;
  }
  if (!res.ok) {
    const message = (data && data.message) || `Request failed (${res.status})`;
    const error = new Error(message);
    error.status = res.status;
    error.data = data;
    throw error;
  }
  return data;
}

function buildQuery(params) {
  if (!params) return '';
  const entries = Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '');
  if (!entries.length) return '';
  const qs = entries
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
    .join('&');
  return `?${qs}`;
}

export const api = {
  boot,
  capabilities: boot.capabilities || {},
  currentUser: boot.currentUser || { id: 0, name: '' },

  // Customers
  listCustomers: (params) => request(`/customers${buildQuery(params)}`),
  getCustomer: (id) => request(`/customers/${id}`),
  createCustomer: (body) => request('/customers', { method: 'POST', body }),
  updateCustomer: (id, body) => request(`/customers/${id}`, { method: 'PUT', body }),
  deleteCustomer: (id) => request(`/customers/${id}`, { method: 'DELETE' }),
  customerTimeline: (id) => request(`/customers/${id}/timeline`),
  customerDuplicates: (id, params) => request(`/customers/${id}/duplicates${buildQuery(params)}`),
  mergeCustomer: (anchorId, body) => request(`/customers/${anchorId}/merge`, { method: 'POST', body }),

  // Leads
  listLeads: (params) => request(`/leads${buildQuery(params)}`),
  getLead: (id) => request(`/leads/${id}`),
  createLead: (body) => request('/leads', { method: 'POST', body }),
  updateLead: (id, body) => request(`/leads/${id}`, { method: 'PUT', body }),
  deleteLead: (id) => request(`/leads/${id}`, { method: 'DELETE' }),
  convertLead: (id, body) => request(`/leads/${id}/convert`, { method: 'POST', body: body || {} }),
  leadActivity: (id) => request(`/leads/${id}/activity`),
  addLeadActivity: (id, body) => request(`/leads/${id}/activity`, { method: 'POST', body }),

  // Tasks
  listTasks: (params) => request(`/tasks${buildQuery(params)}`),
  createTask: (body) => request('/tasks', { method: 'POST', body }),
  updateTask: (id, body) => request(`/tasks/${id}`, { method: 'PUT', body }),
  completeTask: (id) => request(`/tasks/${id}/complete`, { method: 'POST', body: {} }),
  deleteTask: (id) => request(`/tasks/${id}`, { method: 'DELETE' }),

  // Bookings
  listBookings: (params) => request(`/bookings${buildQuery(params)}`),
  bookingsCalendar: (params) => request(`/bookings/calendar${buildQuery(params)}`),
  getBooking: (id) => request(`/bookings/${id}`),
  createBooking: (body) => request('/bookings', { method: 'POST', body }),
  updateBooking: (id, body) => request(`/bookings/${id}`, { method: 'PUT', body }),
  deleteBooking: (id) => request(`/bookings/${id}`, { method: 'DELETE' }),
  bookingCheckIn: (id, body) => request(`/bookings/${id}/check-in`, { method: 'POST', body: body || {} }),
  bookingCancel: (id, body) => request(`/bookings/${id}/cancel`, { method: 'POST', body: body || {} }),
  bookingComplete: (id) => request(`/bookings/${id}/complete`, { method: 'POST', body: {} }),

  // Waivers
  listWaivers: (params) => request(`/waivers${buildQuery(params)}`),
  getWaiver: (id) => request(`/waivers/${id}`),
  createWaiver: (body) => request('/waivers', { method: 'POST', body }),
  verifyWaiver: (id) => request(`/waivers/${id}/verify`, { method: 'POST', body: {} }),
  rejectWaiver: (id, body) => request(`/waivers/${id}/reject`, { method: 'POST', body: body || {} }),

  // Check-in lookup
  checkInLookup: (search) => request(`/check-in/lookup${buildQuery({ search })}`),

  // Memberships
  listMemberships: (params) => request(`/memberships${buildQuery(params)}`),
  getMembership: (id) => request(`/memberships/${id}`),
  createMembership: (body) => request('/memberships', { method: 'POST', body }),
  updateMembership: (id, body) => request(`/memberships/${id}`, { method: 'PUT', body }),
  deleteMembership: (id) => request(`/memberships/${id}`, { method: 'DELETE' }),
  renewMembership: (id, body) => request(`/memberships/${id}/renew`, { method: 'POST', body: body || {} }),
  cancelMembership: (id, body) => request(`/memberships/${id}/cancel`, { method: 'POST', body: body || {} }),
  suspendMembership: (id, body) => request(`/memberships/${id}/suspend`, { method: 'POST', body: body || {} }),

  // Classes
  listClasses: (params) => request(`/classes${buildQuery(params)}`),
  getClass: (id) => request(`/classes/${id}`),
  createClass: (body) => request('/classes', { method: 'POST', body }),
  updateClass: (id, body) => request(`/classes/${id}`, { method: 'PUT', body }),
  deleteClass: (id) => request(`/classes/${id}`, { method: 'DELETE' }),
  cancelClass: (id, body) => request(`/classes/${id}/cancel`, { method: 'POST', body: body || {} }),
  classRoster: (id) => request(`/classes/${id}/students`),
  registerStudent: (classId, body) => request(`/classes/${classId}/students`, { method: 'POST', body }),
  updateStudent: (classId, studentId, body) => request(`/classes/${classId}/students/${studentId}`, { method: 'PUT', body }),

  // Messages
  listMessages: (params) => request(`/messages${buildQuery(params)}`),
  getMessage: (id) => request(`/messages/${id}`),
  cancelMessage: (id) => request(`/messages/${id}/cancel`, { method: 'POST', body: {} }),
  retryMessage: (id) => request(`/messages/${id}/retry`, { method: 'POST', body: {} }),
  sendEmail: (body) => request('/messages/send-email', { method: 'POST', body }),
  sendSms: (body) => request('/messages/send-sms', { method: 'POST', body }),
  dispatchNow: () => request('/messages/dispatch-now', { method: 'POST', body: {} }),

  // Message templates
  listTemplates: (params) => request(`/message-templates${buildQuery(params)}`),
  upsertTemplate: (body) => request('/message-templates', { method: 'POST', body }),
  updateTemplate: (id, body) => request(`/message-templates/${id}`, { method: 'PUT', body }),
  deleteTemplate: (id) => request(`/message-templates/${id}`, { method: 'DELETE' }),
  previewTemplate: (body) => request('/message-templates/preview', { method: 'POST', body }),

  // Tags
  listTags: (params) => request(`/tags${buildQuery(params)}`),
  createTag: (body) => request('/tags', { method: 'POST', body }),
  updateTag: (id, body) => request(`/tags/${id}`, { method: 'PUT', body }),
  deleteTag: (id) => request(`/tags/${id}`, { method: 'DELETE' }),
  customerTags: (customerId) => request(`/customers/${customerId}/tags`),
  attachCustomerTag: (customerId, body) => request(`/customers/${customerId}/tags`, { method: 'POST', body }),
  detachCustomerTag: (customerId, tagId) => request(`/customers/${customerId}/tags/${tagId}`, { method: 'DELETE' }),

  // Notes
  listNotes: (params) => request(`/notes${buildQuery(params)}`),
  createNote: (body) => request('/notes', { method: 'POST', body }),
  updateNote: (id, body) => request(`/notes/${id}`, { method: 'PUT', body }),
  deleteNote: (id) => request(`/notes/${id}`, { method: 'DELETE' }),

  // Settings
  getSettings: () => request('/settings'),
  updateSettings: (body) => request('/settings', { method: 'PUT', body }),

  // Sensitive / compliance records
  sensitiveMeta: () => request('/sensitive-records/meta'),
  listSensitive: (params) => request(`/sensitive-records${buildQuery(params)}`),
  getSensitive: (id) => request(`/sensitive-records/${id}`),
  createSensitive: (body) => request('/sensitive-records', { method: 'POST', body }),
  updateSensitive: (id, body) => request(`/sensitive-records/${id}`, { method: 'PUT', body }),
  deleteSensitive: (id) => request(`/sensitive-records/${id}`, { method: 'DELETE' }),

  // Webhooks
  listWebhooks: (params) => request(`/webhooks${buildQuery(params)}`),
  getWebhook: (id) => request(`/webhooks/${id}`),
  createWebhook: (body) => request('/webhooks', { method: 'POST', body }),
  updateWebhook: (id, body) => request(`/webhooks/${id}`, { method: 'PUT', body }),
  deleteWebhook: (id) => request(`/webhooks/${id}`, { method: 'DELETE' }),
  testWebhook: (id) => request(`/webhooks/${id}/test`, { method: 'POST', body: {} }),
  webhookDeliveries: (id, params) => request(`/webhooks/${id}/deliveries${buildQuery(params)}`),
  webhookEvents: () => request('/webhooks/events'),
  webhookDispatchNow: () => request('/webhooks/dispatch-now', { method: 'POST', body: {} }),

  // Integrations (WooCommerce purchase sync)
  listIntegrations: (params) => request(`/integrations${buildQuery(params)}`),
  integrationStats: () => request('/integrations/stats'),
  listUnlinkedIntegrations: (params) => request(`/integrations/unlinked${buildQuery(params)}`),
  syncWooOrder: (orderId) => request('/integrations/woocommerce/sync', { method: 'POST', body: { order_id: orderId } }),
  backfillWooCustomer: (customerId) => request('/integrations/woocommerce/backfill', { method: 'POST', body: { customer_id: customerId } }),
  customerPurchases: (customerId, params) => request(`/customers/${customerId}/purchases${buildQuery(params)}`),

  // Bulk + saved views
  bulkCustomers: (action, ids, params) => request('/customers/bulk', { method: 'POST', body: { action, ids, params: params || {} } }),
  bulkLeads: (action, ids, params) => request('/leads/bulk', { method: 'POST', body: { action, ids, params: params || {} } }),
  listSavedViews: (resource) => request(`/saved-views${buildQuery({ resource })}`),
  createSavedView: (body) => request('/saved-views', { method: 'POST', body }),
  deleteSavedView: (id) => request(`/saved-views/${id}`, { method: 'DELETE' }),

  // Suppressions
  listSuppressions: (params) => request(`/suppressions${buildQuery(params)}`),
  createSuppression: (body) => request('/suppressions', { method: 'POST', body }),
  deleteSuppression: (id) => request(`/suppressions/${id}`, { method: 'DELETE' }),

  // Reports + audit
  dashboard: () => request('/reports/dashboard'),
  reportRevenue: (params) => request(`/reports/revenue${buildQuery(params)}`),
  reportPipeline: (params) => request(`/reports/pipeline${buildQuery(params)}`),
  reportOperations: (params) => request(`/reports/operations${buildQuery(params)}`),
  reportMembership: (params) => request(`/reports/membership${buildQuery(params)}`),
  reportStaff: (params) => request(`/reports/staff${buildQuery(params)}`),
  reportCommunications: (params) => request(`/reports/communications${buildQuery(params)}`),
  auditLogs: (params) => request(`/audit-logs${buildQuery(params)}`),
};

export default api;
