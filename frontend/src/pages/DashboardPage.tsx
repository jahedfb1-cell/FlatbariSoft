import { useEffect, useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as dashApi from '../api/dashboard';
import type { DashboardOverview } from '../api/dashboard';

// `perm` is the permission that opens the screen (the API enforces it too); super_admin sees every tile.
const navItems: { to: string; key: any; perm: string; icon: string }[] = [
  { to: '/flats', key: 'home.flatManagement', perm: 'flat.view', icon: '🏢' },
  { to: '/billing', key: 'nav.billing', perm: 'billing.view', icon: '🧾' },
  { to: '/payments', key: 'nav.payments', perm: 'payment.view', icon: '💳' },
  { to: '/gas', key: 'nav.gas', perm: 'gas.view', icon: '🔥' },
  { to: '/contributions', key: 'nav.contrib', perm: 'contribution.view', icon: '📒' },
  { to: '/expenses', key: 'nav.expenses', perm: 'expense.view', icon: '💸' },
  { to: '/construction-materials', key: 'nav.materials', perm: 'expense.view', icon: '🧱' },
  { to: '/suppliers', key: 'nav.suppliers', perm: 'expense.view', icon: '🚚' },
  { to: '/bank-accounts', key: 'bank.title', perm: 'accounting.view', icon: '🏦' },
  { to: '/statements', key: 'nav.statements', perm: 'accounting.view', icon: '📊' },
  { to: '/reports', key: 'nav.reports', perm: 'accounting.view', icon: '📑' },
  { to: '/dues', key: 'nav.dues', perm: 'due.view', icon: '🏗️' },
  { to: '/hall', key: 'nav.hall', perm: 'hall.view', icon: '🎉' },
  { to: '/parking', key: 'nav.parking', perm: 'parking.view', icon: '🅿️' },
  { to: '/assets', key: 'nav.assets', perm: 'asset.view', icon: '🛠️' },
  { to: '/staff', key: 'staff.title', perm: 'staff.view', icon: '👷' },
  { to: '/meetings', key: 'nav.meetings', perm: 'meeting.view', icon: '🗓️' },
  { to: '/complaints', key: 'nav.complaints', perm: 'complaint.view', icon: '📣' },
  { to: '/notices', key: 'nav.notices', perm: 'notice.view', icon: '📌' },
  { to: '/users', key: 'nav.users', perm: 'user.view', icon: '🔑' },
];

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 0 })}`;
const num = (v: string | number) => Number(v).toLocaleString('en-BD', { maximumFractionDigits: 1 });
const MONTHS_EN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTHS_BN = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টে', 'অক্টো', 'নভে', 'ডিসে'];

export default function DashboardPage() {
  const { user, logout } = useAuth();
  const { t, lang } = useLanguage();
  const bn = lang === 'bn';
  const monthName = (m: number) => (bn ? MONTHS_BN : MONTHS_EN)[m - 1];

  const now = new Date();
  const [ym, setYm] = useState({ y: now.getFullYear(), m: now.getMonth() + 1 });
  const [data, setData] = useState<DashboardOverview | null>(null);
  const [failed, setFailed] = useState(false);

  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const visibleItems = navItems.filter((item) => can(item.perm));
  // a committee member is usually also a flat owner: let them hop to their own flat's portal
  const isResident = !!user && user.roles.some((r) => r === 'flat_owner' || r === 'tenant');

  useEffect(() => {
    let current = true;
    setFailed(false);
    dashApi
      .getOverview(ym.y, ym.m)
      .then((d) => current && setData(d))
      .catch(() => current && setFailed(true));
    return () => {
      current = false;
    };
  }, [ym]);

  const shift = (d: number) =>
    setYm(({ y, m }) => {
      const t0 = new Date(y, m - 1 + d, 1);
      return { y: t0.getFullYear(), m: t0.getMonth() + 1 };
    });
  const isThisMonth = ym.y === now.getFullYear() && ym.m === now.getMonth() + 1;

  const b = data?.billing;
  const attention: { icon: string; label: string; count: number; to: string; tone: 'due' | 'warn' }[] = [];
  if (data?.approvals?.payments) attention.push({ icon: '💳', label: t('dash.pendingPayments'), count: data.approvals.payments, to: '/payments', tone: 'warn' });
  if (data?.approvals?.expenses) attention.push({ icon: '💸', label: t('dash.pendingExpenses'), count: data.approvals.expenses, to: '/expenses', tone: 'warn' });
  if (data?.hall?.pending_requests) attention.push({ icon: '🎉', label: t('dash.hallRequests'), count: data.hall.pending_requests, to: '/hall', tone: 'warn' });
  if (data?.complaints?.open) attention.push({ icon: '📣', label: t('dash.openComplaints'), count: data.complaints.open, to: '/complaints', tone: data.complaints.urgent ? 'due' : 'warn' });

  return (
    <div className="min-h-screen bg-brand-50 pb-12">
      {/* header band */}
      <div className="bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 px-4 pb-24 pt-6 text-white">
        <div className="mx-auto flex max-w-6xl flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-sm text-brand-200">{t('dash.welcome')}</p>
            <h1 className="text-2xl font-semibold">{t('appName')}</h1>
            <p className="mt-1 text-sm text-brand-200">
              {user?.mobile}
              {data?.flats ? ` · ${data.flats.total} ${t('dash.flatsWord')}` : ''}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <div className="flex items-center rounded-lg bg-white/10">
              <button onClick={() => shift(-1)} className="px-3 py-1.5 hover:bg-white/10" aria-label="previous month">
                ‹
              </button>
              <span className="min-w-24 px-2 text-center font-medium">
                {monthName(ym.m)} {ym.y}
              </span>
              <button onClick={() => shift(1)} disabled={isThisMonth} className="px-3 py-1.5 hover:bg-white/10 disabled:opacity-30" aria-label="next month">
                ›
              </button>
            </div>
            <div className="[&_button]:border-white/30 [&_button]:text-white [&_button:hover]:bg-white/10">
              <LanguageToggle />
            </div>
            {isResident && (
              <Link to="/owner" className="rounded-lg border border-white/30 px-3 py-1.5 hover:bg-white/10">
                {t('users.openPortal')}
              </Link>
            )}
            <Link to="/change-password" className="rounded-lg border border-white/30 px-3 py-1.5 hover:bg-white/10">
              {t('password.change')}
            </Link>
            <button onClick={() => logout()} className="rounded-lg border border-white/30 px-3 py-1.5 hover:bg-white/10">
              {t('home.logout')}
            </button>
          </div>
        </div>
      </div>

      <div className="mx-auto -mt-16 max-w-6xl space-y-5 px-4">
        {failed && <div className="rounded-xl bg-white p-4 text-sm text-status-due shadow">{t('somethingWrong')}</div>}
        {!data && !failed && <div className="h-40 animate-pulse rounded-2xl bg-white/70 shadow" />}

        {/* headline numbers */}
        {data && (b || data.funds) && (
          <div className="grid gap-4 md:grid-cols-3">
            {b && (
              <Card className="md:col-span-1">
                <div className="flex items-center gap-4">
                  <Ring percent={b.percent} />
                  <div>
                    <p className="text-xs text-navy-700">{t('dash.collectionOf')} {monthName(data.month)}</p>
                    <p className="text-2xl font-semibold text-navy-900">{money(b.collected)}</p>
                    <p className="text-xs text-navy-700">
                      {t('dash.ofBilled')} {money(b.billed)}
                    </p>
                  </div>
                </div>
                {b.has_bills ? (
                  <div className="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                    <Chip color="paid" n={b.paid_flats} label={t('dash.paidFlats')} />
                    <Chip color="partial" n={b.partial_flats} label={t('dash.partialFlats')} />
                    <Chip color="due" n={b.unpaid_flats} label={t('dash.unpaidFlats')} />
                  </div>
                ) : (
                  <p className="mt-4 text-xs text-navy-700">{t('dash.noBills')}</p>
                )}
                <p className="mt-3 border-t border-brand-100 pt-2 text-xs text-navy-700">
                  {t('dash.allTimeDue')}: <b className="text-status-due">{money(b.outstanding_all)}</b>
                </p>
              </Card>
            )}

            {data.funds && (
              <Card className={b ? 'md:col-span-2' : 'md:col-span-3'}>
                <div className="flex items-baseline justify-between">
                  <h2 className="text-sm font-semibold text-navy-900">{t('dash.funds')}</h2>
                  <p className="text-lg font-semibold text-navy-900">{money(data.funds.total_closing)}</p>
                </div>
                <div className="mt-3 space-y-3">
                  {data.funds.funds.map((f) => {
                    const total = Math.max(Number(data.funds!.total_closing), 1);
                    const pct = Math.min(100, Math.max(0, (Number(f.closing) / total) * 100));
                    return (
                      <div key={f.code}>
                        <div className="flex justify-between text-sm">
                          <span className="text-navy-900">{bn ? f.name_bn : f.name_en}</span>
                          <span className={`font-medium ${Number(f.closing) < 0 ? 'text-status-due' : 'text-navy-900'}`}>{money(f.closing)}</span>
                        </div>
                        <div className="mt-1 h-2 overflow-hidden rounded-full bg-brand-100">
                          <div className="h-full rounded-full bg-brand-500" style={{ width: `${pct}%` }} />
                        </div>
                        <p className="mt-0.5 text-[11px] text-navy-700">
                          {t('dash.cash')} {money(f.cash)} · {t('dash.bank')} {money(f.bank)}
                        </p>
                      </div>
                    );
                  })}
                </div>
              </Card>
            )}
          </div>
        )}

        {/* needs attention */}
        {attention.length > 0 && (
          <div className="flex flex-wrap gap-2">
            {attention.map((a) => (
              <Link
                key={a.label}
                to={a.to}
                className={`flex items-center gap-2 rounded-full border px-4 py-2 text-sm shadow-sm transition hover:shadow ${
                  a.tone === 'due' ? 'border-status-due/40 bg-red-50 text-status-due' : 'border-status-partial/40 bg-amber-50 text-status-partial'
                }`}
              >
                <span>{a.icon}</span>
                <b>{a.count}</b> {a.label}
              </Link>
            ))}
          </div>
        )}

        {/* detail cards */}
        {data && (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {b && (
              <Card>
                <h2 className="mb-3 text-sm font-semibold text-navy-900">{t('dash.trend')}</h2>
                <Trend items={b.trend} label={monthName} />
              </Card>
            )}

            {data.contributions && data.contributions.length > 0 && (
              <Card>
                <h2 className="mb-3 text-sm font-semibold text-navy-900">{t('dash.collections')}</h2>
                <div className="space-y-3">
                  {data.contributions.map((c) => (
                    <div key={c.code}>
                      <div className="flex justify-between text-sm">
                        <span className="text-navy-900">{bn ? c.name_bn : c.name_en}</span>
                        <span className="font-medium text-navy-900">{money(c.collected)}</span>
                      </div>
                      {c.has_target && c.percent !== null ? (
                        <>
                          <div className="mt-1 h-2 overflow-hidden rounded-full bg-brand-100">
                            <div className="h-full rounded-full bg-status-paid" style={{ width: `${Math.min(100, c.percent)}%` }} />
                          </div>
                          <p className="mt-0.5 text-[11px] text-navy-700">
                            {c.percent}% {t('dash.ofTarget')} {money(c.target)}
                          </p>
                        </>
                      ) : (
                        <p className="mt-0.5 text-[11px] text-navy-700">{t('dash.noTarget')}</p>
                      )}
                    </div>
                  ))}
                </div>
              </Card>
            )}

            {data.gas && (
              <Card>
                <h2 className="mb-3 text-sm font-semibold text-navy-900">🔥 {t('nav.gas')}</h2>
                <Stat label={t('gas.sumTotalPurchase')} value={`${num(data.gas.total_kg)} kg`} sub={money(data.gas.total_cost)} />
                <Stat label={t('gas.sumFlatsUsed')} value={`${num(data.gas.flats_used_m3)} m³`} sub={`${data.gas.flats_read}/${data.gas.flats_total} ${t('gas.sumFlatsRead')}`} />
                {data.gas.loss_percent !== null && <Stat label={t('gas.sumLoss')} value={`${data.gas.loss_percent}%`} sub="" />}
              </Card>
            )}

            {data.parking && (
              <Card>
                <h2 className="mb-3 text-sm font-semibold text-navy-900">🅿️ {t('nav.parking')}</h2>
                <Stat label={t('dash.slots')} value={`${data.parking.slots_taken}/${data.parking.slots_total}`} sub={t('dash.slotsTaken')} />
                <Stat label={t('dash.charged')} value={money(data.parking.charged)} sub={`${t('dash.collected')} ${money(data.parking.collected)}`} />
                <Stat label={t('dash.due')} value={money(data.parking.due)} sub="" tone={Number(data.parking.due) > 0 ? 'due' : undefined} />
              </Card>
            )}

            {data.supplier_owed && (
              <Card>
                <h2 className="mb-3 text-sm font-semibold text-navy-900">🚚 {t('dash.supplierOwed')}</h2>
                <Stat label={t('suppliers.materials')} value={money(data.supplier_owed.materials)} sub="" tone={Number(data.supplier_owed.materials) > 0 ? 'due' : undefined} />
                <Stat label={t('suppliers.gas')} value={money(data.supplier_owed.gas)} sub="" tone={Number(data.supplier_owed.gas) > 0 ? 'due' : undefined} />
                <Link to="/suppliers" className="mt-2 inline-block text-xs text-brand-700 hover:underline">
                  {t('dash.openLedgers')} →
                </Link>
              </Card>
            )}

            {data.hall && (
              <Card>
                <h2 className="mb-3 text-sm font-semibold text-navy-900">🎉 {t('dash.upcomingHall')}</h2>
                {data.hall.upcoming.length === 0 && <p className="text-sm text-navy-700">{t('dash.noneUpcoming')}</p>}
                <ul className="space-y-2">
                  {data.hall.upcoming.map((h) => (
                    <li key={h.id} className="flex items-center justify-between text-sm">
                      <span className="text-navy-900">
                        {h.date} · {h.event || h.customer}
                      </span>
                      <span className={`rounded-full px-2 py-0.5 text-[11px] ${h.status === 'PENDING' ? 'bg-amber-100 text-status-partial' : 'bg-green-100 text-status-paid'}`}>{h.status}</span>
                    </li>
                  ))}
                </ul>
              </Card>
            )}

            {data.recent_payments && (
              <Card>
                <h2 className="mb-3 text-sm font-semibold text-navy-900">💳 {t('dash.recentPayments')}</h2>
                {data.recent_payments.length === 0 && <p className="text-sm text-navy-700">{t('dash.nonePayments')}</p>}
                <ul className="space-y-2">
                  {data.recent_payments.map((p) => (
                    <li key={p.id} className="flex items-center justify-between text-sm">
                      <span className="text-navy-900">
                        <Link to={`/flats/${p.flat_id}`} className="font-medium text-brand-700 hover:underline">
                          {p.flat_no}
                        </Link>{' '}
                        <span className="text-xs text-navy-700">{p.date}</span>
                      </span>
                      <span className={p.status === 'PENDING' ? 'text-status-partial' : 'text-status-paid'}>{money(p.amount)}</span>
                    </li>
                  ))}
                </ul>
              </Card>
            )}

            {data.next_meeting !== undefined && (
              <Card>
                <h2 className="mb-3 text-sm font-semibold text-navy-900">🗓️ {t('dash.nextMeeting')}</h2>
                {data.next_meeting ? (
                  <>
                    <p className="font-medium text-navy-900">{data.next_meeting.title}</p>
                    <p className="text-sm text-navy-700">
                      {data.next_meeting.date}
                      {data.next_meeting.venue ? ` · ${data.next_meeting.venue}` : ''}
                    </p>
                  </>
                ) : (
                  <p className="text-sm text-navy-700">{t('dash.noMeeting')}</p>
                )}
              </Card>
            )}
          </div>
        )}

        {/* everything else lives behind these tiles */}
        <div>
          <h2 className="mb-3 text-sm font-semibold text-navy-900">{t('dash.quickAccess')}</h2>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
            {visibleItems.map((item) => (
              <Link
                key={item.to}
                to={item.to}
                className="group flex items-center gap-3 rounded-xl border border-brand-200 bg-white px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-400 hover:shadow-md"
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-100 text-xl transition group-hover:bg-brand-200">{item.icon}</span>
                <span className="text-sm font-medium text-navy-900">{t(item.key)}</span>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`rounded-2xl border border-brand-100 bg-white p-5 shadow-sm ${className}`}>{children}</div>;
}

function Stat({ label, value, sub, tone }: { label: string; value: string; sub: string; tone?: 'due' }) {
  return (
    <div className="flex items-baseline justify-between border-t border-brand-100 py-2 first:border-t-0 first:pt-0">
      <div>
        <p className="text-xs text-navy-700">{label}</p>
        {sub && <p className="text-[11px] text-navy-700">{sub}</p>}
      </div>
      <p className={`text-base font-semibold ${tone === 'due' ? 'text-status-due' : 'text-navy-900'}`}>{value}</p>
    </div>
  );
}

function Chip({ color, n, label }: { color: 'paid' | 'partial' | 'due'; n: number; label: string }) {
  const cls = { paid: 'bg-green-50 text-status-paid', partial: 'bg-amber-50 text-status-partial', due: 'bg-red-50 text-status-due' }[color];
  return (
    <div className={`rounded-lg px-2 py-2 ${cls}`}>
      <p className="text-lg font-semibold">{n}</p>
      <p>{label}</p>
    </div>
  );
}

/** Collection rate as a ring; "—" when nothing has been billed. */
function Ring({ percent }: { percent: number | null }) {
  const r = 34;
  const c = 2 * Math.PI * r;
  const p = Math.min(100, Math.max(0, percent ?? 0));
  return (
    <svg viewBox="0 0 80 80" className="h-24 w-24 shrink-0">
      <circle cx="40" cy="40" r={r} fill="none" strokeWidth="9" className="stroke-brand-100" />
      {p > 0 && (
        <circle
          cx="40"
          cy="40"
          r={r}
          fill="none"
          strokeWidth="9"
          strokeLinecap="round"
          className="stroke-brand-500"
          strokeDasharray={`${(p / 100) * c} ${c}`}
          transform="rotate(-90 40 40)"
        />
      )}
      <text x="40" y="45" textAnchor="middle" className="fill-navy-900 text-[15px] font-semibold">
        {percent === null ? '—' : `${percent}%`}
      </text>
    </svg>
  );
}

/** Six months of billed (light) against collected (solid). */
function Trend({ items, label }: { items: { year: number; month: number; billed: string; paid: string }[]; label: (m: number) => string }) {
  const max = Math.max(1, ...items.map((i) => Number(i.billed)));
  return (
    <div className="flex h-36 items-end gap-3">
      {items.map((i) => (
        <div key={`${i.year}-${i.month}`} className="flex flex-1 flex-col items-center gap-1" title={`${money(i.paid)} / ${money(i.billed)}`}>
          <div className="relative flex h-28 w-full items-end overflow-hidden rounded-t-md bg-brand-50">
            <div className="absolute bottom-0 w-full rounded-t-md bg-brand-200" style={{ height: `${(Number(i.billed) / max) * 100}%` }} />
            <div className="absolute bottom-0 w-full rounded-t-md bg-brand-500" style={{ height: `${(Number(i.paid) / max) * 100}%` }} />
          </div>
          <span className="text-[11px] text-navy-700">{label(i.month)}</span>
        </div>
      ))}
    </div>
  );
}
