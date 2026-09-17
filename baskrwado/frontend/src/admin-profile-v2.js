import { api } from './lib/api';

const isAdmin = () => window.location.pathname.startsWith('/admin');
let currentAvatarUrl = null;
let rendering = false;

const esc = (value = '') => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#039;');

const title = (value = '') => String(value).replaceAll('_', ' ').replace(/\b\w/g, m => m.toUpperCase());
const initials = (name = '') => String(name).trim().split(/\s+/).slice(0, 2).map(part => part[0] || '').join('').toUpperCase() || 'U';

function setStatus(host, message = '', type = 'success') {
  const node = host.querySelector('[data-profile-status]');
  if (!node) return;
  node.textContent = message;
  node.className = `bkw-profile-status ${type === 'error' ? 'is-error' : 'is-success'}`;
  node.hidden = !message;
}

function setBusy(host, busy) {
  host.querySelectorAll('button, input, select, textarea').forEach(node => {
    if (node.dataset.keepEnabled === 'true') return;
    node.disabled = Boolean(busy);
  });
  host.classList.toggle('is-busy', Boolean(busy));
}

function updateUtilityAvatar(user, avatarUrl = null) {
  const button = document.querySelector('#bkw-admin-profile');
  if (!button) return;
  button.innerHTML = '';
  if (avatarUrl) {
    const img = document.createElement('img');
    img.src = avatarUrl;
    img.alt = user?.name ? `${user.name} profile` : 'Profile';
    button.appendChild(img);
    button.classList.add('has-avatar');
  } else {
    const span = document.createElement('span');
    span.textContent = initials(user?.name);
    button.appendChild(span);
    button.classList.remove('has-avatar');
  }
}

async function resolveAvatar(user, host = null) {
  if (currentAvatarUrl) {
    URL.revokeObjectURL(currentAvatarUrl);
    currentAvatarUrl = null;
  }
  if (!user?.avatar_present) {
    updateUtilityAvatar(user, null);
    if (host) {
      host.querySelectorAll('[data-profile-avatar]').forEach(node => {
        node.classList.remove('has-image');
        node.style.backgroundImage = '';
        node.textContent = initials(user?.name);
      });
    }
    return null;
  }
  try {
    const blob = await api.adminProfileAvatar();
    currentAvatarUrl = URL.createObjectURL(blob);
    updateUtilityAvatar(user, currentAvatarUrl);
    if (host) {
      host.querySelectorAll('[data-profile-avatar]').forEach(node => {
        node.textContent = '';
        node.classList.add('has-image');
        node.style.backgroundImage = `url("${currentAvatarUrl}")`;
      });
    }
    return currentAvatarUrl;
  } catch {
    updateUtilityAvatar(user, null);
    return null;
  }
}

function syncSummary(host, user) {
  const setText = (selector, value) => {
    const node = host.querySelector(selector);
    if (node) node.textContent = value || '—';
  };
  setText('[data-summary-name]', user.name);
  setText('[data-summary-email]', user.email);
  setText('[data-summary-phone]', user.phone);
  setText('[data-summary-department]', user.department);
  setText('[data-summary-job]', user.job_title);
  setText('[data-summary-language]', ({en:'English',hi:'Hindi',gu:'Gujarati'})[user.language] || title(user.language || 'en'));
  setText('[data-summary-role]', title(user.role));
  setText('[data-summary-bio]', user.bio || 'Add a short description about your responsibility in the team.');
  host.querySelectorAll('[data-profile-avatar]').forEach(node => {
    if (!node.classList.contains('has-image')) node.textContent = initials(user.name);
  });
}

function fillForm(host, user) {
  const values = {
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    department: user.department || '',
    job_title: user.job_title || '',
    language: user.language || localStorage.getItem('bkw_admin_language') || 'en',
    timezone: user.timezone || 'Asia/Kolkata',
    bio: user.bio || '',
    role: title(user.role || ''),
  };
  Object.entries(values).forEach(([name, value]) => {
    const field = host.querySelector(`[name="${name}"]`);
    if (field) field.value = value;
  });
}

