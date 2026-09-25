<template>
  <div class="content">
    <BaseBlock :title="$t('team.title')" :subtitle="$t('team.subtitle')">
      <template #options>
        <div class="usr-usage">
          {{ usageLabel }}
        </div>
        <div class="block-options-item">
          <button
            v-if="canManage && !limitReached"
            type="button"
            class="btn btn-primary"
            @click="openAddModal"
          >
            <i class="fa fa-plus fa-fw me-1"></i>{{ $t('team.addBtn') }}
          </button>
        </div>
      </template>

      <!-- ── Not-admin notice ── -->
      <div v-if="!canManage" class="usr-banner usr-banner--info">
        <i class="fa fa-circle-info usr-banner__icon"></i>
        <span>{{ $t('team.notAdminNotice') }}</span>
      </div>

      <!-- ── Plan-limit banner ── -->
      <div v-else-if="limitReached" class="usr-banner usr-banner--warning">
        <i class="fa fa-triangle-exclamation usr-banner__icon"></i>
        <div class="usr-banner__body">
          <span>{{ $t('team.limitReachedMsg') }}</span>
        </div>
        <router-link :to="{ name: 'backend-subscription' }" class="btn btn-sm btn-primary">
          {{ $t('team.upgradeCta') }}
        </router-link>
      </div>

      <div class="table-responsive">
        <table class="table table-vcenter">
          <thead>
            <tr>
              <th>{{ $t('team.table.name') }}</th>
              <th>{{ $t('team.table.email') }}</th>
              <th>{{ $t('team.table.role') }}</th>
              <th v-if="canManage" class="dt-ac-col">{{ $t('team.table.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>
                {{ user.name }}
                <span v-if="user.is_owner" class="badge bg-primary-light text-primary ms-1">{{ $t('team.badges.owner') }}</span>
                <span v-else-if="user.is_self" class="badge bg-secondary-light text-secondary ms-1">{{ $t('team.badges.you') }}</span>
              </td>
              <td>{{ user.email }}</td>
              <td>{{ $t('team.roles.' + user.role) }}</td>
              <td v-if="canManage" class="dt-ac-col">
                <RowActionMenu>
                  <a class="dropdown-item" href="javascript:void(0)" @click.prevent="openEditModal(user)">
                    <i class="fa fa-pencil fa-fw me-1"></i>{{ $t('common.edit') }}
                  </a>
                  <div class="dropdown-divider"></div>
                  <a
                    v-if="!user.is_owner && !user.is_self"
                    class="dropdown-item text-danger"
                    href="javascript:void(0)"
                    @click.prevent="deleteUser(user)"
                  >
                    <i class="fa fa-trash fa-fw me-1"></i>{{ $t('team.deleteBtn') }}
                  </a>
                </RowActionMenu>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>

    <!-- ── Add / Edit modal ── -->
    <Teleport to="body">
      <div v-if="showModal" class="usr-overlay" @click.self="closeModal">
        <div class="usr-modal" role="dialog" aria-modal="true">
          <div class="usr-modal__header">
            <h3 class="usr-modal__title">{{ editing ? $t('team.modal.editTitle') : $t('team.modal.addTitle') }}</h3>
            <button class="usr-modal__close" type="button" @click="closeModal" :disabled="saving">&times;</button>
          </div>
          <form @submit.prevent="submitForm">
            <div class="usr-modal__body">
              <div class="mb-3">
                <label class="form-label">{{ $t('team.modal.name') }}</label>
                <input v-model="form.name" type="text" class="form-control" required maxlength="150">
              </div>
              <div class="mb-3">
                <label class="form-label">{{ $t('team.modal.email') }}</label>
                <input v-model="form.email" type="email" class="form-control" required maxlength="255">
              </div>
              <div class="mb-3">
                <label class="form-label">{{ $t('team.modal.password') }}</label>
                <input v-model="form.password" type="password" class="form-control" autocomplete="new-password">
                <small class="form-text text-muted">
                  {{ editing ? $t('team.modal.passwordEditHint') : $t('team.modal.passwordHint') }}
                </small>
              </div>
              <div class="mb-3">
                <label class="form-label">{{ $t('team.modal.role') }}</label>
                <select v-model="form.role" class="form-select" :disabled="editing && editingUser?.is_owner">
                  <option value="member">{{ $t('team.roles.member') }}</option>
                  <option value="admin">{{ $t('team.roles.admin') }}</option>
                </select>
              </div>
              <p v-if="formError" class="text-danger small mb-0">{{ formError }}</p>
            </div>
            <div class="usr-modal__footer">
              <button type="button" class="btn btn-secondary" @click="closeModal" :disabled="saving">
                {{ $t('team.modal.cancel') }}
              </button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                <i v-if="saving" class="fa fa-spinner fa-spin me-1"></i>
                {{ saving ? $t('team.modal.saving') : $t('team.modal.save') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { createToaster } from '@meforma/vue-toaster';
import BaseBlock from '@/components/BaseBlock.vue';
import RowActionMenu from '@/views/admin/layouts/RowActionMenu.vue';

const { t } = useI18n();
const toaster = createToaster();

const users      = ref([]);
const usage      = ref({ used: 0, limit: null, remaining: null });
const canManage  = ref(false);
const loading    = ref(false);

const limitReached = computed(() => usage.value.limit !== null && usage.value.remaining === 0);

const usageLabel = computed(() => {
  return usage.value.limit === null
    ? t('team.usageLabelUnlimited', { used: usage.value.used })
    : t('team.usageLabel', { used: usage.value.used, limit: usage.value.limit });
});

function loadUsers() {
  loading.value = true;
  axios.get('/users')
    .then(({ data }) => {
      users.value = data.users;
      usage.value = data.usage;
      canManage.value = data.can_manage;
    })
    .catch(() => toaster.error(t('team.genericError')))
    .finally(() => { loading.value = false; });
}

onMounted(loadUsers);

// ── Add / edit modal ─────────────────────────────────────────────

const showModal   = ref(false);
const editing     = ref(false);
const editingUser = ref(null);
const saving      = ref(false);
const formError   = ref('');

const form = reactive({ name: '', email: '', password: '', role: 'member' });

function resetForm() {
  form.name = '';
  form.email = '';
  form.password = '';
  form.role = 'member';
  formError.value = '';
}

function openAddModal() {
  resetForm();
  editing.value = false;
  editingUser.value = null;
  showModal.value = true;
}

function openEditModal(user) {
  resetForm();
  editing.value = true;
  editingUser.value = user;
  form.name = user.name;
  form.email = user.email;
  form.role = user.role;
  showModal.value = true;
}

function closeModal() {
  if (saving.value) return;
  showModal.value = false;
}

function submitForm() {
  saving.value = true;
  formError.value = '';

  const payload = { name: form.name, email: form.email, role: form.role };
  if (form.password) payload.password = form.password;

  const request = editing.value
    ? axios.put('/users/' + editingUser.value.id, payload)
    : axios.post('/users', payload);

  request
    .then(() => {
      toaster.success(editing.value ? t('team.updateSuccess') : t('team.createSuccess'));
      showModal.value = false;
      loadUsers();
    })
    .catch(err => {
      if (err.response?.status === 402 && err.response.data?.error === 'plan_limit_reached') {
        showModal.value = false;
        loadUsers();
        toaster.error(t('team.limitReachedMsg'));
      } else if (err.response?.status === 422) {
        const errors = err.response.data?.errors;
        formError.value = errors ? Object.values(errors).flat().join(' ') : t('team.genericError');
      } else {
        formError.value = err.response?.data?.message ?? t('team.genericError');
      }
    })
    .finally(() => { saving.value = false; });
}

// ── Delete ───────────────────────────────────────────────────────

function deleteUser(user) {
  if (!confirm(t('team.deleteConfirm', { name: user.name }))) return;

  axios.delete('/users/' + user.id)
    .then(() => {
      toaster.success(t('team.deleteSuccess'));
      loadUsers();
    })
    .catch(err => {
      toaster.error(err.response?.data?.message ?? t('team.genericError'));
    });
}
</script>

<style scoped>
.usr-usage {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--text-muted, #6b7280);
  margin-right: 14px;
  white-space: nowrap;
}
:global(.rtl-support) .usr-usage {
  margin-right: 0;
  margin-left: 14px;
}

.usr-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  border-radius: 8px;
  padding: 14px 18px;
  margin-bottom: 20px;
}
.usr-banner--info {
  background: #eff6ff;
  border: 1.5px solid #bfdbfe;
  color: #1e40af;
}
.usr-banner--warning {
  background: #fff7ed;
  border: 1.5px solid #fed7aa;
  color: #9a3412;
}
.usr-banner__icon { font-size: 1.1rem; flex-shrink: 0; }
.usr-banner__body { flex: 1; min-width: 0; font-size: 0.85rem; }

.usr-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 1060;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.usr-modal {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.18);
  width: 100%;
  max-width: 460px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.usr-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f0f0f0;
}
.usr-modal__title { margin: 0; font-size: 16px; font-weight: 700; color: #1a1a1a; }
.usr-modal__close {
  background: none;
  border: none;
  font-size: 22px;
  color: #9ca3af;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
}
.usr-modal__close:hover:not(:disabled) { color: #374151; }
.usr-modal__close:disabled { opacity: 0.4; cursor: not-allowed; }
.usr-modal__body { padding: 20px 24px; }
.usr-modal__footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid #f0f0f0;
}

/* Dark mode - the app's real toggle (.dark-mode class), not
   prefers-color-scheme/data-theme which don't reflect it. */
:global(.dark-mode) .usr-modal { background: var(--dark-surface-elevated); }
:global(.dark-mode) .usr-modal__header, :global(.dark-mode) .usr-modal__footer { border-color: var(--dark-border); }
:global(.dark-mode) .usr-modal__title { color: var(--dark-text); }
</style>
