import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import client from '../api/client';
import { useAuth } from '../context/AuthContext';

interface HealthResponse {
  status: string;
  app: string;
  time: string;
}

/**
 * Placeholder post-login screen. Replaced by the real dashboard/sidebar
 * shell in Phase 4.3 — for now it just proves the logged-in session works
 * (shows who you are, still reaches the API).
 */
export default function HomePage() {
  const { user, logout } = useAuth();
  const [health, setHealth] = useState<HealthResponse | null>(null);

  useEffect(() => {
    client.get<HealthResponse>('/health').then((res) => setHealth(res.data));
  }, []);

  return (
    <div className="flex min-h-screen items-center justify-center bg-brand-50 px-4">
      <div className="w-full max-w-sm rounded-xl border border-brand-200 bg-white p-8 shadow-sm">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-xl font-semibold text-navy-900">Green Valley BMS</h1>
            <p className="mt-1 text-sm text-navy-700">লগইন সফল হয়েছে</p>
          </div>
          <button
            onClick={() => logout()}
            className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 transition hover:bg-brand-100"
          >
            লগআউট
          </button>
        </div>

        <div className="mt-6 rounded-lg bg-brand-100 px-4 py-3 text-sm text-navy-900">
          <p>
            <span className="font-medium">মোবাইল:</span> {user?.mobile}
          </p>
          <p>
            <span className="font-medium">Role:</span> {user?.roles.join(', ')}
          </p>
          {health && (
            <p>
              <span className="font-medium">Server time:</span> {health.time}
            </p>
          )}
        </div>

        <Link
          to="/flats"
          className="mt-4 block rounded-lg border border-brand-200 px-4 py-2 text-center text-sm font-medium text-brand-700 transition hover:bg-brand-100"
        >
          ফ্ল্যাট ব্যবস্থাপনা →
        </Link>
      </div>
    </div>
  );
}