function profileMarkup(user) {
  return `
    <section class="bkw-profile-v2" aria-label="My profile">
      <div class="bkw-profile-page-title">
        <div><span>MY ACCOUNT</span><h1>My profile</h1><p>Keep your staff identity, language and contact details accurate.</p></div>
        <div data-profile-status class="bkw-profile-status" hidden></div>
      </div>
      <div class="bkw-profile-layout">
        <aside class="bkw-profile-summary-card">
          <div class="bkw-profile-cover"></div>
          <div class="bkw-profile-summary-head">
            <div class="bkw-profile-avatar bkw-profile-avatar-large" data-profile-avatar>${esc(initials(user.name))}</div>
            <button type="button" class="bkw-avatar-camera" data-avatar-trigger aria-label="Change profile image">⌁</button>
          </div>
          <div class="bkw-profile-summary-identity">
            <h2 data-summary-name>${esc(user.name || 'Staff')}</h2>
            <p data-summary-email>${esc(user.email || '')}</p>
            <span class="bkw-role-pill" data-summary-role>${esc(title(user.role || 'staff'))}</span>
          </div>
          <div class="bkw-profile-info-block">
            <h3>Personal Info</h3>
            <dl>
              <div><dt>Full Name</dt><dd data-summary-name>${esc(user.name || '—')}</dd></div>
              <div><dt>Email</dt><dd data-summary-email>${esc(user.email || '—')}</dd></div>
              <div><dt>Phone Number</dt><dd data-summary-phone>${esc(user.phone || '—')}</dd></div>
              <div><dt>Department</dt><dd data-summary-department>${esc(user.department || '—')}</dd></div>
              <div><dt>Designation</dt><dd data-summary-job>${esc(user.job_title || '—')}</dd></div>
              <div><dt>Language</dt><dd data-summary-language>${esc(({en:'English',hi:'Hindi',gu:'Gujarati'})[user.language] || 'English')}</dd></div>
            </dl>
            <div class="bkw-profile-bio"><strong>Bio</strong><p data-summary-bio>${esc(user.bio || 'Add a short description about your responsibility in the team.')}</p></div>
          </div>
        </aside>

        <div class="bkw-profile-editor-column">
          <form class="bkw-profile-editor" data-profile-form>
            <div class="bkw-profile-editor-head">
              <div>
                <label class="bkw-field-label">Profile Image</label>
                <div class="bkw-profile-photo-editor">
                  <div class="bkw-profile-avatar bkw-profile-avatar-edit" data-profile-avatar>${esc(initials(user.name))}</div>
                  <button type="button" class="bkw-avatar-camera is-small" data-avatar-trigger aria-label="Upload profile image">⌁</button>
                </div>
                <input type="file" data-avatar-input accept="image/jpeg,image/png,image/webp" hidden />
                <div class="bkw-avatar-actions">
                  <button type="button" class="bkw-text-button" data-avatar-trigger>Change photo</button>
                  <button type="button" class="bkw-text-button is-danger" data-avatar-remove ${user.avatar_present ? '' : 'hidden'}>Remove</button>
                </div>
                <small>JPG, PNG or WebP · max 5 MB · stored privately</small>
              </div>
            </div>

            <div class="bkw-profile-form-grid">
              <label><span>Full Name *</span><input name="name" required maxlength="120" value="${esc(user.name || '')}" /></label>
              <label><span>Email *</span><input name="email" type="email" readonly value="${esc(user.email || '')}" /></label>
              <label><span>Phone</span><input name="phone" maxlength="30" value="${esc(user.phone || '')}" placeholder="Enter phone number" /></label>
              <label><span>Department</span><input name="department" maxlength="100" value="${esc(user.department || '')}" placeholder="Operations, Review, Support..." /></label>
              <label><span>Designation</span><input name="job_title" maxlength="100" value="${esc(user.job_title || '')}" placeholder="Case Manager, Reviewer..." /></label>
              <label><span>Language *</span><select name="language"><option value="en">English</option><option value="hi">Hindi</option><option value="gu">Gujarati</option></select></label>
              <label><span>Timezone *</span><select name="timezone"><option value="Asia/Kolkata">Asia/Kolkata</option><option value="UTC">UTC</option></select></label>
              <label><span>Role</span><input name="role" readonly value="${esc(title(user.role || ''))}" /></label>
              <label class="bkw-profile-bio-field"><span>Description</span><textarea name="bio" maxlength="1200" rows="4" placeholder="Write a short description...">${esc(user.bio || '')}</textarea></label>
            </div>
            <div class="bkw-profile-form-actions">
              <button type="button" class="btn btn-light" data-profile-cancel>Cancel</button>
              <button type="submit" class="btn btn-dark">Save profile</button>
            </div>
          </form>

          <form class="bkw-profile-security" data-password-form>
            <div><span class="section-eyebrow">SECURITY</span><h3>Change password</h3><p>Changing the password signs out your other sessions.</p></div>
            <div class="bkw-password-grid">
              <label><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password" /></label>
              <label><span>New password</span><input type="password" name="password" required minlength="10" autocomplete="new-password" /></label>
              <label><span>Confirm new password</span><input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password" /></label>
            </div>
            <div class="bkw-profile-form-actions"><button type="submit" class="btn btn-dark">Change password</button></div>
          </form>
        </div>
      </div>
    </section>
  `;
}

