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
  const res = await fetch(input, {
    credentials: 'same-origin',
    headers: {
      ...defaultJsonHeaders(),
      ...(init.headers || {}),
    },
    ...init,
  });
  let data: any = null;
  try { data = await res.json(); } catch { /* ignore non json */ }
  if(!res.ok) {
    const msg = data?.message || `Request failed (${res.status})`;
    throw new Error(msg);
  }
  return data;
}
