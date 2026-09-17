const API_URL = import.meta.env.VITE_API_URL || '/api';

const adminToken = () => localStorage.getItem('bkw_admin_token') || '';

async function request(path, options = {}) {
  const isForm = options.body instanceof FormData;
  const headers = {
    Accept: 'application/json',
    ...(isForm ? {} : { 'Content-Type': 'application/json' }),
    ...(options.admin && adminToken() ? { Authorization: `Bearer ${adminToken()}` } : {}),
    ...(options.headers || {}),
  };

  const response = await fetch(`${API_URL}${path}`, { ...options, headers });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    if (response.status === 401 && options.admin) localStorage.removeItem('bkw_admin_token');
    const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
    throw new Error(validation || payload.message || 'Something went wrong. Please try again.');
  }
  return payload;
}

function queryString(params = {}) {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') q.set(key, value);
  });
  const value = q.toString();
  return value ? `?${value}` : '';
}

async function adminBlob(path, fallbackMessage) {
  const response = await fetch(`${API_URL}${path}`, {
    headers: { Authorization: `Bearer ${adminToken()}` },
  });
  if (!response.ok) throw new Error(fallbackMessage);
  return response.blob();
}

export const api = {
  services: () => request('/services'),
  createCase: (payload) => request('/cases', { method: 'POST', body: JSON.stringify(payload) }),
  addAnswers: (publicId, phone, answers) => request(`/cases/${encodeURIComponent(publicId)}/answers`, {
    method: 'POST',
    body: JSON.stringify({ phone, answers }),
  }),
  uploadDocument: (publicId, phone, file, category = 'evidence') => {
    const form = new FormData();
    form.append('phone', phone);
    form.append('category', category);
    form.append('file', file);
    return request(`/cases/${encodeURIComponent(publicId)}/documents`, { method: 'POST', body: form });
  },
  trackCase: (publicId, phone) => request(`/cases/${encodeURIComponent(publicId)}?phone=${encodeURIComponent(phone)}`),
  createPaymentOrder: (publicId, phone) => request(`/cases/${encodeURIComponent(publicId)}/payment-order`, {
    method: 'POST', body: JSON.stringify({ phone }),
  }),
  verifyPayment: (payload) => request('/payments/razorpay/verify', { method: 'POST', body: JSON.stringify(payload) }),

  adminLogin: (email, password) => request('/admin/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }),
  adminMe: () => request('/admin/auth/me', { admin: true }),
  adminLogout: () => request('/admin/auth/logout', { method: 'POST', admin: true }),
  adminUpdateProfile: (payload) => request('/admin/profile', { method: 'PATCH', admin: true, body: JSON.stringify(payload) }),
  adminChangePassword: (payload) => request('/admin/profile/password', { method: 'PATCH', admin: true, body: JSON.stringify(payload) }),
  adminUpdateNotificationPreferences: (payload) => request('/admin/profile/notification-preferences', { method: 'PATCH', admin: true, body: JSON.stringify(payload) }),
  adminNotifications: () => request('/admin/notifications', { admin: true }),
  adminReadNotification: (id) => request(`/admin/notifications/${id}/read`, { method: 'POST', admin: true }),
  adminReadAllNotifications: () => request('/admin/notifications/read-all', { method: 'POST', admin: true }),

  adminCases: (params = {}) => request(`/admin/cases${queryString(params)}`, { admin: true }),
  adminCase: (publicId) => request(`/admin/cases/${encodeURIComponent(publicId)}`, { admin: true }),
  adminUpdateCase: (publicId, payload) => request(`/admin/cases/${encodeURIComponent(publicId)}`, {
    method: 'PATCH', admin: true, body: JSON.stringify(payload),
  }),
  adminAddNote: (publicId, note, customerVisible = false) => request(`/admin/cases/${encodeURIComponent(publicId)}/notes`, {
    method: 'POST', admin: true, body: JSON.stringify({ note, customer_visible: customerVisible }),
  }),
  adminStaff: () => request('/admin/staff', { admin: true }),
  adminCreateStaff: (payload) => request('/admin/staff', { method: 'POST', admin: true, body: JSON.stringify(payload) }),
  adminUpdateStaff: (staffId, payload) => request(`/admin/staff/${staffId}`, { method: 'PATCH', admin: true, body: JSON.stringify(payload) }),
  adminVerifyDocument: (documentId, status) => request(`/admin/documents/${documentId}/verify`, {
    method: 'PATCH', admin: true, body: JSON.stringify({ status }),
  }),
  adminViewDocument: async (documentId) => {
    const blob = await adminBlob(`/admin/documents/${documentId}/view`, 'Unable to view document.');
    const url = URL.createObjectURL(blob);
    const win = window.open(url, '_blank', 'noopener,noreferrer');
    if (!win) {
      URL.revokeObjectURL(url);
      throw new Error('Popup blocked. Please allow popups to view this document.');
    }
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  },
  adminDownloadDocument: async (documentId, filename = 'document') => {
    const blob = await adminBlob(`/admin/documents/${documentId}/download`, 'Unable to download document.');
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  },
};

export { API_URL };
