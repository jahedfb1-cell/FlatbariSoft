import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import * as flatsApi from '../api/flats';
import type { Flat } from '../api/flats';
import AssignOwnerModal from '../components/AssignOwnerModal';
import AssignTenantModal from '../components/AssignTenantModal';
import FlatFormModal from '../components/FlatFormModal';
import DocumentManagerModal from '../components/DocumentManagerModal';

type DocTarget = { kind: 'owner'; personId: number } | { kind: 'tenant'; tenantId: number };

export default function FlatDetailPage() {
  const { id } = useParams();
  const flatId = Number(id);
  const [flat, setFlat] = useState<Flat | null>(null);
  const [modal, setModal] = useState<'owner' | 'tenant' | 'edit' | null>(null);
  const [docTarget, setDocTarget] = useState<DocTarget | null>(null);

  function load() {
    flatsApi.getFlat(flatId).then(setFlat);
  }

  useEffect(load, [flatId]);

  async function handleEndOwnership(flatPartyId: number) {
    if (!confirm('এই মালিকানার রেকর্ড শেষ করবেন?')) return;
    await flatsApi.endOwnership(flatId, flatPartyId);
    load();
  }

  async function handleVacate(tenantId: number) {
    if (!confirm('এই ভাড়াটিয়াকে ভ্যাকেট করবেন?')) return;
    await flatsApi.vacateTenant(tenantId);
    load();
  }

  if (!flat) {
    return <div className="flex min-h-screen items-center justify-center text-navy-700">লোড হচ্ছে...</div>;
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-2xl space-y-6">
        <Link to="/flats" className="text-sm text-navy-700 hover:underline">
          ← ফ্ল্যাট তালিকা
        </Link>

        <div className="rounded-xl border border-brand-200 bg-white p-6 shadow-sm">
          <div className="flex items-start justify-between">
            <div>
              <h1 className="text-xl font-semibold text-navy-900">ফ্ল্যাট {flat.flat_no}</h1>
              <p className="mt-1 text-sm text-navy-700">
                ফ্লোর {flat.floor} · {flat.size_sqft ?? '—'} sqft
              </p>
            </div>
            <button
              onClick={() => setModal('edit')}
              className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 transition hover:bg-brand-100"
            >
              এডিট
            </button>
          </div>
          <div className="mt-4 grid grid-cols-2 gap-2 text-sm text-navy-900">
            <p>সার্ভিস চার্জ: ৳{Number(flat.service_charge_rate).toLocaleString('en-BD')}</p>
            <p>খালি রেট: ৳{Number(flat.empty_flat_rate).toLocaleString('en-BD')}</p>
            <p>গ্যাস মিটার: {flat.gas_meter_no ?? '—'}</p>
            <p>অবস্থা: {flat.occupancy_status}</p>
          </div>
        </div>

        <div className="rounded-xl border border-brand-200 bg-white p-6 shadow-sm">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="font-semibold text-navy-900">মালিক</h2>
            <button
              onClick={() => setModal('owner')}
              className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm text-white transition hover:bg-brand-700"
            >
              + মালিক যোগ করুন
            </button>
          </div>
          {(!flat.current_owners || flat.current_owners.length === 0) && (
            <p className="text-sm text-navy-700">কোনো মালিক যোগ করা হয়নি।</p>
          )}
          {flat.current_owners?.map((party) => (
            <div key={party.id} className="flex items-center justify-between border-t border-brand-100 py-2 text-sm">
              <div>
                <p className="font-medium text-navy-900">{party.person.name_bn}</p>
                <p className="text-navy-700">
                  {party.person.mobile} · থেকে {party.from_date}
                  {party.is_billing_contact && ' · বিলিং কন্টাক্ট'}
                </p>
              </div>
              <div className="flex shrink-0 gap-3">
                <button
                  onClick={() => setDocTarget({ kind: 'owner', personId: party.person.id! })}
                  className="text-brand-700 hover:underline"
                >
                  ডকুমেন্ট
                </button>
                <button onClick={() => handleEndOwnership(party.id)} className="text-status-due hover:underline">
                  মালিকানা শেষ করুন
                </button>
              </div>
            </div>
          ))}
        </div>

        <div className="rounded-xl border border-brand-200 bg-white p-6 shadow-sm">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="font-semibold text-navy-900">ভাড়াটিয়া</h2>
            {(!flat.current_tenant || flat.current_tenant.length === 0) && (
              <button
                onClick={() => setModal('tenant')}
                className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm text-white transition hover:bg-brand-700"
              >
                + ভাড়াটিয়া যোগ করুন
              </button>
            )}
          </div>
          {(!flat.current_tenant || flat.current_tenant.length === 0) && (
            <p className="text-sm text-navy-700">কোনো সক্রিয় ভাড়াটিয়া নেই।</p>
          )}
          {flat.current_tenant?.map((tenant) => (
            <div key={tenant.id} className="flex items-center justify-between border-t border-brand-100 py-2 text-sm">
              <div>
                <p className="font-medium text-navy-900">{tenant.person.name_bn}</p>
                <p className="text-navy-700">
                  {tenant.person.mobile} · থেকে {tenant.start_date} · ভাড়া ৳
                  {tenant.monthly_rent ? Number(tenant.monthly_rent).toLocaleString('en-BD') : '—'}
                </p>
              </div>
              <div className="flex shrink-0 gap-3">
                <button
                  onClick={() => setDocTarget({ kind: 'tenant', tenantId: tenant.id })}
                  className="text-brand-700 hover:underline"
                >
                  ডকুমেন্ট
                </button>
                <button onClick={() => handleVacate(tenant.id)} className="text-status-due hover:underline">
                  ভ্যাকেট করুন
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

      {modal === 'owner' && (
        <AssignOwnerModal
          flatId={flat.id}
          onClose={() => setModal(null)}
          onSaved={() => {
            setModal(null);
            load();
          }}
        />
      )}
      {modal === 'tenant' && (
        <AssignTenantModal
          flatId={flat.id}
          onClose={() => setModal(null)}
          onSaved={() => {
            setModal(null);
            load();
          }}
        />
      )}
      {modal === 'edit' && (
        <FlatFormModal
          buildingId={flat.building_id}
          flat={flat}
          onClose={() => setModal(null)}
          onSaved={() => {
            setModal(null);
            load();
          }}
        />
      )}
      {docTarget?.kind === 'owner' && (
        <DocumentManagerModal
          kind="owner"
          personId={docTarget.personId}
          flatId={flat.id}
          onClose={() => setDocTarget(null)}
        />
      )}
      {docTarget?.kind === 'tenant' && (
        <DocumentManagerModal kind="tenant" tenantId={docTarget.tenantId} onClose={() => setDocTarget(null)} />
      )}
    </div>
  );
}
