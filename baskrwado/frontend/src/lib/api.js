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

const IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const TARGET_IMAGE_BYTES = 300 * 1024;

async function loadBitmap(file) {
  if ('createImageBitmap' in window) return createImageBitmap(file);
  return new Promise((resolve, reject) => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Unable to read image.')); };
    img.src = url;
  });
}

function canvasBlob(canvas, type, quality) {
  return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
}

async function optimizeImage(file) {
  if (!IMAGE_TYPES.has(file.type) || file.size <= TARGET_IMAGE_BYTES) return file;

  const bitmap = await loadBitmap(file);
  let width = bitmap.width;
  let height = bitmap.height;
  const maxEdge = 1800;
  if (Math.max(width, height) > maxEdge) {
    const scale = maxEdge / Math.max(width, height);
    width = Math.max(1, Math.round(width * scale));
    height = Math.max(1, Math.round(height * scale));
  }

  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d', { alpha: true });
  let best = null;
  const outputType = file.type === 'image/png' ? 'image/webp' : file.type;

  for (let pass = 0; pass < 10; pass += 1) {
    canvas.width = width;
    canvas.height = height;
    ctx.clearRect(0, 0, width, height);
    ctx.drawImage(bitmap, 0, 0, width, height);

    for (const quality of [0.92, 0.86, 0.8, 0.74, 0.68, 0.62, 0.56, 0.5]) {
      const blob = await canvasBlob(canvas, outputType, quality);
      if (!blob) continue;
      if (!best || blob.size < best.size) best = blob;
      if (blob.size <= TARGET_IMAGE_BYTES) {
        if (bitmap.close) bitmap.close();
        const base = file.name.replace(/\.[^.]+$/, '');
        const ext = outputType === 'image/webp' ? 'webp' : 'jpg';
        return new File([blob], `${base}.${ext}`, { type: outputType, lastModified: Date.now() });
      }
    }

    if (Math.max(width, height) <= 640) break;
    width = Math.max(480, Math.round(width * 0.86));
    height = Math.max(480, Math.round(height * 0.86));
  }

  if (bitmap.close) bitmap.close();
  if (!best || best.size >= file.size) return file;
  const base = file.name.replace(/\.[^.]+$/, '');
  const ext = outputType === 'image/webp' ? 'webp' : 'jpg';
  return new File([best], `${base}.${ext}`, { type: outputType, lastModified: Date.now() });
}

async function authenticatedBlob(path) {
  const response = await fetch(`${API_URL}${path}`, {
    headers: { Authorization: `Bearer ${adminToken()}` },
  });
  if (!response.ok) throw new Error('Unable to load document.');
  return response.blob();
}

export const api = {
  services: () => request('/services'),
  createCase: (payload) => request('/cases', { method: 'POST', body: JSON.stringify(payload) }),
  addAnswers: (publicId, phone, answers) => request(`/cases/${encodeURIComponent(publicId)}/answers`, {
    method: 'POST',
    body: JSON.stringify({ phone, answers }),
  }),
  uploadDocument: async (publicId, phone, file, category = 'evidence') => {
    const optimized = await optimizeImage(file);
    const form = new FormData();
    form.append('phone', phone);
    form.append('category', category);
    form.append('file', optimized);
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
    const blob = await authenticatedBlob(`/admin/documents/${documentId}/view`);
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  },
  adminDownloadDocument: async (documentId, filename = 'document') => {
    const blob = await authenticatedBlob(`/admin/documents/${documentId}/download`);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  },
};

export { API_URL };
