<template>
  <div class="content pf-page">

    <!-- ══════════════════════════════════════════════════
         Page header
    ══════════════════════════════════════════════════ -->
    <div class="pf-header">
      <div class="pf-header-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </div>
      <div>
        <h1 class="pf-title">{{ $t('profile.title') }}</h1>
        <p class="pf-sub">{{ $t('profile.description') }}</p>
      </div>
    </div>

    <!-- Loading skeleton -->
    <template v-if="isLoadingProfile">
      <div class="pf-card pf-card--skeleton">
        <div class="pf-sk pf-sk--head"></div>
        <div class="pf-sk pf-sk--row"></div>
        <div class="pf-sk pf-sk--row"></div>
        <div class="pf-sk pf-sk--row pf-sk--short"></div>
      </div>
    </template>

    <div v-else class="pf-body">

      <!-- ══════════════════════════════════════════════
           Card 1 - Profile information
      ══════════════════════════════════════════════ -->
      <div class="pf-card">
        <div class="pf-card-head">
          <span class="pf-tag">{{ $t('profile.personalInfo') }}</span>
          <h2 class="pf-card-title">{{ $t('profile.title') }}</h2>
          <p class="pf-card-desc">{{ $t('profile.description') }}</p>
        </div>

        <form @submit.prevent="submitProfile" novalidate>

          <!-- Avatar row -->
          <div class="pf-row pf-row--avatar">
            <div class="pf-row-lbl">
              <span class="pf-lbl">{{ $t('profile.avatarLabel') }}</span>
              <span class="pf-hint">{{ $t('profile.avatarHint') }}</span>
            </div>
            <div class="pf-row-val">
              <div class="pf-avatar-area">
                <button type="button" class="pf-avatar-btn"
                        @click="$refs.avatarInput.click()"
                        :disabled="isSavingProfile"
                        :title="$t('profile.avatarChoose')">
                  <img v-if="avatarPreview" :src="avatarPreview" class="pf-avatar-img" alt="" />
                  <span v-else class="pf-avatar-initials">{{ initials }}</span>
                  <div class="pf-avatar-overlay" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18">
                      <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                  </div>
                </button>
                <input ref="avatarInput" type="file" accept="image/*"
                       class="pf-file-hidden" @change="onAvatarChange"
                       :disabled="isSavingProfile" />
                <button type="button" class="pf-btn-outline"
                        @click="$refs.avatarInput.click()"
                        :disabled="isSavingProfile">
                  <i class="fa fa-upload" aria-hidden="true"></i>
                  {{ $t('profile.avatarChoose') }}
                </button>
              </div>
              <p v-if="profileErrors.avatar" class="pf-error">{{ profileErrors.avatar[0] }}</p>
            </div>
          </div>

          <div class="pf-divider"></div>

          <!-- Name -->
          <div class="pf-row">
            <div class="pf-row-lbl">
              <span class="pf-lbl">{{ $t('profile.nameLabel') }}
                <span class="pf-req" aria-hidden="true">*</span>
              </span>
            </div>
            <div class="pf-row-val">
              <input type="text" class="pf-input"
                     :class="{ 'pf-input--err': profileErrors.name }"
                     :placeholder="$t('profile.namePlaceholder')"
                     v-model="profileForm.name"
                     :disabled="isSavingProfile"
                     autocomplete="name" />
              <p v-if="profileErrors.name" class="pf-error">{{ profileErrors.name[0] }}</p>
            </div>
          </div>

          <!-- Email -->
          <div class="pf-row">
            <div class="pf-row-lbl">
              <span class="pf-lbl">{{ $t('profile.emailLabel') }}
                <span class="pf-req" aria-hidden="true">*</span>
              </span>
            </div>
            <div class="pf-row-val">
              <input type="email" class="pf-input"
                     :class="{ 'pf-input--err': profileErrors.email }"
                     :placeholder="$t('profile.emailPlaceholder')"
                     v-model="profileForm.email"
                     :disabled="isSavingProfile"
                     autocomplete="email" />
              <p v-if="profileErrors.email" class="pf-error">{{ profileErrors.email[0] }}</p>
            </div>
          </div>

          <!-- Language -->
          <div class="pf-row">
            <div class="pf-row-lbl">
              <span class="pf-lbl">{{ $t('profile.languageLabel') }}</span>
              <span class="pf-hint">{{ $t('profile.languageHint') }}</span>
            </div>
            <div class="pf-row-val">
              <div class="pf-select-wrap">
                <select class="pf-select"
                        :class="{ 'pf-input--err': profileErrors.locale }"
                        v-model="profileForm.locale"
                        :disabled="isSavingProfile">
                  <option value="fr">🇫🇷 {{ $t('languages.fr') }}</option>
                  <option value="en">🇬🇧 {{ $t('languages.en') }}</option>
                  <option value="es">🇪🇸 {{ $t('languages.es') }}</option>
                </select>
                <svg class="pf-select-chevron" viewBox="0 0 16 16" fill="currentColor" width="14" height="14" aria-hidden="true">
                  <path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 011.06 0L8 8.94l2.72-2.72a.75.75 0 111.06 1.06l-3.25 3.25a.75.75 0 01-1.06 0L4.22 7.28a.75.75 0 010-1.06z" clip-rule="evenodd"/>
                </svg>
              </div>
              <p v-if="profileErrors.locale" class="pf-error">{{ profileErrors.locale[0] }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="pf-card-foot">
            <button type="submit" class="pf-btn-primary" :disabled="isSavingProfile">
              <svg v-if="isSavingProfile" class="pf-spin" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.5" width="16" height="16" aria-hidden="true">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              <svg v-else viewBox="0 0 16 16" fill="currentColor" width="14" height="14" aria-hidden="true">
                <path d="M2 2.5A.5.5 0 0 1 2.5 2h8a.5.5 0 0 1 .354.146l2.5 2.5A.5.5 0 0 1 13.5 5v9a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-12zm3 0v4.5h5V2.5H5zm5 12v-4H6v4h4zm1 0h1V5.207L11.793 4H11v10.5zM4.5 14.5V10H3.5v4.5h1z"/>
              </svg>
              {{ $t('profile.save') }}
            </button>
          </div>

        </form>
      </div><!-- /Card 1 -->

      <!-- ══════════════════════════════════════════════
           Card 2 - Security / Password
      ══════════════════════════════════════════════ -->
      <div class="pf-card">
        <div class="pf-card-head">
          <span class="pf-tag pf-tag--sec">{{ $t('profile.security') }}</span>
          <h2 class="pf-card-title">{{ $t('profile.passwordTitle') }}</h2>
          <p class="pf-card-desc">{{ $t('profile.passwordDescription') }}</p>
        </div>

        <form @submit.prevent="submitPassword" novalidate>

          <!-- Current password -->
          <div class="pf-row">
            <div class="pf-row-lbl">
              <span class="pf-lbl">{{ $t('profile.currentPassword') }}</span>
            </div>
            <div class="pf-row-val">
              <div class="pf-pwd-wrap">
                <input :type="showPwd.current ? 'text' : 'password'"
                       class="pf-input pf-input--pwd"
                       :class="{ 'pf-input--err': passwordErrors.current_password }"
                       v-model="passwordForm.current_password"
                       :disabled="isSavingPassword"
                       autocomplete="current-password" />
                <button type="button" class="pf-pwd-eye"
                        @click="showPwd.current = !showPwd.current"
                        tabindex="-1"
                        :aria-label="showPwd.current ? 'Hide password' : 'Show password'">
                  <i :class="showPwd.current ? 'fa fa-eye-slash' : 'fa fa-eye'" aria-hidden="true"></i>
                </button>
              </div>
              <p v-if="passwordErrors.current_password" class="pf-error">{{ passwordErrors.current_password[0] }}</p>
            </div>
          </div>

          <!-- New password -->
          <div class="pf-row">
            <div class="pf-row-lbl">
              <span class="pf-lbl">{{ $t('profile.newPassword') }}</span>
              <span class="pf-hint">{{ $t('profile.passwordHint') }}</span>
            </div>
            <div class="pf-row-val">
              <div class="pf-pwd-wrap">
                <input :type="showPwd.new ? 'text' : 'password'"
                       class="pf-input pf-input--pwd"
                       :class="{ 'pf-input--err': passwordErrors.password }"
                       v-model="passwordForm.password"
                       :disabled="isSavingPassword"
                       autocomplete="new-password" />
                <button type="button" class="pf-pwd-eye"
                        @click="showPwd.new = !showPwd.new"
                        tabindex="-1">
                  <i :class="showPwd.new ? 'fa fa-eye-slash' : 'fa fa-eye'" aria-hidden="true"></i>
                </button>
              </div>
              <!-- Strength meter -->
              <div v-if="passwordForm.password" class="pf-strength">
                <div class="pf-strength-bars">
                  <div v-for="n in 4" :key="n" class="pf-strength-bar"
                       :class="pwdStrength >= n ? 'pf-strength-bar--' + pwdStrengthColor : ''">
                  </div>
                </div>
                <span class="pf-strength-lbl" :class="'pf-strength-lbl--' + pwdStrengthColor">
                  {{ pwdStrengthLabel }}
                </span>
              </div>
              <p v-if="passwordErrors.password" class="pf-error">{{ passwordErrors.password[0] }}</p>
            </div>
          </div>

          <!-- Confirm password -->
          <div class="pf-row">
            <div class="pf-row-lbl">
              <span class="pf-lbl">{{ $t('profile.confirmPassword') }}</span>
            </div>
            <div class="pf-row-val">
              <div class="pf-pwd-wrap">
                <input :type="showPwd.confirm ? 'text' : 'password'"
                       class="pf-input pf-input--pwd"
                       :class="{ 'pf-input--err': passwordErrors.password_confirmation }"
                       v-model="passwordForm.password_confirmation"
                       :disabled="isSavingPassword"
                       autocomplete="new-password" />
                <button type="button" class="pf-pwd-eye"
                        @click="showPwd.confirm = !showPwd.confirm"
                        tabindex="-1">
                  <i :class="showPwd.confirm ? 'fa fa-eye-slash' : 'fa fa-eye'" aria-hidden="true"></i>
                </button>
              </div>
              <!-- Match indicator -->
              <div v-if="passwordForm.password_confirmation && passwordForm.password"
                   class="pf-pwd-match"
                   :class="passwordsMatch ? 'pf-pwd-match--ok' : 'pf-pwd-match--no'">
                <svg v-if="passwordsMatch" viewBox="0 0 16 16" fill="currentColor" width="13" height="13" aria-hidden="true">
                  <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd"/>
                </svg>
                <svg v-else viewBox="0 0 16 16" fill="currentColor" width="13" height="13" aria-hidden="true">
                  <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z"/>
                </svg>
                {{ passwordsMatch ? $t('profile.pwdMatch') : $t('profile.pwdNoMatch') }}
              </div>
              <p v-if="passwordErrors.password_confirmation" class="pf-error">{{ passwordErrors.password_confirmation[0] }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="pf-card-foot">
            <button type="submit" class="pf-btn-primary pf-btn--sec" :disabled="isSavingPassword">
              <svg v-if="isSavingPassword" class="pf-spin" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.5" width="16" height="16" aria-hidden="true">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              <svg v-else viewBox="0 0 16 16" fill="currentColor" width="14" height="14" aria-hidden="true">
                <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
              </svg>
              {{ $t('profile.changePassword') }}
            </button>
          </div>

        </form>
      </div><!-- /Card 2 -->

    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { createToaster } from '@meforma/vue-toaster';
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n';

const toaster = createToaster({});
const { t }   = useI18n();

// ── Avatar ─────────────────────────────────────────────────────────────────
const defaultAvatar = '/assets/media/avatars/avatar13.jpg';
const avatarPreview = ref(defaultAvatar);
const avatarFile    = ref(null);

const setAvatarPreview = (url) => {
  if (avatarPreview.value?.startsWith('blob:')) URL.revokeObjectURL(avatarPreview.value);
  avatarPreview.value = url || defaultAvatar;
};

// ── Forms ──────────────────────────────────────────────────────────────────
const profileForm = reactive({ name: '', email: '', locale: 'fr' });
const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' });

const profileErrors  = ref({});
const passwordErrors = ref({});
const isSavingProfile  = ref(false);
const isSavingPassword = ref(false);
const isLoadingProfile = ref(true);

// ── Password visibility ────────────────────────────────────────────────────
const showPwd = reactive({ current: false, new: false, confirm: false });

// ── Computed ───────────────────────────────────────────────────────────────
const initials = computed(() => {
  const name = profileForm.name || '';
  return name.split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
});

const pwdStrength = computed(() => {
  const p = passwordForm.password;
  if (!p) return 0;
  let s = 0;
  if (p.length >= 8)          s++;
  if (/[A-Z]/.test(p))        s++;
  if (/[0-9]/.test(p))        s++;
  if (/[^A-Za-z0-9]/.test(p)) s++;
  return s;
});

const pwdStrengthColor = computed(() =>
  ['', 'red', 'orange', 'yellow', 'green'][pwdStrength.value] ?? ''
);

const pwdStrengthLabel = computed(() => [
  '',
  t('profile.pwdStrengthWeak'),
  t('profile.pwdStrengthFair'),
  t('profile.pwdStrengthGood'),
  t('profile.pwdStrengthStrong'),
][pwdStrength.value] ?? '');

const passwordsMatch = computed(() =>
  !!passwordForm.password && passwordForm.password === passwordForm.password_confirmation
);

// ── API: load profile ──────────────────────────────────────────────────────
const fetchProfile = async () => {
  isLoadingProfile.value = true;
  profileErrors.value    = {};
  try {
    const { data } = await axios.get('/user');
    profileForm.name   = data?.user?.name  ?? '';
    profileForm.email  = data?.user?.email ?? '';
    profileForm.locale = data?.user?.locale ?? 'fr';
    if (data?.user?.locale)     setLocale(data.user.locale);
    if (data?.user?.avatar_url) setAvatarPreview(data.user.avatar_url);
    else if (!avatarFile.value) setAvatarPreview(defaultAvatar);
  } catch {
    toaster.error(t('notifications.profileLoadError'));
  } finally {
    isLoadingProfile.value = false;
  }
};

onMounted(fetchProfile);

// ── Avatar change ──────────────────────────────────────────────────────────
const onAvatarChange = (event) => {
  const [file] = event.target.files || [];
  avatarFile.value = file || null;
  if (file) setAvatarPreview(URL.createObjectURL(file));
  else      setAvatarPreview(defaultAvatar);
};

// ── API: save profile ──────────────────────────────────────────────────────
const submitProfile = async () => {
  isSavingProfile.value = true;
  profileErrors.value   = {};
  try {
    const fd = new FormData();
    fd.append('_method', 'put');
    fd.append('name',    profileForm.name  ?? '');
    fd.append('email',   profileForm.email ?? '');
    fd.append('locale',  profileForm.locale ?? 'fr');
    if (avatarFile.value) fd.append('avatar', avatarFile.value);

    const { data } = await axios.post('/user', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    toaster.success(t('notifications.profileUpdateSuccess'));
    setLocale(data?.user?.locale ?? profileForm.locale ?? 'fr');
    avatarFile.value = null;
    if (data?.user?.avatar_url) setAvatarPreview(data.user.avatar_url);
    else                        setAvatarPreview(defaultAvatar);
    if (data?.user) {
      window.dispatchEvent(new CustomEvent('user-profile-updated', { detail: { user: data.user } }));
    }
  } catch (error) {
    if (error.response?.status === 422) {
      profileErrors.value = error.response.data?.errors || {};
      return;
    }
    toaster.error(t('notifications.profileUpdateError'));
  } finally {
    isSavingProfile.value = false;
  }
};

// ── API: save password ─────────────────────────────────────────────────────
const submitPassword = async () => {
  isSavingPassword.value = true;
  passwordErrors.value   = {};
  try {
    await axios.post('/user', {
      _method:               'put',
      current_password:      passwordForm.current_password,
      password:              passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    });
    toaster.success(t('notifications.passwordUpdateSuccess'));
    passwordForm.current_password      = '';
    passwordForm.password              = '';
    passwordForm.password_confirmation = '';
    showPwd.current = showPwd.new = showPwd.confirm = false;
  } catch (error) {
    if (error.response?.status === 422) {
      passwordErrors.value = error.response.data?.errors || {};
      return;
    }
    toaster.error(t('notifications.passwordUpdateError'));
  } finally {
    isSavingPassword.value = false;
  }
};
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════
   PROFILE PAGE - design tokens (mirrors settings.vue)
   ═══════════════════════════════════════════════════════════════ */
.pf-page {
  --pf-page:     #F0F2FA;
  --pf-surface:  #FFFFFF;
  --pf-border:   #E3E6F3;
  --pf-shadow:   0 1px 2px rgba(14,18,50,.04), 0 4px 14px rgba(14,18,50,.06);
  --pf-text:     #111827;
  --pf-muted:    #64708A;
  --pf-field-bg: #F8F9FE;
  --pf-accent:   #E91E63;
  --pf-accent-h: #c2185b;
  --pf-radius:   10px;
  --pf-row-px:   2rem;
  --pf-row-py:   1.375rem;
}

@media (prefers-color-scheme: dark) {
  .pf-page {
    --pf-page:     #0C0E1C;
    --pf-surface:  #13162A;
    --pf-border:   rgba(255,255,255,.07);
    --pf-shadow:   0 1px 2px rgba(0,0,0,.3), 0 4px 14px rgba(0,0,0,.25);
    --pf-text:     #E1E5FA;
    --pf-muted:    #8290B8;
    --pf-field-bg: #191C30;
  }
}
:root[data-theme="dark"]  .pf-page {
  --pf-page:     #0C0E1C;
  --pf-surface:  #13162A;
  --pf-border:   rgba(255,255,255,.07);
  --pf-shadow:   0 1px 2px rgba(0,0,0,.3), 0 4px 14px rgba(0,0,0,.25);
  --pf-text:     #E1E5FA;
  --pf-muted:    #8290B8;
  --pf-field-bg: #191C30;
}
:root[data-theme="light"] .pf-page {
  --pf-page:     #F0F2FA;
  --pf-surface:  #FFFFFF;
  --pf-border:   #E3E6F3;
  --pf-shadow:   0 1px 2px rgba(14,18,50,.04), 0 4px 14px rgba(14,18,50,.06);
  --pf-text:     #111827;
  --pf-muted:    #64708A;
  --pf-field-bg: #F8F9FE;
}

/* ── Page wrapper ──────────────────────────────────────────────── */
.pf-page {
  padding-bottom: 64px;
}

/* ── Page header ───────────────────────────────────────────────── */
.pf-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 2rem;
  padding-top: 4px;
}

.pf-header-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: rgba(233,30,99,.10);
  color: var(--pf-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pf-header-icon svg { width: 24px; height: 24px; }

.pf-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--pf-text);
  margin: 0 0 .2rem;
  letter-spacing: -.03em;
  line-height: 1.2;
}

.pf-sub {
  font-size: .875rem;
  color: var(--pf-muted);
  margin: 0;
}

/* ── Body: max-width centered column ──────────────────────────── */
.pf-body {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  max-width: 760px;
}

/* ── Card ──────────────────────────────────────────────────────── */
.pf-card {
  background: var(--pf-surface);
  border: 1px solid var(--pf-border);
  border-radius: var(--pf-radius);
  box-shadow: var(--pf-shadow);
  overflow: hidden;
}

/* ── Card header ───────────────────────────────────────────────── */
.pf-card-head {
  padding: 1.5rem var(--pf-row-px) 1.25rem;
  border-bottom: 1px solid var(--pf-border);
}

.pf-tag {
  display: inline-block;
  font-size: .65rem;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--pf-accent);
  margin-bottom: .5rem;
}
.pf-tag--sec { color: #7c3aed; }

.pf-card-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--pf-text);
  margin: 0 0 .25rem;
  line-height: 1.3;
}

