import React, { useEffect, useState } from 'react';
import { api } from '../api.js';

const SMS_PROVIDERS = ['log_only', 'twilio'];
const ASSIGN_STRATEGIES = ['round_robin', 'first_available'];
const TASK_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
const INTEREST_TYPES = ['range_visit', 'membership', 'training', 'private_lesson', 'retail', 'ffl_transfer', 'group_event', 'general'];

export default function Settings() {
  const caps = api.capabilities;
  const [settings, setSettings] = useState(null);
  const [original, setOriginal] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [savedAt, setSavedAt] = useState(null);

  const load = () => {
    setLoading(true);
    api
      .getSettings()
      .then((res) => {
        setSettings(res);
        setOriginal(res);
        setError(null);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  const set = (key, value) => setSettings({ ...settings, [key]: value });
  const setList = (key, raw) => set(key, raw.split(/[,\s]+/).map((s) => s.trim()).filter(Boolean));

  const save = async () => {
    if (!settings) return;
    setSaving(true);
    setError(null);
    try {
      const patch = {};
      Object.keys(settings).forEach((k) => {
        if (JSON.stringify(settings[k]) !== JSON.stringify(original[k])) {
          patch[k] = settings[k];
        }
      });
      const next = await api.updateSettings(patch);
      setSettings(next);
      setOriginal(next);
      setSavedAt(new Date().toLocaleTimeString());
    } catch (e) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  if (!caps.manage_settings) {
    return <div className="g2a-error">You do not have permission to view settings.</div>;
  }

  if (loading || !settings) return <div className="g2a-loading">Loading settings…</div>;

  const listValue = (key) => (Array.isArray(settings[key]) ? settings[key].join(', ') : '');

  return (
    <div>
      <div className="g2a-toolbar">
        <div>
          <h2 style={{ margin: 0, fontSize: 22, color: 'var(--text)' }}>Settings</h2>
          <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginTop: 4 }}>
            CRM configuration, messaging providers, and compliance defaults.
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          {savedAt && <span style={{ fontSize: 12, color: 'var(--success, #33C27F)' }}>Saved at {savedAt}</span>}
          <button className="g2a-btn primary" onClick={save} disabled={saving}>{saving ? 'Saving…' : 'Save changes'}</button>
        </div>
      </div>

      {error && <div className="g2a-error">{error}</div>}

      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <section className="g2a-card">
          <h4 className="g2a-section-title">Business</h4>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Business name</label><input className="g2a-input" value={settings.business_name || ''} onChange={(e) => set('business_name', e.target.value)} /></div>
            <div className="g2a-field"><label>Timezone</label><input className="g2a-input" value={settings.business_timezone || ''} onChange={(e) => set('business_timezone', e.target.value)} placeholder="America/New_York" /></div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Business email</label><input className="g2a-input" value={settings.business_email || ''} onChange={(e) => set('business_email', e.target.value)} /></div>
            <div className="g2a-field"><label>Business phone</label><input className="g2a-input" value={settings.business_phone || ''} onChange={(e) => set('business_phone', e.target.value)} /></div>
          </div>
        </section>

        <section className="g2a-card">
          <h4 className="g2a-section-title">Email</h4>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>From name</label><input className="g2a-input" value={settings.email_from_name || ''} onChange={(e) => set('email_from_name', e.target.value)} /></div>
            <div className="g2a-field"><label>From address</label><input className="g2a-input" value={settings.email_from_address || ''} onChange={(e) => set('email_from_address', e.target.value)} /></div>
          </div>
        </section>

        <section className="g2a-card">
          <h4 className="g2a-section-title">SMS / Twilio</h4>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Provider</label>
              <select className="g2a-select" value={settings.sms_provider || 'log_only'} onChange={(e) => set('sms_provider', e.target.value)}>
                {SMS_PROVIDERS.map((p) => <option key={p} value={p}>{p}</option>)}
              </select>
            </div>
            <div className="g2a-field"><label>From number</label><input className="g2a-input" value={settings.sms_twilio_from || ''} onChange={(e) => set('sms_twilio_from', e.target.value)} placeholder="+15555550123" /></div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Twilio SID</label><input className="g2a-input" value={settings.sms_twilio_sid || ''} onChange={(e) => set('sms_twilio_sid', e.target.value)} /></div>
            <div className="g2a-field">
              <label>Twilio Auth Token</label>
              <input
                className="g2a-input"
                type="password"
                value={settings.sms_twilio_token || ''}
                onChange={(e) => set('sms_twilio_token', e.target.value)}
                placeholder="Stored encrypted on save"
              />
              <small style={{ color: 'var(--text-secondary)', fontSize: 11 }}>Leave the masked value untouched to keep the existing token.</small>
            </div>
          </div>
          <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <input id="test-mode" type="checkbox" checked={!!settings.messaging_test_mode} onChange={(e) => set('messaging_test_mode', e.target.checked)} />
            <label htmlFor="test-mode" style={{ margin: 0 }}>Test mode — log outbound messages but do not actually send</label>
          </div>
        </section>

        <section className="g2a-card">
          <h4 className="g2a-section-title">Operational defaults</h4>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Waiver expiry (days)</label><input className="g2a-input" type="number" value={settings.waiver_expiry_days || 0} onChange={(e) => set('waiver_expiry_days', parseInt(e.target.value || '0', 10))} /></div>
            <div className="g2a-field"><label>Waiver warning (days before expiry)</label><input className="g2a-input" type="number" value={settings.waiver_warn_days || 0} onChange={(e) => set('waiver_warn_days', parseInt(e.target.value || '0', 10))} /></div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Booking reminder lead (hours)</label><input className="g2a-input" type="number" value={settings.booking_reminder_lead_hours || 0} onChange={(e) => set('booking_reminder_lead_hours', parseInt(e.target.value || '0', 10))} /></div>
            <div className="g2a-field"><label>Class reminder lead (hours)</label><input className="g2a-input" type="number" value={settings.class_reminder_lead_hours || 0} onChange={(e) => set('class_reminder_lead_hours', parseInt(e.target.value || '0', 10))} /></div>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field"><label>Lead follow-up (hours)</label><input className="g2a-input" type="number" value={settings.lead_follow_up_hours || 0} onChange={(e) => set('lead_follow_up_hours', parseInt(e.target.value || '0', 10))} /></div>
            <div className="g2a-field"><label>Renewal warning (days)</label><input className="g2a-input" type="number" value={settings.renewal_warn_days || 0} onChange={(e) => set('renewal_warn_days', parseInt(e.target.value || '0', 10))} /></div>
          </div>
          <div className="g2a-field"><label>Waiver-required booking types</label><input className="g2a-input" value={listValue('waiver_required_booking_types')} onChange={(e) => setList('waiver_required_booking_types', e.target.value)} placeholder="range_lane, class, private_training" /></div>
          <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <input id="kiosk" type="checkbox" checked={!!settings.kiosk_enabled} onChange={(e) => set('kiosk_enabled', e.target.checked)} />
            <label htmlFor="kiosk" style={{ margin: 0 }}>Enable public kiosk waiver flow</label>
          </div>
        </section>

        <section className="g2a-card">
          <h4 className="g2a-section-title">Lead routing</h4>
          <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <input id="aa-enabled" type="checkbox" checked={!!settings.lead_auto_assign_enabled} onChange={(e) => set('lead_auto_assign_enabled', e.target.checked)} />
            <label htmlFor="aa-enabled" style={{ margin: 0 }}>Auto-assign new leads to staff</label>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field">
              <label>Strategy</label>
              <select className="g2a-select" value={settings.lead_auto_assign_strategy || 'round_robin'} onChange={(e) => set('lead_auto_assign_strategy', e.target.value)}>
                {ASSIGN_STRATEGIES.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
              </select>
              <small style={{ color: 'var(--text-secondary)', fontSize: 11 }}>
                <strong>Round-robin</strong> cycles fairly through the pool. <strong>First available</strong> always assigns to the first user listed.
              </small>
            </div>
            <div className="g2a-field">
              <label>Follow-up task priority</label>
              <select className="g2a-select" value={settings.lead_followup_task_priority || 'normal'} onChange={(e) => set('lead_followup_task_priority', e.target.value)}>
                {TASK_PRIORITIES.map((p) => <option key={p} value={p}>{p}</option>)}
              </select>
            </div>
          </div>
          <div className="g2a-field">
            <label>Global pool (WordPress user IDs, comma-separated)</label>
            <input
              className="g2a-input"
              value={Array.isArray(settings.lead_auto_assign_pool) ? settings.lead_auto_assign_pool.join(', ') : ''}
              onChange={(e) => setList('lead_auto_assign_pool', e.target.value)}
              placeholder="e.g. 12, 18, 24"
            />
            <small style={{ color: 'var(--text-secondary)', fontSize: 11 }}>
              Fallback pool used when no per-interest route matches. Users must have the <code>g2a_crm_view_leads</code> capability or they're skipped silently.
            </small>
          </div>
          <div className="g2a-field">
            <label>Per-interest routes</label>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
              {INTEREST_TYPES.map((interest) => {
                const value = (settings.lead_auto_assign_routes && settings.lead_auto_assign_routes[interest]) || [];
                const text = Array.isArray(value) ? value.join(', ') : '';
                return (
                  <div key={interest} style={{ display: 'grid', gridTemplateColumns: '160px 1fr', gap: 8, alignItems: 'center' }}>
                    <span style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{interest.replace(/_/g, ' ')}</span>
                    <input
                      className="g2a-input"
                      value={text}
                      onChange={(e) => {
                        const ids = e.target.value
                          .split(/[,\s]+/)
                          .map((v) => parseInt(v, 10))
                          .filter((v) => v > 0);
                        set('lead_auto_assign_routes', { ...(settings.lead_auto_assign_routes || {}), [interest]: ids });
                      }}
                      placeholder="user IDs, e.g. 12, 18"
                    />
                  </div>
                );
              })}
            </div>
            <small style={{ color: 'var(--text-secondary)', fontSize: 11 }}>
              Leave any row blank to fall back to the global pool for that interest type.
            </small>
          </div>
          <div className="g2a-form-row">
            <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
              <input id="aa-match" type="checkbox" checked={!!settings.lead_match_customer_on_create} onChange={(e) => set('lead_match_customer_on_create', e.target.checked)} />
              <label htmlFor="aa-match" style={{ margin: 0 }}>Match new leads to an existing customer by email / phone</label>
            </div>
            <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
              <input id="aa-task" type="checkbox" checked={!!settings.lead_create_followup_task} onChange={(e) => set('lead_create_followup_task', e.target.checked)} />
              <label htmlFor="aa-task" style={{ margin: 0 }}>Create a follow-up task on the assignee</label>
            </div>
          </div>
          <div className="g2a-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <input id="aa-notify" type="checkbox" checked={!!settings.lead_notify_assignee} onChange={(e) => set('lead_notify_assignee', e.target.checked)} />
            <label htmlFor="aa-notify" style={{ margin: 0 }}>Email the assignee when a new lead is assigned</label>
          </div>
        </section>

        <section className="g2a-card">
          <h4 className="g2a-section-title">Compliance</h4>
          <div className="g2a-field"><label>Workflow review required for</label><input className="g2a-input" value={listValue('compliance_review_required_for')} onChange={(e) => setList('compliance_review_required_for', e.target.value)} placeholder="ffl_transfer" /></div>
          <div className="g2a-field">
            <label>Roles allowed to view sensitive records</label>
            <input className="g2a-input" value={listValue('restrict_sensitive_view_to_roles')} onChange={(e) => setList('restrict_sensitive_view_to_roles', e.target.value)} placeholder="administrator, crm_owner, crm_compliance_officer" />
            <small style={{ color: 'var(--text-secondary)', fontSize: 11 }}>WordPress role slugs, comma-separated. This list does not grant capabilities — it filters visibility within the CRM.</small>
          </div>
        </section>
      </div>
    </div>
  );
}
