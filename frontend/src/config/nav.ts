import type { TranslationKey } from '../i18n/translations';

/**
 * Every office screen, once. The sidebar shows them in these groups; the dashboard's quick-access tiles show the
 * same list flat. `perm` is the permission that opens the screen ("a|b" = either one; the API enforces it too),
 * and super_admin sees everything.
 */
export interface NavItem {
  to: string;
  key: TranslationKey;
  perm: string;
  icon: string;
}

export interface NavGroup {
  key: TranslationKey;
  items: NavItem[];
}

export const NAV_GROUPS: NavGroup[] = [
  {
    key: 'navgroup.people' as TranslationKey,
    items: [
      { to: '/flats', key: 'home.flatManagement', perm: 'flat.view', icon: '🏢' },
      { to: '/tenant-requests', key: 'nav.tenantRequests' as TranslationKey, perm: 'tenant.request|tenant.manage', icon: '🏠' },
      { to: '/visitors', key: 'nav.visitors' as TranslationKey, perm: 'visitor.view', icon: '🚪' },
      { to: '/staff', key: 'staff.title', perm: 'staff.view', icon: '👷' },
      { to: '/users', key: 'nav.users', perm: 'user.view', icon: '🔑' },
    ],
  },
  {
    key: 'navgroup.money' as TranslationKey,
    items: [
      { to: '/billing', key: 'nav.billing', perm: 'billing.view', icon: '🧾' },
      { to: '/payments', key: 'nav.payments', perm: 'payment.view', icon: '💳' },
      { to: '/expenses', key: 'nav.expenses', perm: 'expense.view', icon: '💸' },
      { to: '/budget', key: 'nav.budget' as TranslationKey, perm: 'budget.view', icon: '🎯' },
      { to: '/contributions', key: 'nav.contrib', perm: 'contribution.view', icon: '📒' },
      { to: '/dues', key: 'nav.dues', perm: 'due.view', icon: '🏗️' },
    ],
  },
  {
    key: 'navgroup.accounts' as TranslationKey,
    items: [
      { to: '/bank-accounts', key: 'bank.title', perm: 'accounting.view', icon: '🏦' },
      { to: '/statements?treasury=1', key: 'nav.treasury' as TranslationKey, perm: 'treasury.manage', icon: '🏛️' },
      { to: '/statements', key: 'nav.statements', perm: 'accounting.view', icon: '📊' },
      { to: '/reports', key: 'nav.reports', perm: 'accounting.view', icon: '📑' },
    ],
  },
  {
    key: 'navgroup.purchases' as TranslationKey,
    items: [
      { to: '/gas', key: 'nav.gas', perm: 'gas.view', icon: '🔥' },
      { to: '/construction-materials', key: 'nav.materials', perm: 'expense.view', icon: '🧱' },
      { to: '/suppliers', key: 'nav.suppliers' as TranslationKey, perm: 'expense.view', icon: '🚚' },
    ],
  },
  {
    key: 'navgroup.building' as TranslationKey,
    items: [
      { to: '/hall', key: 'nav.hall', perm: 'hall.view', icon: '🎉' },
      { to: '/parking', key: 'nav.parking', perm: 'parking.view', icon: '🅿️' },
      { to: '/assets', key: 'nav.assets', perm: 'asset.view', icon: '🛠️' },
      { to: '/meetings', key: 'nav.meetings', perm: 'meeting.view', icon: '🗓️' },
      { to: '/complaints', key: 'nav.complaints', perm: 'complaint.view', icon: '📣' },
      { to: '/notices', key: 'nav.notices', perm: 'notice.view', icon: '📌' },
    ],
  },
  {
    key: 'navgroup.system' as TranslationKey,
    items: [
      { to: '/audit-logs', key: 'nav.audit' as TranslationKey, perm: 'audit.view', icon: '🔍' },
      { to: '/settings', key: 'nav.settings' as TranslationKey, perm: 'setting.manage', icon: '⚙️' },
    ],
  },
];

export const ALL_NAV_ITEMS: NavItem[] = NAV_GROUPS.flatMap((g) => g.items);

/** Can this user open the screen? */
export function canOpen(item: NavItem, can: (permission: string) => boolean): boolean {
  return item.perm.split('|').some(can);
}
