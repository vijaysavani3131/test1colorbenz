const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
const ADMIN_KEY = () => localStorage.getItem('bkw_admin_key') || '';

async function request(path, options = {}) {
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(options.admin ? { 'X-Admin-Key': ADMIN_KEY() } : {}),
      ...(options.headers || {}),
    },
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.message || 'Something went wrong. Please try again.');
  }
  return payload;
}

export const api = {
  services: () => request('/services'),
  createCase: (payload) => request('/cases', { method: 'POST', body: JSON.stringify(payload) }),
  addAnswers: (publicId, answers) => request(`/cases/${publicId}/answers`, { method: 'POST', body: JSON.stringify({ answers }) }),
  trackCase: (publicId, phone) => request(`/cases/${encodeURIComponent(publicId)}?phone=${encodeURIComponent(phone)}`),
  adminCases: () => request('/admin/cases', { admin: true }),
  adminCase: (publicId) => request(`/admin/cases/${publicId}`, { admin: true }),
  updateStatus: (publicId, status) => request(`/admin/cases/${publicId}/status`, {
    method: 'PATCH',
    admin: true,
    body: JSON.stringify({ status }),
  }),
};

export { API_URL };
