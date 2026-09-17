import { api } from './lib/api';

const isAdmin = () => window.location.pathname.startsWith('/admin');
const LANGS = {
  en: { short: 'EN', name: 'English' },
  hi: { short: 'हि', name: 'हिन्दी' },
  gu: { short: 'ગુ', name: 'ગુજરાતી' },
};

const COPY = {
  en: { cases:'Cases', team:'Team', profile:'My profile', customer:'Customer site' },
  hi: { cases:'केस', team:'टीम', profile:'मेरी प्रोफ़ाइल', customer:'कस्टमर साइट' },
  gu: { cases:'કેસ', team:'ટીમ', profile:'મારી પ્રોફાઇલ', customer:'કસ્ટમર સાઇટ' },
};

let audioContext = null;
let lastNotificationId = null;
let pollingTimer = null;
let currentUser = null;

function soundEnabled() {
  return localStorage.getItem('bkw_notification_sound') !== 'off';
}

function unlockAudio() {
  if (!isAdmin() || !soundEnabled()) return;
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return;
    if (!audioContext) audioContext = new Ctx();
    if (audioContext.state === 'suspended') audioContext.resume().catch(() => {});
  } catch {}
}

function notificationChime() {
  if (!soundEnabled()) return;
  try {
    unlockAudio();
    if (!audioContext || audioContext.state !== 'running') return;
    const now = audioContext.currentTime;
    [0, 0.16].forEach((delay, index) => {
      const osc = audioContext.createOscillator();
      const gain = audioContext.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(index === 0 ? 760 : 980, now + delay);
      gain.gain.setValueAtTime(0.0001, now + delay);
      gain.gain.exponentialRampToValueAtTime(0.16, now + delay + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + delay + 0.18);
      osc.connect(gain);
      gain.connect(audioContext.destination);
      osc.start(now + delay);
      osc.stop(now + delay + 0.2);
    });
  } catch {}
}

function svgIcon(name) {
  if (name === 'theme') return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm8-6a1 1 0 1 1 0 2h-1a1 1 0 1 1 0-2h1ZM5 11a1 1 0 1 1 0 2H4a1 1 0 1 1 0-2h1Zm12.66-5.07a1 1 0 0 1 1.41 1.41l-.7.71a1 1 0 1 1-1.42-1.42l.71-.7ZM7.05 16.95a1 1 0 0 1 0 1.42l-.7.7a1 1 0 1 1-1.42-1.41l.71-.71a1 1 0 0 1 1.41 0Zm11.32 0 .7.71a1 1 0 1 1-1.41 1.41l-.71-.7a1 1 0 1 1 1.42-1.42ZM6.34 4.93l.71.7a1 1 0 0 1-1.42 1.42l-.7-.71a1 1 0 1 1 1.41-1.41ZM12 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1Z"/></svg>';
  if (name === 'bell') return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.6 2.6 0 0 0 2.45-1.75h-4.9A2.6 2.6 0 0 0 12 22Zm7-5.4-1.55-1.75V10a5.45 5.45 0 0 0-4.2-5.3V4a1.25 1.25 0 1 0-2.5 0v.7A5.45 5.45 0 0 0 6.55 10v4.85L5 16.6a1 1 0 0 0 .75 1.65h12.5A1 1 0 0 0 19 16.6Z"/></svg>';
  return '';
}

function findSidebarButton(text) {
  return [...document.querySelectorAll('.ops-sidebar nav button')].find(btn => btn.textContent.toLowerCase().includes(text.toLowerCase()));
}

function clickSidebar(text) {
  const btn = findSidebarButton(text);
  if (btn) btn.click();
}

function applyLanguageChrome() {
  const lang = localStorage.getItem('bkw_admin_language') || 'en';
  const copy = COPY[lang] || COPY.en;
  document.documentElement.lang = lang;
  const mapping = [
    ['cases', copy.cases],
    ['team', copy.team],
    ['my profile', copy.profile],
    ['customer site', copy.customer],
  ];
  document.querySelectorAll('.ops-sidebar nav button').forEach(btn => {
    if (!btn.dataset.bkwOriginal) btn.dataset.bkwOriginal = btn.textContent.trim().toLowerCase();
    const original = btn.dataset.bkwOriginal;
    mapping.forEach(([key, value]) => {
      if (original.includes(key)) {
        const span = btn.querySelector('span');
        if (span) span.textContent = value;
      }
    });
  });
}

