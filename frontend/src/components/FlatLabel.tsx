import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';

/**
 * How a flat is named in every list: the flat number, then who lives there - the tenant first when the flat
 * is let, and the owner just below. The flat number and the owner's name both open the owner's ledger
 * (for people allowed to see ledgers; everyone else gets the flat's detail page instead).
 */
export default function FlatLabel({
  flatId,
  flatNo,
  owner,
  tenant,
}: {
  flatId: number;
  flatNo: string | null | undefined;
  owner?: string | null;
  tenant?: string | null;
}) {
  const { user } = useAuth();
  const { t } = useLanguage();
  const canLedger = !!user && (user.roles.includes('super_admin') || user.permissions.includes('contribution.view'));
  const to = canLedger ? `/contributions?flat=${flatId}` : `/flats/${flatId}`;
  const link = 'text-brand-700 hover:underline';

  return (
    <div className="leading-snug">
      <Link to={to} className={`font-semibold ${link}`}>
        {flatNo ?? flatId}
      </Link>
      {tenant && (
        <div className="text-navy-900">
          {tenant} <span className="ml-1 rounded bg-status-partial/15 px-1 text-[10px] text-status-partial">{t('flatLabel.tenant')}</span>
        </div>
      )}
      {owner && (
        <div className="text-sm">
          <Link to={to} className={link}>
            {owner}
          </Link>
          {tenant && <span className="ml-1 rounded bg-brand-100 px-1 text-[10px] text-brand-800">{t('flatLabel.owner')}</span>}
        </div>
      )}
    </div>
  );
}