async function renderProfile(main, originalRoot) {
  if (rendering || main.querySelector('.bkw-profile-v2')) return;
  rendering = true;
  try {
    const response = await api.adminMe();
    let user = response.user;
    originalRoot.dataset.bkwProfileOriginal = 'true';
    originalRoot.style.setProperty('display', 'none', 'important');
    const wrapper = document.createElement('div');
    wrapper.innerHTML = profileMarkup(user);
    const host = wrapper.firstElementChild;
    main.appendChild(host);
    fillForm(host, user);
    syncSummary(host, user);
    await resolveAvatar(user, host);

    const avatarInput = host.querySelector('[data-avatar-input]');
    host.querySelectorAll('[data-avatar-trigger]').forEach(button => button.addEventListener('click', () => avatarInput?.click()));

    avatarInput?.addEventListener('change', async () => {
      const file = avatarInput.files?.[0];
      if (!file) return;
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        setStatus(host, 'Please choose a JPG, PNG or WebP image.', 'error');
        avatarInput.value = '';
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        setStatus(host, 'Profile image must be 5 MB or smaller.', 'error');
        avatarInput.value = '';
        return;
      }
      setBusy(host, true);
      setStatus(host, 'Uploading profile image…');
      try {
        const result = await api.adminUploadAvatar(file);
        user = {...user, ...result.user};
        await resolveAvatar(user, host);
        syncSummary(host, user);
        const remove = host.querySelector('[data-avatar-remove]');
        if (remove) remove.hidden = false;
        setStatus(host, 'Profile image updated.');
      } catch (error) {
        setStatus(host, error.message || 'Profile image could not be updated.', 'error');
      } finally {
        setBusy(host, false);
        avatarInput.value = '';
      }
    });

    host.querySelector('[data-avatar-remove]')?.addEventListener('click', async () => {
      setBusy(host, true);
      try {
        const result = await api.adminRemoveAvatar();
        user = {...user, ...result.user, avatar_present:false};
        await resolveAvatar(user, host);
        const remove = host.querySelector('[data-avatar-remove]');
        if (remove) remove.hidden = true;
        setStatus(host, 'Profile image removed.');
      } catch (error) {
        setStatus(host, error.message || 'Profile image could not be removed.', 'error');
      } finally {
        setBusy(host, false);
      }
    });

    host.querySelector('[data-profile-cancel]')?.addEventListener('click', () => {
      fillForm(host, user);
      setStatus(host, 'Changes reset.');
    });

    host.querySelector('[data-profile-form]')?.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const data = Object.fromEntries(new FormData(form).entries());
      const payload = {
        name: data.name,
        phone: data.phone || null,
        department: data.department || null,
        job_title: data.job_title || null,
        language: data.language || 'en',
        timezone: data.timezone || 'Asia/Kolkata',
        bio: data.bio || null,
      };
      setBusy(host, true);
      setStatus(host, 'Saving profile…');
      try {
        const result = await api.adminUpdateProfile(payload);
        user = {...user, ...result.user};
        localStorage.setItem('bkw_admin_language', user.language || 'en');
        const languageLabel = document.querySelector('#bkw-admin-language span');
        if (languageLabel) languageLabel.textContent = ({en:'EN',hi:'हि',gu:'ગુ'})[user.language] || 'EN';
        syncSummary(host, user);
        fillForm(host, user);
        updateUtilityAvatar(user, currentAvatarUrl);
        setStatus(host, 'Profile saved successfully.');
        window.dispatchEvent(new CustomEvent('bkw:profile-updated', {detail:{user}}));
      } catch (error) {
        setStatus(host, error.message || 'Profile could not be saved.', 'error');
      } finally {
        setBusy(host, false);
      }
    });

    host.querySelector('[data-password-form]')?.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const data = Object.fromEntries(new FormData(form).entries());
      if (data.password !== data.password_confirmation) {
        setStatus(host, 'New password and confirmation do not match.', 'error');
        return;
      }
      setBusy(host, true);
      try {
        await api.adminChangePassword(data);
        form.reset();
        setStatus(host, 'Password changed. Other sessions were signed out.');
      } catch (error) {
        setStatus(host, error.message || 'Password could not be changed.', 'error');
      } finally {
        setBusy(host, false);
      }
    });
  } catch {
    originalRoot.style.removeProperty('display');
  } finally {
    rendering = false;
  }
}

function syncProfileView() {
  if (!isAdmin()) return;
  const main = document.querySelector('.ops-main');
  if (!main) return;
  const heading = [...main.querySelectorAll('h1')].find(node => node.textContent.trim() === 'Profile & security');
  const existing = main.querySelector('.bkw-profile-v2');

  if (!heading) {
    existing?.remove();
    return;
  }

  const originalRoot = heading.parentElement?.parentElement;
  if (!originalRoot) return;
  originalRoot.style.setProperty('display', 'none', 'important');
  if (!existing) renderProfile(main, originalRoot);
}

async function hydrateUtilityAvatar() {
  if (!isAdmin() || !localStorage.getItem('bkw_admin_token')) return;
  try {
    const response = await api.adminMe();
    await resolveAvatar(response.user);
  } catch {}
}

if (isAdmin()) {
  const observer = new MutationObserver(syncProfileView);
  observer.observe(document.documentElement, {childList:true, subtree:true});
  window.addEventListener('load', () => {
    syncProfileView();
    hydrateUtilityAvatar();
  }, {once:true});
  queueMicrotask(() => {
    syncProfileView();
    hydrateUtilityAvatar();
  });
}
