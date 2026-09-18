const nativeFetch = window.fetch.bind(window);
const CACHE_KEY = 'bkw_admin_cached_user';
const CACHE_AT_KEY = 'bkw_admin_cached_user_at';

function responseJson(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json', 'X-BKW-Runtime-Guard': '1' },
  });
}

function getUrl(input) {
  try {
    if (typeof input === 'string') return new URL(input, window.location.origin);
    if (input instanceof Request) return new URL(input.url, window.location.origin);
    return new URL(String(input), window.location.origin);
  } catch {
    return null;
  }
}

function cacheUser(user) {
  if (!user) return;
  try {
    sessionStorage.setItem(CACHE_KEY, JSON.stringify(user));
    sessionStorage.setItem(CACHE_AT_KEY, String(Date.now()));
  } catch {}
}

function cachedUser(maxAgeMs = 5 * 60 * 1000) {
  try {
    const savedAt = Number(sessionStorage.getItem(CACHE_AT_KEY) || 0);
    if (!savedAt || Date.now() - savedAt > maxAgeMs) return null;
    return JSON.parse(sessionStorage.getItem(CACHE_KEY) || 'null');
  } catch {
    return null;
  }
}

window.fetch = async function guardedFetch(input, init = {}) {
  const url = getUrl(input);
  if (!url || url.origin !== window.location.origin || !url.pathname.startsWith('/api/admin/')) {
    return nativeFetch(input, init);
  }

  const method = String(init?.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
  const path = url.pathname;

  // Login already returns the authenticated user. Cache it so the immediate
  // post-login /auth/me round trip can never block the console on shared hosting.
  if (method === 'GET' && path === '/api/admin/auth/me') {
    const user = cachedUser(30 * 1000);
    if (user) return responseJson({ user });
  }

  const controller = init.signal ? null : new AbortController();
  const timeoutMs = path.includes('/admin/cases') ? 12000 : 8000;
  const timer = controller ? window.setTimeout(() => controller.abort(), timeoutMs) : null;

  try {
    const response = await nativeFetch(input, {
      ...init,
      signal: init.signal || controller?.signal,
    });

    if (response.ok && path === '/api/admin/auth/login') {
      response.clone().json().then(payload => cacheUser(payload?.user)).catch(() => {});
    }
    if (response.ok && path === '/api/admin/auth/me') {
      response.clone().json().then(payload => cacheUser(payload?.user)).catch(() => {});
    }
    return response;
  } catch (error) {
    if (error?.name !== 'AbortError') throw error;

    // These GETs are dashboard bootstrap helpers. Do not let one slow shared-
    // hosting request freeze the complete console indefinitely.
    if (method === 'GET' && path === '/api/admin/notifications') {
      return responseJson({ data: [] });
    }
    if (method === 'GET' && path === '/api/admin/staff') {
      return responseJson({ data: [] });
    }
    if (method === 'GET' && path === '/api/admin/cases') {
      return responseJson({
        data: [],
        metrics: { open: 0, ready_for_review: 0, waiting_customer: 0, urgent: 0 },
      });
    }
    if (method === 'GET' && path === '/api/admin/auth/me') {
      const user = cachedUser();
      if (user) return responseJson({ user });
    }

    throw new Error('Server response timed out. Please refresh and try again.');
  } finally {
    if (timer) window.clearTimeout(timer);
  }
};
