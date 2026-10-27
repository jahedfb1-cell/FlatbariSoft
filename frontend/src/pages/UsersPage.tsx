import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import type { TranslationKey } from '../i18n/translations';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import { apiError } from '../api/hall';
import * as usersApi from '../api/users';
import type { ManagedUser, PermissionGroup, RoleInfo } from '../api/users';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2';
const btn = 'rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100';

export default function UsersPage() {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const canManageUsers = can('user.manage');
  const canManageRoles = can('role.manage');

  const [tab, setTab] = useState<'users' | 'roles'>('users');
  const [roles, setRoles] = useState<RoleInfo[]>([]);
  const [catalog, setCatalog] = useState<PermissionGroup[]>([]);
  const [error, setError] = useState<string | null>(null);

  const loadRoles = useCallback(() => {
    usersApi
      .listRoles()
      .then((r) => {
        setRoles(r.roles);
        setCatalog(r.catalog);
      })
      .catch((e) => setError(apiError(e, t('somethingWrong'))));
  }, [t]);
  useEffect(loadRoles, [loadRoles]);

  const label = (name: string) => {
    const r = roles.find((x) => x.name === name);
    return r ? usersApi.roleLabel(r, lang) : name;
  };

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('users.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        {error && <p className="mb-4 text-sm text-status-due">{error}</p>}

        <div className="mb-4 flex gap-2">
          <button className={tab === 'users' ? btn : btnGhost} onClick={() => setTab('users')}>
            {t('users.tab.users')}
          </button>
          <button className={tab === 'roles' ? btn : btnGhost} onClick={() => setTab('roles')}>
            {t('users.tab.roles')}
          </button>
        </div>

        {tab === 'users' ? (
          <UsersTab roles={roles} label={label} canManage={canManageUsers} onChanged={loadRoles} />
        ) : (
          <RolesTab roles={roles} catalog={catalog} canManage={canManageRoles} onRoles={setRoles} />
        )}
      </div>
    </div>
  );
}

function RolePicker({ roles, value, onChange }: { roles: RoleInfo[]; value: string[]; onChange: (v: string[]) => void }) {
  const { lang, t } = useLanguage();
  // super_admin is never handed out from this screen
  const options = roles.filter((r) => r.name !== 'super_admin');
  return (
    <div>
      <p className="mb-1 text-sm text-navy-700">{t('users.pickRoles')}</p>
      <div className="grid grid-cols-2 gap-1">
        {options.map((r) => (
          <label key={r.name} className="flex items-center gap-2 rounded-lg border border-brand-100 px-2 py-1 text-sm">
            <input
              type="checkbox"
              checked={value.includes(r.name)}
              onChange={(e) => onChange(e.target.checked ? [...value, r.name] : value.filter((x) => x !== r.name))}
            />
            {usersApi.roleLabel(r, lang)}
          </label>
        ))}
      </div>
      <p className="mt-1 text-xs text-navy-700">{t('users.multiRoleHint')}</p>
    </div>
  );
}