.pf-card-desc {
  font-size: .8rem;
  color: var(--pf-muted);
  margin: 0;
  line-height: 1.5;
}

/* ── Divider ───────────────────────────────────────────────────── */
.pf-divider {
  height: 1px;
  background: var(--pf-border);
}

/* ── Field rows ────────────────────────────────────────────────── */
.pf-row {
  display: grid;
  grid-template-columns: 175px 1fr;
  gap: 1rem 1.75rem;
  padding: var(--pf-row-py) var(--pf-row-px);
  align-items: flex-start;
  border-bottom: 1px solid var(--pf-border);
}
.pf-row:last-of-type { border-bottom: none; }

.pf-row-lbl { padding-top: .35rem; }

.pf-lbl {
  display: block;
  font-size: .875rem;
  font-weight: 600;
  color: var(--pf-text);
  margin-bottom: .2rem;
  line-height: 1.4;
}
.pf-req  { color: var(--pf-accent); margin-left: 1px; }
.pf-hint {
  display: block;
  font-size: .73rem;
  color: var(--pf-muted);
  line-height: 1.45;
}

.pf-row-val { min-width: 0; }

/* ── Input ─────────────────────────────────────────────────────── */
.pf-input {
  display: block;
  width: 100%;
  height: 38px;
  padding: 0 .75rem;
  font-size: .875rem;
  color: var(--pf-text);
  background: var(--pf-field-bg);
  border: 1.5px solid var(--pf-border);
  border-radius: 7px;
  outline: none;
  transition: border-color .15s, box-shadow .15s, background .15s;
  font-family: inherit;
}
.pf-input::placeholder { color: var(--pf-muted); opacity: .65; }
.pf-input:focus {
  background: var(--pf-surface);
  border-color: var(--pf-accent);
  box-shadow: 0 0 0 3px rgba(233,30,99,.12);
  color: var(--pf-text);
}
.pf-input:disabled { opacity: .55; cursor: not-allowed; }
.pf-input--err { border-color: #dc3545 !important; }
.pf-input--err:focus { box-shadow: 0 0 0 3px rgba(220,53,69,.15) !important; }

/* ── Password wrapper (input + eye toggle) ─────────────────────── */
.pf-pwd-wrap {
  display: flex;
  align-items: center;
  position: relative;
}
.pf-input--pwd { padding-right: 2.4rem; }
.pf-pwd-eye {
  position: absolute;
  right: .6rem;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--pf-muted);
  padding: .2rem;
  line-height: 1;
  border-radius: 4px;
  transition: color .15s;
}
.pf-pwd-eye:hover { color: var(--pf-text); }
.pf-pwd-eye .fa { font-size: .875rem; }

