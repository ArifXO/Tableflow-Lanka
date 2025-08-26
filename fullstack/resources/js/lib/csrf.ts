export function getCsrfToken(): string {
  const el = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
  return el?.content || '';
}

export const defaultJsonHeaders = () => ({
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
  'X-CSRF-TOKEN': getCsrfToken(),
});

export async function jsonFetch(input: RequestInfo | URL, init: RequestInit = {}) {
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