function UsersTab({ roles, label, canManage, onChanged }: { roles: RoleInfo[]; label: (n: string) => string; canManage: boolean; onChanged: () => void }) {
  const { t } = useLanguage();
  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [q, setQ] = useState('');
  const [role, setRole] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<ManagedUser | null>(null);

  const load = useCallback(() => {
    usersApi
      .listUsers({ q: q || undefined, role: role || undefined })
      .then(setUsers)
      .catch((e) => setError(apiError(e, t('somethingWrong'))));
  }, [q, role, t]);
  useEffect(load, [load]);

  async function toggleStatus(u: ManagedUser) {
    if (u.status === 'ACTIVE' && !window.confirm(t('users.confirmDeactivate'))) return;
    setError(null);
    try {
      await usersApi.updateUser(u.id, { status: u.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' });
      load();
    } catch (e) {
      setError(apiError(e, t('somethingWrong')));
    }
  }

  return (
    <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <input className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm" placeholder={t('users.search')} value={q} onChange={(e) => setQ(e.target.value)} />
        <select className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm" value={role} onChange={(e) => setRole(e.target.value)}>
          <option value="">{t('users.allRoles')}</option>
          {roles.map((r) => (
            <option key={r.name} value={r.name}>
              {label(r.name)}
            </option>
          ))}
        </select>
        {canManage && (
          <button className={`${btn} ml-auto`} onClick={() => setShowNew(true)}>
            {t('users.newUser')}
          </button>
        )}
      </div>
      <p className="mb-3 text-xs text-navy-700">{t('users.residentNote')}</p>
      {error && <p className="mb-2 text-sm text-status-due">{error}</p>}
      {msg && <p className="mb-2 text-sm text-status-paid">{msg}</p>}

      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead className="text-navy-700">
            <tr>
              <th className="py-2 pr-3">{t('users.name')}</th>
              <th className="pr-3">{t('users.mobile')}</th>
              <th className="pr-3">{t('users.roles')}</th>
              <th className="pr-3">{t('users.status')}</th>
              <th className="pr-3">{t('users.lastLogin')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id} className="border-t border-brand-100">
                <td className="py-2 pr-3 font-medium">
                  {u.name ?? '—'}
                  {u.email && <span className="block text-xs font-normal text-navy-700">{u.email}</span>}
                </td>
                <td className="pr-3">{u.mobile}</td>
                <td className="pr-3">{u.roles.map(label).join(', ')}</td>
                <td className="pr-3">
                  <span className={`rounded-full px-2 py-0.5 text-xs ${u.status === 'ACTIVE' ? 'bg-status-paid/15 text-status-paid' : 'bg-navy-900/10 text-navy-700'}`}>
                    {t(`users.status.${u.status}` as TranslationKey)}
                  </span>
                </td>
                <td className="pr-3 text-xs text-navy-700">{u.last_login ? new Date(u.last_login).toLocaleString() : t('users.never')}</td>
                <td className="whitespace-nowrap text-right">
                  {canManage && !u.roles.includes('super_admin') && (
                    <>
                      <button className="mr-2 text-brand-700 hover:underline" onClick={() => setEditing(u)}>
                        {t('users.edit')}
                      </button>
                      <button className="text-status-due hover:underline" onClick={() => toggleStatus(u)}>
                        {u.status === 'ACTIVE' ? t('users.deactivate') : t('users.activate')}
                      </button>
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showNew && (
        <NewUserModal
          roles={roles}
          onClose={() => setShowNew(false)}
          onDone={() => {
            setShowNew(false);
            setMsg(t('users.created'));
            load();
            onChanged();
          }}
        />
      )}
      {editing && (
        <EditUserModal
          user={editing}
          roles={roles}
          onClose={() => setEditing(null)}
          onDone={(m) => {
            setEditing(null);
            setMsg(m);
            load();
            onChanged();
          }}
        />
      )}
    </div>
  );
}

function NewUserModal({ roles, onClose, onDone }: { roles: RoleInfo[]; onClose: () => void; onDone: () => void }) {
  const { t } = useLanguage();
  const [name, setName] = useState('');
  const [mobile, setMobile] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [picked, setPicked] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await usersApi.createUser({ name_bn: name, mobile, email: email || undefined, password, roles: picked });
      onDone();
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal title={t('users.newUser')} onClose={onClose}>
      <form onSubmit={submit} className="max-h-[70vh] space-y-3 overflow-y-auto">
        <input required className={input} placeholder={t('users.name')} value={name} onChange={(e) => setName(e.target.value)} />
        <input required className={input} placeholder={`${t('users.mobile')} (01XXXXXXXXX)`} value={mobile} onChange={(e) => setMobile(e.target.value)} />
        <input type="email" required className={input} placeholder={t('users.email')} value={email} onChange={(e) => setEmail(e.target.value)} />
        <div>
          <input type="password" required minLength={8} className={input} placeholder={t('users.password')} value={password} onChange={(e) => setPassword(e.target.value)} />
          <p className="mt-1 text-xs text-navy-700">{t('users.passwordHint')}</p>
        </div>
        <RolePicker roles={roles} value={picked} onChange={setPicked} />
        {error && <p className="text-sm text-status-due">{error}</p>}
        <button type="submit" disabled={busy || picked.length === 0} className={`${btn} w-full`}>
          {t('common.submit')}
        </button>
      </form>
    </Modal>
  );
}

function EditUserModal({ user, roles, onClose, onDone }: { user: ManagedUser; roles: RoleInfo[]; onClose: () => void; onDone: (msg: string) => void }) {
  const { t } = useLanguage();
  const [email, setEmail] = useState(user.email ?? '');
  const [picked, setPicked] = useState<string[]>(user.roles);
  const [newPassword, setNewPassword] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function save(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await usersApi.updateUser(user.id, { email: email || null, roles: picked });
      onDone(t('users.saved'));
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  async function reset() {
    setError(null);
    try {
      await usersApi.resetPassword(user.id, newPassword);
      onDone(t('users.passwordChanged'));
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  return (
    <Modal title={`${t('users.edit')} — ${user.name ?? user.mobile}`} onClose={onClose}>
      <div className="max-h-[70vh] space-y-4 overflow-y-auto">
        <form onSubmit={save} className="space-y-3">
          <input type="email" className={input} placeholder={t('users.email')} value={email} onChange={(e) => setEmail(e.target.value)} />
          <RolePicker roles={roles} value={picked} onChange={setPicked} />
          <button type="submit" disabled={picked.length === 0} className={`${btn} w-full`}>
            {t('hall.save')}
          </button>
        </form>
        <div className="space-y-2 rounded-lg border border-brand-200 p-3">
          <input type="password" minLength={8} className={input} placeholder={t('users.newPassword')} value={newPassword} onChange={(e) => setNewPassword(e.target.value)} />
          <button type="button" disabled={newPassword.length < 8} onClick={reset} className={`${btnGhost} w-full`}>
            {t('users.resetPassword')}
          </button>
        </div>
        {error && <p className="text-sm text-status-due">{error}</p>}
      </div>
    </Modal>
  );
}

function RolesTab({ roles, catalog, canManage, onRoles }: { roles: RoleInfo[]; catalog: PermissionGroup[]; canManage: boolean; onRoles: (r: RoleInfo[]) => void }) {
  const { t, lang } = useLanguage();
  const [selected, setSelected] = useState<string>('');
  const [draft, setDraft] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [newName, setNewName] = useState('');

  const role = roles.find((r) => r.name === selected) ?? roles[0];

  useEffect(() => {
    if (role) {
      setSelected(role.name);
      setDraft(role.permissions);
    }
    // re-seed the ticks only when a different role is opened or the list is refreshed
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [role?.name, roles]);

  if (!role) return null;
  const editable = canManage && !role.locked;

  async function save() {
    setError(null);
    setMsg(null);
    try {
      onRoles(await usersApi.saveRolePermissions(role.name, draft));
      setMsg(t('users.saved'));
    } catch (e) {
      setError(apiError(e, t('somethingWrong')));
    }
  }

  async function addRole(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const list = await usersApi.createRole(newName, []);
      onRoles(list);
      setSelected(newName);
      setNewName('');
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  async function remove() {
    setError(null);
    try {
      const list = await usersApi.deleteRole(role.name);
      setSelected('');
      onRoles(list);
    } catch (e) {
      setError(apiError(e, t('somethingWrong')));
    }
  }

  const toggle = (name: string, on: boolean) => setDraft((d) => (on ? [...new Set([...d, name])] : d.filter((x) => x !== name)));

  return (
    <div className="grid gap-4 md:grid-cols-[14rem_1fr]">
      <div className="rounded-xl border border-brand-200 bg-white p-2 shadow-sm">
        {roles.map((r) => (
          <button
            key={r.name}
            onClick={() => {
              setSelected(r.name);
              setMsg(null);
              setError(null);
            }}
            className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm ${r.name === role.name ? 'bg-brand-100 font-medium' : 'hover:bg-brand-50'}`}
          >
            <span>{usersApi.roleLabel(r, lang)}</span>
            <span className="text-xs text-navy-700">{r.users_count}</span>
          </button>
        ))}
        {canManage && (
          <form onSubmit={addRole} className="mt-2 space-y-2 border-t border-brand-100 p-2">
            <input className={input} placeholder={t('users.roleName')} value={newName} onChange={(e) => setNewName(e.target.value)} />
            <button type="submit" disabled={!newName} className={`${btnGhost} w-full`}>
              {t('users.newRole')}
            </button>
          </form>
        )}
      </div>

      <div className="min-w-0 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
        <div className="mb-3 flex items-start justify-between gap-2">
          <div>
            <h2 className="font-semibold text-navy-900">{usersApi.roleLabel(role, lang)}</h2>
            <p className="text-xs text-navy-700">
              {role.name} · {role.system ? t('users.system') : t('users.custom')} · {t('users.usersCount')}: {role.users_count}
            </p>
          </div>
          {canManage && !role.system && role.users_count === 0 && (
            <button className="text-sm text-status-due hover:underline" onClick={remove}>
              {t('users.deleteRole')}
            </button>
          )}
        </div>

        {role.locked && <p className="mb-3 rounded-lg bg-brand-50 p-3 text-sm text-navy-900">{t('users.lockedHint')}</p>}

        <div className="space-y-3">
          {catalog.map((g) => (
            <div key={g.module}>
              <p className="mb-1 text-sm font-medium text-navy-900">{lang === 'bn' ? g.label_bn : g.label_en}</p>
              <div className="flex flex-wrap gap-2">
                {g.permissions.map((p) => (
                  <label key={p.name} className={`flex items-center gap-1 rounded-lg border px-2 py-1 text-sm ${draft.includes(p.name) ? 'border-brand-400 bg-brand-50' : 'border-brand-100'}`}>
                    <input type="checkbox" disabled={!editable} checked={draft.includes(p.name)} onChange={(e) => toggle(p.name, e.target.checked)} />
                    {lang === 'bn' ? p.label_bn : p.label_en}
                  </label>
                ))}
              </div>
            </div>
          ))}
        </div>

        {error && <p className="mt-3 text-sm text-status-due">{error}</p>}
        {msg && <p className="mt-3 text-sm text-status-paid">{msg}</p>}
        {editable && (
          <button className={`${btn} mt-4`} onClick={save}>
            {t('users.savePermissions')}
          </button>
        )}
      </div>
    </div>
  );
}
