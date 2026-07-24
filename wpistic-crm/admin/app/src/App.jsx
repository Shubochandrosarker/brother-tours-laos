import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import Sidebar from './components/Sidebar.jsx';
import Topbar from './components/Topbar.jsx';
import Dashboard from './pages/Dashboard.jsx';
import Customers from './pages/Customers.jsx';
import CustomerDetail from './pages/CustomerDetail.jsx';
import Leads from './pages/Leads.jsx';
import LeadDetail from './pages/LeadDetail.jsx';
import Tasks from './pages/Tasks.jsx';
import Bookings from './pages/Bookings.jsx';
import BookingDetail from './pages/BookingDetail.jsx';
import Calendar from './pages/Calendar.jsx';
import Messages from './pages/Messages.jsx';
import MessageDetail from './pages/MessageDetail.jsx';
import Templates from './pages/Templates.jsx';
import Reports from './pages/Reports.jsx';
import RevenueReport from './pages/reports/RevenueReport.jsx';
import PipelineReport from './pages/reports/PipelineReport.jsx';
import OperationsReport from './pages/reports/OperationsReport.jsx';
import StaffReport from './pages/reports/StaffReport.jsx';
import CommunicationsReport from './pages/reports/CommunicationsReport.jsx';
import Exports from './pages/Exports.jsx';
import AuditLogs from './pages/AuditLogs.jsx';
import Settings from './pages/Settings.jsx';
import Webhooks from './pages/Webhooks.jsx';
import Integrations from './pages/Integrations.jsx';
import Suppressions from './pages/Suppressions.jsx';

// Firearms-era modules (Check-In, Waivers, Classes, Memberships, Compliance)
// are intentionally removed — this is a Tours CRM.
export default function App() {
  return (
    <div className="g2a-shell">
      <Sidebar />
      <div className="g2a-main">
        <Topbar />
        <main className="g2a-content">
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/calendar" element={<Calendar />} />
            <Route path="/bookings" element={<Bookings />} />
            <Route path="/bookings/:id" element={<BookingDetail />} />
            <Route path="/customers" element={<Customers />} />
            <Route path="/customers/:id" element={<CustomerDetail />} />
            <Route path="/leads" element={<Leads />} />
            <Route path="/leads/:id" element={<LeadDetail />} />
            <Route path="/tasks" element={<Tasks />} />
            <Route path="/messages" element={<Messages />} />
            <Route path="/messages/:id" element={<MessageDetail />} />
            <Route path="/templates" element={<Templates />} />
            <Route path="/reports" element={<Reports />} />
            <Route path="/reports/revenue" element={<RevenueReport />} />
            <Route path="/reports/pipeline" element={<PipelineReport />} />
            <Route path="/reports/operations" element={<OperationsReport />} />
            <Route path="/reports/staff" element={<StaffReport />} />
            <Route path="/reports/communications" element={<CommunicationsReport />} />
            <Route path="/exports" element={<Exports />} />
            <Route path="/webhooks" element={<Webhooks />} />
            <Route path="/integrations" element={<Integrations />} />
            <Route path="/suppressions" element={<Suppressions />} />
            <Route path="/audit-logs" element={<AuditLogs />} />
            <Route path="/settings" element={<Settings />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </main>
      </div>
    </div>
  );
}
