<template>

  <!-- Page Content -->
  <div class="content content-boxed">
    <!-- User Profile -->
    <BaseBlock :title="$t('profile.title')">
      <form @submit.prevent="submitProfile">
        <div class="row push">
          <div class="col-lg-4">
            <p class="fs-sm text-muted">
              {{ $t("profile.description") }}
            </p>
          </div>
          <div class="col-lg-8 col-xl-5">
            <div class="mb-4">
              <label class="form-label" for="one-profile-edit-name">{{
                $t("profile.nameLabel")
              }}</label>
              <input
                type="text"
                class="form-control"
                id="one-profile-edit-name"
                name="one-profile-edit-name"
                :placeholder="$t('profile.namePlaceholder')"
                v-model="profileForm.name"
                :disabled="isLoadingProfile || isSavingProfile"
                :class="{ 'is-invalid': profileErrors.name }"
              />
              <div v-if="profileErrors.name" class="invalid-feedback">
                {{ profileErrors.name[0] }}
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label" for="one-profile-edit-email"
                >{{ $t("profile.emailLabel") }}</label
              >
              <input
                type="email"
                class="form-control"
                id="one-profile-edit-email"
                name="one-profile-edit-email"
                :placeholder="$t('profile.emailPlaceholder')"
                v-model="profileForm.email"
                :disabled="isLoadingProfile || isSavingProfile"
                :class="{ 'is-invalid': profileErrors.email }"
              />
              <div v-if="profileErrors.email" class="invalid-feedback">
                {{ profileErrors.email[0] }}
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label" for="one-profile-edit-locale">{{
                $t("profile.languageLabel")
              }}</label>
              <select
                id="one-profile-edit-locale"
                name="one-profile-edit-locale"
                class="form-select"
                v-model="profileForm.locale"
                :disabled="isLoadingProfile || isSavingProfile"
                :class="{ 'is-invalid': profileErrors.locale }"
              >
                <option value="es">{{ $t("languages.es") }}</option>
                <option value="en">{{ $t("languages.en") }}</option>
                <option value="fr">{{ $t("languages.fr") }}</option>
              </select>
              <div v-if="profileErrors.locale" class="invalid-feedback">
                {{ profileErrors.locale[0] }}
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label">{{ $t("profile.avatarLabel") }}</label>
              <div class="mb-4">
                <img
                  class="img-avatar"
                  :src="avatarPreview"
                  alt=""
                />
              </div>
              <div class="mb-4">
                <label for="one-profile-edit-avatar" class="form-label"
                  >{{ $t("profile.avatarChoose") }}</label
                >
                <input
                  class="form-control"
                  type="file"
                  id="one-profile-edit-avatar"
                  accept="image/*"
                  @change="onAvatarChange"
                  :disabled="isSavingProfile"
                  :class="{ 'is-invalid': profileErrors.avatar }"
                />
                <div v-if="profileErrors.avatar" class="invalid-feedback">
                  {{ profileErrors.avatar[0] }}
                </div>
              </div>
            </div>
            <div class="mb-4">
              <button
                type="submit"
                class="btn btn-alt-primary"
                :disabled="isSavingProfile || isLoadingProfile"
              >
                <span
                  v-if="isSavingProfile"
                  class="spinner-border spinner-border-sm me-2"
                  role="status"
                  aria-hidden="true"
                ></span>
                {{ $t("profile.save") }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </BaseBlock>
    <!-- END User Profile -->

    <!-- Change Password -->
    <BaseBlock :title="$t('profile.passwordTitle')">
      <form @submit.prevent="submitPassword">
        <div class="row push">
          <div class="col-lg-4">
            <p class="fs-sm text-muted">
              {{ $t("profile.passwordDescription") }}
            </p>
          </div>
          <div class="col-lg-8 col-xl-5">
            <div class="mb-4">
              <label class="form-label" for="one-profile-edit-password"
                >{{ $t("profile.currentPassword") }}</label
              >
              <input
                type="password"
                class="form-control"
                id="one-profile-edit-password"
                name="one-profile-edit-password"
                v-model="passwordForm.current_password"
                :disabled="isSavingPassword"
                :class="{ 'is-invalid': passwordErrors.current_password }"
              />
              <div v-if="passwordErrors.current_password" class="invalid-feedback">
                {{ passwordErrors.current_password[0] }}
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-12">
                <label class="form-label" for="one-profile-edit-password-new"
                  >{{ $t("profile.newPassword") }}</label
                >
                <input
                type="password"
                class="form-control"
                id="one-profile-edit-password-new"
                name="one-profile-edit-password-new"
                v-model="passwordForm.password"
                :disabled="isSavingPassword"
                :class="{ 'is-invalid': passwordErrors.password }"
              />
                <div v-if="passwordErrors.password" class="invalid-feedback">
                  {{ passwordErrors.password[0] }}
                </div>
              </div>
            </div>
            <div class="row mb-4">
              <div class="col-12">
                <label
                  class="form-label"
                  for="one-profile-edit-password-new-confirm"
                  >{{ $t("profile.confirmPassword") }}</label
                >
                <input
                type="password"
                class="form-control"
                id="one-profile-edit-password-new-confirm"
                name="one-profile-edit-password-new-confirm"
                v-model="passwordForm.password_confirmation"
                :disabled="isSavingPassword"
                :class="{ 'is-invalid': passwordErrors.password_confirmation }"
              />
                <div
                  v-if="passwordErrors.password_confirmation"
                  class="invalid-feedback"
                >
                  {{ passwordErrors.password_confirmation[0] }}
                </div>
              </div>
            </div>
            <div class="mb-4">
              <button
                type="submit"
                class="btn btn-alt-primary"
                :disabled="isSavingPassword"
              >
                <span
                  v-if="isSavingPassword"
                  class="spinner-border spinner-border-sm me-2"
                  role="status"
                  aria-hidden="true"
                ></span>
                {{ $t("profile.save") }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </BaseBlock>
    <!-- END Change Password -->

</div>
</template>

<script setup>
import { reactive, ref, onMounted } from "vue";
import axios from 'axios'
import { createToaster } from '@meforma/vue-toaster';
import { useI18n } from "vue-i18n";
import { setLocale } from "@/i18n";
const toaster = createToaster({ /* options */ });

const defaultAvatar = '/assets/media/avatars/avatar13.jpg';
const avatarPreview = ref(defaultAvatar);
const avatarFile = ref(null);
const { t } = useI18n();

const profileForm = reactive({
  name: '',
  email: '',
  locale: 'es',
});
const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const profileErrors = ref({});
const passwordErrors = ref({});
const isSavingProfile = ref(false);
const isSavingPassword = ref(false);
const isLoadingProfile = ref(true);

const setAvatarPreview = (url) => {
  if (avatarPreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(avatarPreview.value);
  }

  avatarPreview.value = url || defaultAvatar;
};

const fetchProfile = async () => {
  isLoadingProfile.value = true;
  profileErrors.value = {};

  try {
    const { data } = await axios.get('/user');
    profileForm.name = data?.user?.name ?? '';
    profileForm.email = data?.user?.email ?? '';
    profileForm.locale = data?.user?.locale ?? 'es';
    if (data?.user?.locale) {
      setLocale(data.user.locale);
    }

    if (data?.user?.avatar_url) {
      setAvatarPreview(data.user.avatar_url);
    } else if (!avatarFile.value) {
      setAvatarPreview(defaultAvatar);
    }
  } catch (error) {
    toaster.error(t('notifications.profileLoadError'));
  } finally {
    isLoadingProfile.value = false;
  }
};

onMounted(fetchProfile);

const onAvatarChange = (event) => {
  const [file] = event.target.files || [];
  avatarFile.value = file || null;

  if (file) {
    setAvatarPreview(URL.createObjectURL(file));
  } else {
    setAvatarPreview(defaultAvatar);
  }
};

const submitProfile = async () => {
  isSavingProfile.value = true;
  profileErrors.value = {};

  try {
    const formData = new FormData();
    formData.append('_method', 'put');
    formData.append('name', profileForm.name ?? '');
    formData.append('email', profileForm.email ?? '');
    formData.append('locale', profileForm.locale ?? 'es');
    if (avatarFile.value) {
      formData.append('avatar', avatarFile.value);
    }

    const { data } = await axios.post('/user', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    toaster.success(t('notifications.profileUpdateSuccess'));
    setLocale(data?.user?.locale ?? profileForm.locale ?? 'es');
    avatarFile.value = null;
    if (data?.user?.avatar_url) {
      setAvatarPreview(data.user.avatar_url);
    } else {
      setAvatarPreview(defaultAvatar);
    }
    if (data?.user) {
      window.dispatchEvent(new CustomEvent('user-profile-updated', {
        detail: { user: data.user },
      }));
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

const submitPassword = async () => {
  isSavingPassword.value = true;
  passwordErrors.value = {};

  try {
    await axios.post('/user', {
      _method: 'put',
      current_password: passwordForm.current_password,
      password: passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    });

    toaster.success(t('notifications.passwordUpdateSuccess'));
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
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


<style lang="scss">
// Flatpickr + Custom overrides
@import "flatpickr/dist/flatpickr.css";
@import "@/assets/scss/vendor/flatpickr";

// Dropzone + Custom overrides
@import "dropzone/dist/dropzone.css";
@import "@/assets/scss/vendor/dropzone";
</style>
