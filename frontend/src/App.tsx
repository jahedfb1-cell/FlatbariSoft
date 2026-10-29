import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { LanguageProvider, useLanguage } from './context/LanguageContext';
import SettingsPage from './pages/SettingsPage';
import AuditLogsPage from './pages/AuditLogsPage';
import VisitorsPage from './pages/VisitorsPage';
import BudgetPage from './pages/BudgetPage';
import OwnerPaymentsPage from './pages/owner/OwnerPaymentsPage';
import OwnerVisitorsPage from './pages/owner/OwnerVisitorsPage';
import ResponsiveTables from './components/ResponsiveTables';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import FlatsPage from './pages/FlatsPage';
import FlatDetailPage from './pages/FlatDetailPage';
import NidPrintPage from './pages/NidPrintPage';
import StaffPage from './pages/StaffPage';
import BillingPage from './pages/BillingPage';
import GasPage from './pages/GasPage';
import PaymentsPage from './pages/PaymentsPage';
import ExpensesPage from './pages/ExpensesPage';
import DuesPage from './pages/DuesPage';
import AssetsPage from './pages/AssetsPage';
import MeetingsPage from './pages/MeetingsPage';
import ComplaintsPage from './pages/ComplaintsPage';
import NoticesPage from './pages/NoticesPage';
import ReportsPage from './pages/ReportsPage';
import BankAccountsPage from './pages/BankAccountsPage';
import ChangePasswordPage from './pages/ChangePasswordPage';
import ConstructionMaterialsPage from './pages/ConstructionMaterialsPage';
import OwnerHomePage from './pages/owner/OwnerHomePage';
import OwnerBillsPage from './pages/owner/OwnerBillsPage';
import OwnerNoticesPage from './pages/owner/OwnerNoticesPage';
import OwnerComplaintsPage from './pages/owner/OwnerComplaintsPage';
import OwnerDocumentsPage from './pages/owner/OwnerDocumentsPage';
import HallPage from './pages/HallPage';
import UsersPage from './pages/UsersPage';
import ParkingPage from './pages/ParkingPage';
import GasLedgerPage from './pages/GasLedgerPage';
import SuppliersPage from './pages/SuppliersPage';
import ContributionsPage from './pages/ContributionsPage';
import StatementsPage from './pages/StatementsPage';
import OwnerContributionsPage from './pages/owner/OwnerContributionsPage';
import OwnerParkingPage from './pages/owner/OwnerParkingPage';
import OwnerHallPage from './pages/owner/OwnerHallPage';

const OWNER_ROLES = ['flat_owner', 'tenant'];

function HomeRedirect() {
  const { user } = useAuth();
  // Check both the spatie roles array (what actually gates permissions)
  // and the denormalized `role` column (a display label that's always set,
  // even in the rare case a roles assignment was missed) so routing never
  // silently falls through to the admin dashboard for an owner/tenant.
  // A committee member who is also a flat owner holds both kinds of role: they belong on the office
  // dashboard (which links to their flat's portal), not trapped in the portal.
  const residentOnly = user ? user.roles.length > 0 && user.roles.every((r) => OWNER_ROLES.includes(r)) : false;
  const legacyOwner = user ? user.roles.length === 0 && OWNER_ROLES.includes(user.role) : false;
  return residentOnly || legacyOwner ? <Navigate to="/owner" replace /> : <DashboardPage />;
}

function AppRoutes() {
  const { user, loading } = useAuth();
  const { t } = useLanguage();

  if (loading) {
    return <div className="flex min-h-screen items-center justify-center text-navy-700">{t('loading')}</div>;
  }

  if (!user) {
    return (
      <Routes>
        <Route path="*" element={<LoginPage />} />
      </Routes>
    );
  }

  return (
    <Routes>
      <Route path="/login" element={<Navigate to="/" replace />} />
      <Route path="/" element={<HomeRedirect />} />
      <Route path="/change-password" element={<ChangePasswordPage />} />

      {/* Admin/Manager */}
      <Route path="/flats" element={<FlatsPage />} />
      <Route path="/flats/:id" element={<FlatDetailPage />} />
      <Route path="/print/nid" element={<NidPrintPage />} />
      <Route path="/staff" element={<StaffPage />} />
      <Route path="/billing" element={<BillingPage />} />
      <Route path="/gas" element={<GasPage />} />
      <Route path="/gas/ledger" element={<GasLedgerPage />} />
      <Route path="/suppliers" element={<SuppliersPage />} />
      <Route path="/settings" element={<SettingsPage />} />
      <Route path="/audit-logs" element={<AuditLogsPage />} />
      <Route path="/visitors" element={<VisitorsPage />} />
      <Route path="/budget" element={<BudgetPage />} />
      <Route path="/payments" element={<PaymentsPage />} />
      <Route path="/expenses" element={<ExpensesPage />} />
      <Route path="/dues" element={<DuesPage />} />
      <Route path="/assets" element={<AssetsPage />} />
      <Route path="/meetings" element={<MeetingsPage />} />
      <Route path="/complaints" element={<ComplaintsPage />} />
      <Route path="/notices" element={<NoticesPage />} />
      <Route path="/reports" element={<ReportsPage />} />
      <Route path="/bank-accounts" element={<BankAccountsPage />} />
      <Route path="/construction-materials" element={<ConstructionMaterialsPage />} />
      <Route path="/hall" element={<HallPage />} />
      <Route path="/users" element={<UsersPage />} />
      <Route path="/parking" element={<ParkingPage />} />
      <Route path="/contributions" element={<ContributionsPage />} />
      <Route path="/statements" element={<StatementsPage />} />

      {/* Owner/Tenant portal (5 screens, PRD's mobile-first cap) */}
      <Route path="/owner" element={<OwnerHomePage />} />
      <Route path="/owner/bills" element={<OwnerBillsPage />} />
      <Route path="/owner/notices" element={<OwnerNoticesPage />} />
      <Route path="/owner/complaints" element={<OwnerComplaintsPage />} />
      <Route path="/owner/documents" element={<OwnerDocumentsPage />} />
      <Route path="/owner/hall" element={<OwnerHallPage />} />
      <Route path="/owner/parking" element={<OwnerParkingPage />} />
      <Route path="/owner/payments" element={<OwnerPaymentsPage />} />
      <Route path="/owner/visitors" element={<OwnerVisitorsPage />} />
      <Route path="/owner/contributions" element={<OwnerContributionsPage />} />
    </Routes>
  );
}

function App() {
  return (
    <BrowserRouter>
      <LanguageProvider>
        <AuthProvider>
          <ResponsiveTables />
          <AppRoutes />
        </AuthProvider>
      </LanguageProvider>
    </BrowserRouter>
  );
}

export default App;
