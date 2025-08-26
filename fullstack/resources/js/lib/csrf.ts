export function getCsrfToken(): string {
  const el = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
  return el?.content || '';
}

export const defaultJsonHeaders = () => ({
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
  'X-CSRF-TOKEN': getCsrfToken(),
  // Also send Sanctum style header if cookie exists (improves compatibility)
  'X-XSRF-TOKEN': decodeURIComponent(document.cookie.split('; ').find(c=>c.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''),
});

export async function jsonFetch(input: RequestInfo | URL, init: RequestInit = {}) {
  // Ensure POST/PUT/PATCH have a body so some CSRF middlewares don't discard
  if(init && init.method && ['POST','PUT','PATCH','DELETE'].includes(init.method.toUpperCase()) && init.body === undefined) {
    init.body = JSON.stringify({});
  }
  // If JSON body and _token missing, inject it for extra compatibility
  try {
    if(init.body && typeof init.body === 'string' && init.headers && (init.headers as any)['Content-Type']?.includes('application/json')) {
      const parsed = JSON.parse(init.body);
      if(parsed && typeof parsed === 'object' && !('_token' in parsed)) {
        parsed._token = getCsrfToken();
        init.body = JSON.stringify(parsed);
      }
    }
  } catch {/* ignore parse issues */}
  const doRequest = () => fetch(input, {
    credentials: 'same-origin',
    headers: {
      ...defaultJsonHeaders(),
      ...(init.headers || {}),
    },
    ...init,
  });
  let res = await doRequest();
  let data: any = null;
  try { data = await res.json(); } catch {}
  if(res.status === 419) {
    // Try to refresh token once
    try {
      const tokenRes = await fetch('/api/csrf-token', { credentials:'same-origin'});
      const tokenData = await tokenRes.json();
      const meta = document.querySelector('meta[name="csrf-token"]');
      if(meta) meta.setAttribute('content', tokenData.token);
      // Rebuild body with new _token if JSON
      if(init.body && typeof init.body === 'string') {
        try { const parsed = JSON.parse(init.body); parsed._token = tokenData.token; init.body = JSON.stringify(parsed); } catch {}
      }
      res = await doRequest();
      try { data = await res.json(); } catch {}
    } catch {/* ignore refresh failure */}
  }
  if(!res.ok) throw new Error(data?.message || `Request failed (${res.status})`);
  return data;
}
