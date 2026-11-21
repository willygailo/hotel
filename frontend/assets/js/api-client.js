/* ============================================================
   MARVELOUS HOTEL — Tier 1 Frontend Unified REST API Client
   frontend/assets/js/api-client.js
   Communicates with Tier 2 Slim 4 REST API (/backend/api/v1/*)
   ============================================================ */

(function () {
  function getApiBase() {
    if (window.HOTEL_API_BASE) {
      return window.HOTEL_API_BASE.replace(/\/+$/, '');
    }
    const isSubdir = window.location.pathname.startsWith('/hotel');
    return (isSubdir ? '/hotel' : '') + '/backend/api/v1';
  }

  function detectPortal() {
    if (window.HOTEL_PORTAL) return window.HOTEL_PORTAL;
    const p = window.location.pathname;
    if (/\/admin(\/|$)/.test(p)) return 'admin';
    if (/\/staff(\/|$)/.test(p)) return 'staff';
    return 'user';
  }

  function getCsrfToken() {
    if (window.HOTEL_CSRF_TOKEN) return window.HOTEL_CSRF_TOKEN;
    if (window.HOTEL_AI_CONFIG && window.HOTEL_AI_CONFIG.csrfToken) return window.HOTEL_AI_CONFIG.csrfToken;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const hidden = document.querySelector('input[name="csrf_token"]');
    return hidden ? hidden.value : '';
  }

  async function request(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const url = getApiBase() + (path.startsWith('/') ? path : '/' + path);
    const headers = Object.assign({
      'Accept': 'application/json',
      'X-Portal': detectPortal()
    }, options.headers || {});

    const fetchOpts = {
      method,
      credentials: 'same-origin',
      headers
    };

    if (options.signal) {
      fetchOpts.signal = options.signal;
    }

    if (method !== 'GET' && method !== 'HEAD') {
      const csrf = getCsrfToken();
      if (csrf) headers['X-CSRF-Token'] = csrf;
      if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
        fetchOpts.body = typeof options.body === 'string'
          ? options.body
          : JSON.stringify(options.body);
      }
    }

    const res = await fetch(url, fetchOpts);
    const json = await res.json().catch(() => ({ ok: false, error: `Invalid JSON (HTTP ${res.status})` }));
    if (!res.ok || json.ok === false) {
      const err = new Error(json.error || `Request failed (HTTP ${res.status})`);
      err.status = res.status;
      err.payload = json;
      throw err;
    }
    return json;
  }

  function toQuery(params) {
    const qs = new URLSearchParams();
    Object.entries(params || {}).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        qs.set(k, String(v));
      }
    });
    const str = qs.toString();
    return str ? '?' + str : '';
  }

  window.HotelAPI = {
    baseUrl: getApiBase,
    getHealth: () => request('/health'),
    getAuthStatus: () => request('/auth/status'),
    getRooms: (filters = {}) => request('/rooms' + toQuery(filters)),
    getRoom: (id) => request('/rooms/' + encodeURIComponent(id)),
    checkAvailability: (roomId, checkIn, checkOut, signal) =>
      request('/availability' + toQuery({ room_id: roomId, check_in: checkIn, check_out: checkOut }), { signal }),
    getBookings: (filters = {}) => request('/bookings' + toQuery(filters)),
    cancelBooking: (id, reason = '') =>
      request('/bookings/' + encodeURIComponent(id) + '/cancel', { method: 'POST', body: { reason } }),
    getPayments: (filters = {}) => request('/payments' + toQuery(filters)),
    decidePayment: (id, decision) =>
      request('/payments/' + encodeURIComponent(id) + '/decision', { method: 'POST', body: { decision } }),
    getDashboardMetrics: () => request('/dashboard/metrics')
  };
})();
