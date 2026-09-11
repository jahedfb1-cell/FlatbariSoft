import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { LanguageProvider, useLanguage } from './context/LanguageContext';
import LoginPage from './pages/LoginPage';
import HomePage from './pages/HomePage';
import FlatsPage from './pages/FlatsPage';
import FlatDetailPage from './pages/FlatDetailPage';
import NidPrintPage from './pages/NidPrintPage';

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
      <Route path="/" element={<HomePage />} />
      <Route path="/flats" element={<FlatsPage />} />
      <Route path="/flats/:id" element={<FlatDetailPage />} />
      <Route path="/print/nid" element={<NidPrintPage />} />
    </Routes>
  );
}

function App() {
  return (
    <BrowserRouter>
      <LanguageProvider>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </LanguageProvider>
    </BrowserRouter>
  );
}

export default App;
