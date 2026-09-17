const API_URL = import.meta.env.VITE_API_URL || '/api';
const IMAGE_TARGET_BYTES = 300 * 1024;
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

async function canvasBlob(canvas, quality) {
  return new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', quality));
}

async function optimizeImageUpload(file) {
  if (!file?.type?.startsWith('image/') || file.size <= IMAGE_TARGET_BYTES || typeof document === 'undefined') return file;

  let bitmap;
  try {
    if ('createImageBitmap' in window) {
      bitmap = await createImageBitmap(file);
    } else {
      const url = URL.createObjectURL(file);
      bitmap = await new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => { URL.revokeObjectURL(url); resolve(image); };
        image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Unable to read image.')); };
        image.src = url;
      });
    }

    const originalWidth = bitmap.width || bitmap.naturalWidth;
    const originalHeight = bitmap.height || bitmap.naturalHeight;
    if (!originalWidth || !originalHeight) return file;

    let scale = Math.min(1, 2400 / Math.max(originalWidth, originalHeight));
    let quality = 0.92;
    let best = null;

    for (let attempt = 0; attempt < 18; attempt += 1) {
      const width = Math.max(720, Math.round(originalWidth * scale));
      const height = Math.max(1, Math.round(originalHeight * (width / originalWidth)));
      const canvas = document.createElement('canvas');
      canvas.width = Math.min(width, originalWidth);
      canvas.height = Math.min(height, originalHeight);
      const ctx = canvas.getContext('2d', { alpha: true });
      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

      const blob = await canvasBlob(canvas, quality);
      if (blob && (!best || blob.size < best.size)) best = blob;
      if (blob && blob.size <= IMAGE_TARGET_BYTES) {
        const base = file.name.replace(/\.[^.]+$/, '') || 'evidence';
        return new File([blob], `${base}.webp`, { type: 'image/webp', lastModified: file.lastModified || Date.now() });
      }

      if (quality > 0.72) quality -= 0.06;
      else { scale *= 0.86; quality = 0.88; }
    }

    if (best && best.size < file.size) {
      const base = file.name.replace(/\.[^.]+$/, '') || 'evidence';
      return new File([best], `${base}.webp`, { type: 'image/webp', lastModified: file.lastModified || Date.now() });
    }
  } catch {
    return file;
  } finally {
    if (bitmap?.close) bitmap.close();
  }
  return file;
}

async function authenticatedDocumentBlob(documentId, mode) {
  const response = await fetch(`${API_URL}/admin/documents/${documentId}/${mode}`, {
    headers: { Authorization: `Bearer ${adminToken()}` },
  });
  if (!response.ok) throw new Error(`Unable to ${mode} document.`);
  return response.blob();
}

export const api = {
  services: () => request('/services'),
  createCase: (payload) => request('/cases', { method: 'POST', body: JSON.stringify(payload) }),
  addAnswers: (publicId, phone, answers) => request(`/cases/${encodeURIComponent(publicId)}/answers`, {
    method: 'POST', body: JSON.stringify({ phone, answers }),
  }),
  uploadDocument: async (publicId, phone, file, category = 'evidence') => {
    const uploadFile = await optimizeImageUpload(file);
    const form = new FormData();
    form.append('phone', phone);
    form.append('category', category);
    form.append('file', uploadFile);
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
    const tab = window.open('about:blank', '_blank');
    try {
      const blob = await authenticatedDocumentBlob(documentId, 'view');
      const url = URL.createObjectURL(blob);
      if (tab) tab.location.href = url;
      else window.open(url, '_blank', 'noopener,noreferrer');
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
      if (tab) tab.close();
      throw error;
    }
  },
  adminDownloadDocument: async (documentId, filename = 'document') => {
    const blob = await authenticatedDocumentBlob(documentId, 'download');
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  },
};

export { API_URL, optimizeImageUpload };
