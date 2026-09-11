import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import * as flatsApi from '../api/flats';
import type { Flat } from '../api/flats';
import FlatFormModal from '../components/FlatFormModal';

const occupancyLabel: Record<Flat['occupancy_status'], string> = {
  OWNER_OCCUPIED: 'মালিক নিজে থাকেন',
  TENANTED: 'ভাড়াটিয়া আছে',
  EMPTY: 'খালি',
};

const occupancyClass: Record<Flat['occupancy_status'], string> = {
  OWNER_OCCUPIED: 'bg-brand-100 text-brand-700',
  TENANTED: 'bg-status-partial/15 text-status-partial',
  EMPTY: 'bg-navy-500/10 text-navy-700',
};

export default function FlatsPage() {
  const [flats, setFlats] = useState<Flat[]>([]);
  const [buildingId, setBuildingId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [search, setSearch] = useState('');

  function load() {
    setLoading(true);
    flatsApi.listFlats({ search: search || undefined }).then((data) => {
      setFlats(data);
      setLoading(false);
    });
  }

  useEffect(() => {
    flatsApi.listBuildings().then((buildings) => setBuildingId(buildings[0]?.id ?? null));
  }, []);

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← হোম
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">ফ্ল্যাট তালিকা</h1>
          </div>
          <button
            onClick={() => setShowForm(true)}
            disabled={!buildingId}
            className="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
          >
            + নতুন ফ্ল্যাট
          </button>
        </div>

        <input
          placeholder="ফ্ল্যাট নম্বর দিয়ে খুঁজুন..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="mb-4 w-full max-w-xs rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500"
        />

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-4 py-3">ফ্ল্যাট</th>
                <th className="px-4 py-3">ফ্লোর</th>
                <th className="px-4 py-3">অবস্থা</th>
                <th className="px-4 py-3">সার্ভিস চার্জ</th>
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-navy-700">
                    লোড হচ্ছে...
                  </td>
                </tr>
              )}
              {!loading && flats.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-navy-700">
                    কোনো ফ্ল্যাট পাওয়া যায়নি।
                  </td>
                </tr>
              )}
              {flats.map((flat) => (
                <tr key={flat.id} className="cursor-pointer border-t border-brand-100 transition hover:bg-brand-50">
                  <td className="px-4 py-3">
                    <Link to={`/flats/${flat.id}`} className="font-medium text-brand-700 hover:underline">
                      {flat.flat_no}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-navy-900">{flat.floor}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-1 text-xs font-medium ${occupancyClass[flat.occupancy_status]}`}>
                      {occupancyLabel[flat.occupancy_status]}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(flat.service_charge_rate).toLocaleString('en-BD')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {showForm && buildingId && (
        <FlatFormModal
          buildingId={buildingId}
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false);
            load();
          }}
        />
      )}
    </div>
  );
}
