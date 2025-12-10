<template>

  <!-- Page Content -->
  <div class="content content-boxed">
    <!-- User Profile -->
    <BaseBlock title="Perfil de usuario">
      <form @submit.prevent="submitProfile">
        <div class="row push">
          <div class="col-lg-4">
            <p class="fs-sm text-muted">
              Actualiza tus datos de acceso y la imagen de tu cuenta.
            </p>
          </div>
          <div class="col-lg-8 col-xl-5">
            <div class="mb-4">
              <label class="form-label" for="one-profile-edit-name">Nombre</label>
              <input
                type="text"
                class="form-control"
                id="one-profile-edit-name"
                name="one-profile-edit-name"
                placeholder="Introduce tu nombre.."
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
                >Correo electrónico</label
              >
              <input
                type="email"
                class="form-control"
                id="one-profile-edit-email"
                name="one-profile-edit-email"
                placeholder="Enter your email.."
                v-model="profileForm.email"
                :disabled="isLoadingProfile || isSavingProfile"
                :class="{ 'is-invalid': profileErrors.email }"
              />
              <div v-if="profileErrors.email" class="invalid-feedback">
                {{ profileErrors.email[0] }}
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label">Imagen de perfil</label>
              <div class="mb-4">
                <img
                  class="img-avatar"
                  :src="avatarPreview"
                  alt=""
                />
              </div>
              <div class="mb-4">
                <label for="one-profile-edit-avatar" class="form-label"
                  >Elige un nuevo imagen</label
                >
                <input
                  class="form-control"
                  type="file"
                  id="one-profile-edit-avatar"
                  accept="image/*"
                  @change="onAvatarChange"
                  :disabled="isSavingProfile"
                />
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
                Guardar
              </button>
            </div>
          </div>
        </div>
      </form>
    </BaseBlock>
    <!-- END User Profile -->

    <!-- Change Password -->
    <BaseBlock title="Cambiar contraseña">
      <form @submit.prevent="submitPassword">
        <div class="row push">
          <div class="col-lg-4">
            <p class="fs-sm text-muted">
              Cambiar tu contraseña de inicio de sesión es una forma fácil de mantener tu cuenta segura.
            </p>
          </div>
          <div class="col-lg-8 col-xl-5">
            <div class="mb-4">
              <label class="form-label" for="one-profile-edit-password"
                >Contraseña actual</label
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
                  >Nueva contraseña</label
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
                  >Confirmar nueva contraseña</label
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
                Guardar
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
const toaster = createToaster({ /* options */ });

const defaultAvatar = '/assets/media/avatars/avatar13.jpg';
const avatarPreview = ref(defaultAvatar);
const avatarFile = ref(null);

const profileForm = reactive({
  name: '',
  email: '',
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

const fetchProfile = async () => {
  isLoadingProfile.value = true;
  profileErrors.value = {};

  try {
    const { data } = await axios.get('/user');
    profileForm.name = data?.user?.name ?? '';
    profileForm.email = data?.user?.email ?? '';

    if (data?.user?.avatar_url) {
      avatarPreview.value = data.user.avatar_url;
    } else if (!avatarFile.value) {
      avatarPreview.value = defaultAvatar;
    }
  } catch (error) {
    toaster.error('No se pudo cargar tu perfil.');
  } finally {
    isLoadingProfile.value = false;
  }
};

onMounted(fetchProfile);

const onAvatarChange = (event) => {
  const [file] = event.target.files || [];
  avatarFile.value = file || null;

  if (file) {
    avatarPreview.value = URL.createObjectURL(file);
  } else {
    avatarPreview.value = defaultAvatar;
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
    if (avatarFile.value) {
      formData.append('avatar', avatarFile.value);
    }

    const { data } = await axios.post('/user', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    toaster.success(data?.message ?? 'Perfil actualizado correctamente.');
  } catch (error) {
    if (error.response?.status === 422) {
      profileErrors.value = error.response.data?.errors || {};
      return;
    }

    toaster.error('No se pudo actualizar el perfil.');
  } finally {
    isSavingProfile.value = false;
  }
};

const submitPassword = async () => {
  isSavingPassword.value = true;
  passwordErrors.value = {};

  try {
    const { data } = await axios.post('/user', {
      _method: 'put',
      current_password: passwordForm.current_password,
      password: passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    });

    toaster.success(data?.message ?? 'Contraseña actualizada correctamente.');
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
  } catch (error) {
    if (error.response?.status === 422) {
      passwordErrors.value = error.response.data?.errors || {};
      return;
    }

    toaster.error('No se pudo actualizar la contraseña.');
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
