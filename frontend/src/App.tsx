import { useEffect, useState } from 'react';
import client from './api/client';

interface HealthResponse {
  status: string;
  app: string;
  time: string;
}

/**
 * Phase 0.2 placeholder shell.
 *
 * This proves the frontend can actually reach the Laravel API (CORS,
 * base URL, axios client all wired correctly) before any real screens
 * exist. It will be replaced by the login screen in Phase 0.4 and the
 * real app shell/sidebar from Phase 4 onward.
 */
function App() {
  const [health, setHealth] = useState<HealthResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    client
      .get<HealthResponse>('/health')
      .then((res) => setHealth(res.data))
      .catch(() => setError('ব্যাকএন্ড সার্ভারে সংযোগ করা যায়নি।'));
  }, []);

  return (
    <div className="flex min-h-screen items-center justify-center bg-brand-50">
      <div className="rounded-xl border border-brand-200 bg-white p-8 shadow-sm">
        <h1 className="text-2xl font-semibold text-navy-900">Green Valley BMS</h1>
        <p className="mt-1 text-sm text-navy-700">ফাউন্ডেশন সেটআপ যাচাই — Phase 0.2</p>

        <div className="mt-6 rounded-lg bg-brand-100 px-4 py-3 text-sm">
          {error && <span className="text-status-due">{error}</span>}
          {!error && !health && <span className="text-navy-700">ব্যাকএন্ড থেকে সাড়া আসছে...</span>}
          {health && (
            <div className="text-navy-900">
              <p>
                <span className="font-medium">Status:</span> {health.status}
              </p>
              <p>
                <span className="font-medium">App:</span> {health.app}
              </p>
              <p>
                <span className="font-medium">Server time:</span> {health.time}
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default App;
