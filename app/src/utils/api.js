/**
 * Create a REST client backed by the page bootstrap payload.
 *
 * @since 1.0.0
 * @param {Object} bootstrap - Bootstrap payload returned by the REST API.
 * @return {{request: Function, get: Function, post: Function}} Client helpers.
 */
export function createApiClient(bootstrap) {
  const root = String(bootstrap?.rest?.root || '').replace(/\/+$/, '');
  const nonce = bootstrap?.rest?.nonce || '';

  /**
   * Join the REST root and an endpoint path.
   *
   * WordPress always hands over an absolute root, but `new URL(path, base)`
   * throws outright on a relative base — which is what a preview harness or a
   * test double is most likely to supply. Concatenating leaves both cases
   * working and costs nothing.
   *
   * @since 1.0.0
   * @param {string} path - Endpoint path, relative to the plugin namespace.
   * @return {string} The URL to request.
   */
  function buildUrl(path) {
    return `${root}/${String(path).replace(/^\/+/, '')}`;
  }

  /**
   * Send a JSON request to the WordPress REST API.
   *
   * @since 1.0.0
   * @param {string} path - Endpoint path, relative to the plugin namespace.
   * @param {Object} [options] - Request options.
   * @return {Promise<Object>} Parsed JSON response.
   */
  async function request(path, options = {}) {
    const response = await fetch(buildUrl(path), {
      method: options.method || 'GET',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(nonce ? { 'X-WP-Nonce': nonce } : {}),
        ...(options.headers || {}),
      },
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'same-origin',
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data?.message || 'Request failed.');
    }

    return data;
  }

  return {
    request,
    get: (path) => request(path),
    post: (path, body) => request(path, { method: 'POST', body }),
  };
}