/* ── Password strength meter ───────────────────────────────────── */
.pf-strength {
  display: flex;
  align-items: center;
  gap: .6rem;
  margin-top: .5rem;
}
.pf-strength-bars {
  display: flex;
  gap: 3px;
}
.pf-strength-bar {
  width: 36px;
  height: 4px;
  border-radius: 99px;
  background: var(--pf-border);
  transition: background .2s;
}
.pf-strength-bar--red    { background: #dc3545; }
.pf-strength-bar--orange { background: #fd7e14; }
.pf-strength-bar--yellow { background: #ffc107; }
.pf-strength-bar--green  { background: #198754; }

.pf-strength-lbl {
  font-size: .72rem;
  font-weight: 600;
}
.pf-strength-lbl--red    { color: #dc3545; }
.pf-strength-lbl--orange { color: #fd7e14; }
.pf-strength-lbl--yellow { color: #c89700; }
.pf-strength-lbl--green  { color: #198754; }

/* ── Password match indicator ──────────────────────────────────── */
.pf-pwd-match {
  display: flex;
  align-items: center;
  gap: .35rem;
  font-size: .78rem;
  font-weight: 600;
  margin-top: .45rem;
}
.pf-pwd-match--ok { color: #198754; }
.pf-pwd-match--no { color: #dc3545; }

/* ── Select ────────────────────────────────────────────────────── */
.pf-select-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.pf-select {
  display: block;
  width: 100%;
  height: 38px;
  padding: 0 2.25rem 0 .75rem;
  font-size: .875rem;
  color: var(--pf-text);
  background: var(--pf-field-bg);
  border: 1.5px solid var(--pf-border);
  border-radius: 7px;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
  transition: border-color .15s, box-shadow .15s;
  font-family: inherit;
}
.pf-select:not(:focus) {
  border-color: var(--pf-border);
  box-shadow: none;
  outline: none;
}
.pf-select:focus {
  background: var(--pf-surface);
  border-color: var(--pf-accent);
  box-shadow: 0 0 0 3px rgba(233,30,99,.12);
  outline: none;
}
.pf-select:disabled { opacity: .55; cursor: not-allowed; }
.pf-select-chevron {
  position: absolute;
  right: .75rem;
  width: 14px;
  height: 14px;
  color: var(--pf-muted);
  pointer-events: none;
  flex-shrink: 0;
}

/* ── Error text ────────────────────────────────────────────────── */
.pf-error {
  font-size: .78rem;
  color: #dc3545;
  margin: .3rem 0 0;
}

/* ── Avatar upload ─────────────────────────────────────────────── */
.pf-row--avatar { align-items: center; }

.pf-avatar-area {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.pf-avatar-btn {
  position: relative;
  width: 72px;
  height: 72px;
  border-radius: 50%;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid var(--pf-border);
  background: var(--pf-field-bg);
  flex-shrink: 0;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color .2s;
}
.pf-avatar-btn:hover { border-color: var(--pf-accent); }
.pf-avatar-btn:disabled { opacity: .6; cursor: default; }

.pf-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.pf-avatar-initials {
  font-size: 1.375rem;
  font-weight: 800;
  color: var(--pf-accent);
  user-select: none;
  letter-spacing: -.04em;
}

.pf-avatar-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,.45);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity .18s;
  color: #fff;
}
.pf-avatar-btn:hover .pf-avatar-overlay { opacity: 1; }

.pf-file-hidden { display: none; }

/* ── Outline button (avatar choose) ───────────────────────────── */
.pf-btn-outline {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  height: 34px;
  padding: 0 .9rem;
  font-size: .8rem;
  font-weight: 600;
  color: var(--pf-text);
  background: var(--pf-surface);
  border: 1.5px solid var(--pf-border);
  border-radius: 7px;
  cursor: pointer;
  transition: border-color .15s, color .15s, background .15s;
  white-space: nowrap;
  font-family: inherit;
}
.pf-btn-outline:hover:not(:disabled) {
  border-color: var(--pf-accent);
  color: var(--pf-accent);
}
.pf-btn-outline:disabled { opacity: .5; cursor: not-allowed; }

/* ── Card footer ───────────────────────────────────────────────── */
.pf-card-foot {
  padding: 1.125rem var(--pf-row-px);
  border-top: 1px solid var(--pf-border);
  background: rgba(0,0,0,.018);
  display: flex;
  align-items: center;
  gap: .75rem;
}

/* ── Primary button ────────────────────────────────────────────── */
.pf-btn-primary {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  height: 36px;
  padding: 0 1.125rem;
  font-size: .875rem;
  font-weight: 600;
  color: #fff;
  background: var(--pf-accent);
  border: none;
  border-radius: 7px;
  cursor: pointer;
  transition: background .15s, transform .12s, box-shadow .15s;
  white-space: nowrap;
  font-family: inherit;
}
.pf-btn-primary:hover:not(:disabled) {
  background: var(--pf-accent-h);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233,30,99,.25);
}
.pf-btn-primary:active:not(:disabled) { transform: translateY(0); }
.pf-btn-primary:disabled { opacity: .55; cursor: not-allowed; }

.pf-btn--sec {
  --pf-accent:   #7c3aed;
  --pf-accent-h: #6d28d9;
}

/* ── Spinner ───────────────────────────────────────────────────── */
@keyframes pf-spin { to { transform: rotate(360deg); } }
.pf-spin { animation: pf-spin .7s linear infinite; }

/* ── Loading skeleton ──────────────────────────────────────────── */
@keyframes pf-shimmer {
  0%   { background-position: -400px 0; }
  100% { background-position:  400px 0; }
}
.pf-card--skeleton { padding: var(--pf-row-py) var(--pf-row-px); }
.pf-sk {
  border-radius: 6px;
  background: linear-gradient(90deg, var(--pf-border) 25%, var(--pf-field-bg) 50%, var(--pf-border) 75%);
  background-size: 800px 100%;
  animation: pf-shimmer 1.4s infinite linear;
}
.pf-sk--head  { height: 18px; width: 40%; margin-bottom: 1.25rem; }
.pf-sk--row   { height: 38px; margin-bottom: .75rem; }
.pf-sk--short { width: 60%; }

/* ── Responsive ────────────────────────────────────────────────── */
@media (max-width: 640px) {
  .pf-header { flex-direction: column; align-items: flex-start; gap: .75rem; }
  .pf-row {
    grid-template-columns: 1fr;
    gap: .5rem;
    padding: 1rem var(--pf-row-px);
  }
  .pf-row-lbl { padding-top: 0; }
  .pf-card-head,
  .pf-card-foot { padding-left: 1.25rem; padding-right: 1.25rem; }
  .pf-row { padding-left: 1.25rem; padding-right: 1.25rem; }
  --pf-row-px: 1.25rem;

  .pf-strength-bar { width: 28px; }
}
</style>