function setTheme(theme) {
  const next = theme === 'dark' ? 'dark' : 'light';
  localStorage.setItem('bkw_admin_theme', next);
  document.body.classList.toggle('bkw-admin-dark', next === 'dark');
  const btn = document.querySelector('#bkw-admin-theme');
  if (btn) btn.setAttribute('aria-label', next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
}

function createUtilityBar() {
  if (!isAdmin() || document.querySelector('#bkw-admin-utility')) return;
  const shell = document.querySelector('.ops-shell');
  if (!shell) return;

  const bar = document.createElement('div');
  bar.id = 'bkw-admin-utility';
  bar.innerHTML = `
    <div class="bkw-language-wrap">
      <button type="button" class="bkw-util-btn bkw-lang-btn" id="bkw-admin-language" aria-label="Language"><span>${LANGS[localStorage.getItem('bkw_admin_language') || 'en'].short}</span></button>
      <div class="bkw-language-menu" id="bkw-language-menu" hidden>
        ${Object.entries(LANGS).map(([code, item]) => `<button type="button" data-lang="${code}"><b>${item.short}</b><span>${item.name}</span></button>`).join('')}
      </div>
    </div>
    <button type="button" class="bkw-util-btn" id="bkw-admin-theme" aria-label="Toggle theme">${svgIcon('theme')}</button>
    <button type="button" class="bkw-util-btn bkw-bell-btn" id="bkw-admin-bell" aria-label="Notifications">${svgIcon('bell')}<span class="bkw-badge" id="bkw-admin-badge" hidden>0</span></button>
    <button type="button" class="bkw-profile-btn" id="bkw-admin-profile" aria-label="My profile"><span>${(currentUser?.name || 'U').charAt(0).toUpperCase()}</span></button>
  `;
  document.body.appendChild(bar);

  bar.addEventListener('pointerdown', unlockAudio, { capture: true });
  document.querySelector('#bkw-admin-theme')?.addEventListener('click', () => {
    const current = localStorage.getItem('bkw_admin_theme') || 'light';
    setTheme(current === 'dark' ? 'light' : 'dark');
  });
  document.querySelector('#bkw-admin-bell')?.addEventListener('click', () => clickSidebar('alerts'));
  document.querySelector('#bkw-admin-profile')?.addEventListener('click', () => clickSidebar('my profile'));
  document.querySelector('#bkw-admin-language')?.addEventListener('click', (event) => {
    event.stopPropagation();
    const menu = document.querySelector('#bkw-language-menu');
    if (menu) menu.hidden = !menu.hidden;
  });
  document.querySelector('#bkw-language-menu')?.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-lang]');
    if (!button) return;
    const lang = button.dataset.lang;
    localStorage.setItem('bkw_admin_language', lang);
    const label = document.querySelector('#bkw-admin-language span');
    if (label) label.textContent = LANGS[lang].short;
    document.querySelector('#bkw-language-menu').hidden = true;
    applyLanguageChrome();
  });
  document.addEventListener('click', (event) => {
    const wrap = document.querySelector('.bkw-language-wrap');
    if (wrap && !wrap.contains(event.target)) {
      const menu = document.querySelector('#bkw-language-menu');
      if (menu) menu.hidden = true;
    }
  });
}

function decorateAdminDom() {
  if (!isAdmin()) return;
  createUtilityBar();
  applyLanguageChrome();

  const alerts = findSidebarButton('alerts');
  if (alerts) alerts.classList.add('bkw-sidebar-alerts-source');

  [...document.querySelectorAll('.ops-sidebar div')].forEach(el => {
    if (el.textContent?.includes('Notifications') && el.style?.position === 'absolute') {
      el.classList.add('bkw-notification-popover');
    }
  });
}

function updateBadge(notifications = []) {
  const unread = notifications.filter(item => !item.read_at).length;
  const badge = document.querySelector('#bkw-admin-badge');
  if (!badge) return;
  badge.textContent = unread > 99 ? '99+' : String(unread);
  badge.hidden = unread === 0;
}

async function pollNotifications() {
  if (!isAdmin() || !localStorage.getItem('bkw_admin_token')) return;
  try {
    const result = await api.adminNotifications();
    const list = result.data || [];
    const newest = list.reduce((max, item) => Math.max(max, Number(item.id) || 0), 0);
    updateBadge(list);
    if (lastNotificationId !== null && newest > lastNotificationId) {
      const hasNewUnread = list.some(item => !item.read_at && Number(item.id) > lastNotificationId);
      if (hasNewUnread) notificationChime();
    }
    lastNotificationId = Math.max(lastNotificationId || 0, newest);
  } catch {}
}

async function bootUtility() {
  if (!isAdmin()) return;
  setTheme(localStorage.getItem('bkw_admin_theme') || 'light');
  try {
    if (localStorage.getItem('bkw_admin_token')) {
      const me = await api.adminMe();
      currentUser = me.user || null;
    }
  } catch {}
  decorateAdminDom();
  await pollNotifications();
  if (!pollingTimer) pollingTimer = window.setInterval(pollNotifications, 15000);
}

if (isAdmin()) {
  window.addEventListener('pointerdown', unlockAudio, { capture: true });
  window.addEventListener('keydown', unlockAudio, { capture: true });
  const observer = new MutationObserver(() => decorateAdminDom());
  observer.observe(document.documentElement, { childList: true, subtree: true });
  window.addEventListener('load', bootUtility, { once: true });
  queueMicrotask(bootUtility);
}
