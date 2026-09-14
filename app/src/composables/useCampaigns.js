import { ref } from 'vue';
import { createApiClient } from '../utils/api';
import { __, _n, sprintf } from '../utils/i18n';
import { formatMoney } from '../utils/money';

/**
 * The campaigns screen's data and what can be done to it.
 *
 * Every campaign is in memory: the bootstrap brings them all, and each action
 * answers with the fresh list, so the screen never shows a state the server
 * does not hold.
 *
 * @since 6.0.0
 * @param {object} bootstrap - The screen's bootstrap payload.
 * @return {object} State and actions.
 */
export function useCampaigns(bootstrap) {
  const api = createApiClient(bootstrap);
  const campaigns = ref(Array.isArray(bootstrap.campaigns) ? bootstrap.campaigns : []);

  /**
   * Put one campaign in the list, replacing its older copy.
   *
   * @since 6.0.0
   * @param {object} campaign - The campaign as the server holds it.
   * @return {void}
   */
  function remember(campaign) {
    if (!campaign) {
      return;
    }

    const others = campaigns.value.filter((item) => item.id !== campaign.id);

    campaigns.value = [campaign, ...others];
  }

  /**
   * Create or update a campaign.
   *
   * @since 6.0.0
   * @param {{id: number, title: string, status: string, rules: object}} campaign - What the editor holds.
   * @return {Promise<{ok: boolean, message: string, errors: object, campaign: object|null}>}
   */
  async function save(campaign) {
    try {
      const response = await api.post('admin/campaigns', { campaign });

      if (response.status !== 'success') {
        return { ok: false, message: response.message || '', errors: response.errors || {}, campaign: null };
      }

      remember(response.campaign);

      return { ok: true, message: response.message, errors: {}, campaign: response.campaign };
    } catch (error) {
      return { ok: false, message: error.message, errors: {}, campaign: null };
    }
  }

  /**
   * Switch, trash, restore or delete campaigns.
   *
   * @since 6.0.0
   * @param {number[]} ids - Campaign IDs.
   * @param {string} action - One of activate, deactivate, trash, restore, delete.
   * @return {Promise<{ok: boolean, message: string}>}
   */
  async function apply(ids, action) {
    try {
      const response = await api.post('admin/campaigns/action', { ids, action });

      if (Array.isArray(response.campaigns)) {
        campaigns.value = response.campaigns;
      }

      return { ok: response.status === 'success', message: response.message || '' };
    } catch (error) {
      return { ok: false, message: error.message };
    }
  }

  /**
   * Copy a campaign, as an inactive one.
   *
   * @since 6.0.0
   * @param {number} id - Campaign ID.
   * @return {Promise<{ok: boolean, message: string, campaign: object|null}>}
   */
  async function duplicate(id) {
    try {
      const response = await api.post('admin/campaigns/duplicate', { id });

      if (Array.isArray(response.campaigns)) {
        campaigns.value = response.campaigns;
      }

      return { ok: response.status === 'success', message: response.message || '', campaign: response.campaign || null };
    } catch (error) {
      return { ok: false, message: error.message, campaign: null };
    }
  }

  /**
   * Search products or terms for a picker.
   *
   * @since 6.0.0
   * @param {string} type - product, product_cat, product_tag or product_brand.
   * @param {string} term - What the reader typed.
   * @return {Promise<Array<{id: number, label: string}>>}
   */
  async function search(type, term) {
    const query = new URLSearchParams({ type, search: term }).toString();
    const response = await api.get(`admin/campaigns/search?${query}`);

    return Array.isArray(response.results) ? response.results : [];
  }

  /**
   * How many products a campaign reaches, and what it overlaps.
   *
   * @since 6.0.0
   * @param {{id: number, rules: object}} campaign - What the editor holds.
   * @return {Promise<{products: number, conflicts: Array<object>}|null>}
   */
  async function preview(campaign) {
    try {
      const response = await api.post('admin/campaigns/preview', { campaign });

      return { products: Number(response.products) || 0, conflicts: response.conflicts || [] };
    } catch (error) {
      return null;
    }
  }

  return { campaigns, save, apply, duplicate, search, preview };
}

/**
 * Write a campaign date, stored in the store's time, for the reader.
 *
 * The date is already in the store's timezone and must not move: it is read
 * as if it were UTC and printed as UTC, in the page's language.
 *
 * @since 6.0.0
 * @param {string} value - `YYYY-MM-DDTHH:MM`.
 * @return {string} The date, or an empty string.
 */
export function formatCampaignDate(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(String(value || ''));

  if (!match) {
    return '';
  }

  const [, year, month, day, hour, minute] = match.map(Number);
  const date = new Date(Date.UTC(year, month - 1, day, hour, minute));
  const locale = document.documentElement.lang || 'pt-BR';

  return new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short', timeZone: 'UTC' }).format(date);
}

/**
 * A campaign's period, in one line.
 *
 * @since 6.0.0
 * @param {{starts_at: string, ends_at: string}} rules - Campaign rules.
 * @return {string}
 */
export function describePeriod(rules) {
  const starts = formatCampaignDate(rules.starts_at);
  const ends = formatCampaignDate(rules.ends_at);

  if (starts && ends) {
    return sprintf(__('De %s até %s'), starts, ends);
  }

  if (starts) {
    return sprintf(__('A partir de %s, sem data de fim'), starts);
  }

  if (ends) {
    return sprintf(__('Até %s'), ends);
  }

  return __('Sem data de início ou fim');
}

/**
 * What a campaign targets, in a few words.
 *
 * @since 6.0.0
 * @param {object} target - The rules' target.
 * @return {string}
 */
export function describeTarget(target) {
  const count = (target.ids || []).length;

  switch (target.scope) {
    case 'all':
      return __('Todos os produtos');

    case 'products':
      return sprintf(_n('%d produto', '%d produtos', count), count);

    case 'categories':
      return sprintf(_n('%d categoria', '%d categorias', count), count);

    case 'tags':
      return sprintf(_n('%d tag', '%d tags', count), count);

    case 'brands':
      return sprintf(_n('%d marca', '%d marcas', count), count);

    default:
      return '';
  }
}

/**
 * What a campaign changes, in a few words each.
 *
 * @since 6.0.0
 * @param {object} rules - Campaign rules.
 * @param {object} environment - The store's currency formatting.
 * @return {string[]}
 */
export function describeEffects(rules, environment = {}) {
  const effects = [];
  const { discount, installments } = rules;
  const money = (amount) => formatMoney(amount, environment);
  const rate = (amount) => String(Number(amount)).replace('.', environment.decimal_separator ?? ',');

  if (discount.enabled && discount.amount !== '') {
    effects.push(
      discount.method === 'fixed'
        ? sprintf(__('%s de desconto'), money(Number(discount.amount)))
        : sprintf(__('%s%% de desconto'), rate(discount.amount)),
    );
  }

  if (installments.enabled) {
    if (installments.without_fee !== '') {
      effects.push(sprintf(__('%dx sem juros'), Number(installments.without_fee)));
    }

    if (installments.max !== '') {
      effects.push(sprintf(__('até %dx'), Number(installments.max)));
    }

    if (installments.interest === 'single' && installments.fee !== '') {
      effects.push(sprintf(__('juros de %s%% ao mês'), rate(installments.fee)));
    } else if (installments.interest === 'table') {
      effects.push(__('tabela de juros própria'));
    }

    if (installments.min_value !== '') {
      effects.push(sprintf(__('parcela mínima de %s'), money(Number(installments.min_value))));
    }
  }

  return effects;
}
