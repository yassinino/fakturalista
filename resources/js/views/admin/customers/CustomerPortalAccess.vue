<template>
  <BaseBlock class="customer-form-card" :title="$t('customers.portal.title')" content-full>
    <div class="row">
      <div class="col-lg-3">
        <p class="fs-sm text-muted">{{ $t('customers.portal.hint') }}</p>
      </div>

      <div class="col-lg-9">
        <div v-if="loading" class="text-muted fs-sm py-2">
          <i class="fa fa-spinner fa-spin me-1"></i>
        </div>

        <!-- No active access -->
        <div v-else-if="!status.active" class="d-flex flex-wrap align-items-center gap-2">
          <span class="fs-sm text-muted me-2">{{ $t('customers.portal.none') }}</span>
          <button type="button" class="btn btn-sm btn-primary" :disabled="busy" data-test="portal-create" @click="create">
            <i class="fa fa-link me-1"></i>{{ $t('customers.portal.create') }}
          </button>
        </div>

        <!-- Active access -->
        <template v-else>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2 fs-sm">
            <span class="badge bg-success-light text-success">
              <i class="fa fa-circle fa-xs me-1"></i>{{ $t('customers.portal.active') }}
            </span>
            <span v-if="status.created_at" class="text-muted">{{ $t('customers.portal.createdOn', { date: formatDateTime(status.created_at) }) }}</span>
            <span class="text-muted">
              · {{ status.last_accessed_at ? $t('customers.portal.lastOpened', { date: formatDateTime(status.last_accessed_at) }) : $t('customers.portal.neverOpened') }}
            </span>
          </div>

          <!-- The raw link only exists right after create/regenerate (never stored). -->
          <template v-if="url">
            <label class="form-label fs-sm mb-1" for="portal-link">{{ $t('customers.portal.linkLabel') }}</label>
            <div class="input-group input-group-sm mb-1">
              <input id="portal-link" ref="linkInput" type="text" class="form-control" :value="url" readonly @focus="$event.target.select()" />
              <button type="button" class="btn btn-alt-primary" data-test="portal-copy" @click="copy">
                <i class="fa fa-copy me-1"></i>{{ copied ? $t('customers.portal.copied') : $t('customers.portal.copy') }}
              </button>
              <a :href="url" target="_blank" rel="noopener noreferrer" class="btn btn-alt-secondary" data-test="portal-open">
                <i class="fa fa-external-link-alt me-1"></i>{{ $t('customers.portal.open') }}
              </a>
            </div>
            <p class="fs-xs text-warning mb-2"><i class="fa fa-info-circle me-1"></i>{{ $t('customers.portal.shownOnce') }}</p>
          </template>
          <p v-else class="fs-xs text-muted mb-2">{{ $t('customers.portal.hiddenLink') }}</p>

          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-alt-secondary" :disabled="busy" data-test="portal-regenerate" @click="regenerate">
              <i class="fa fa-sync-alt me-1"></i>{{ $t('customers.portal.regenerate') }}
            </button>
            <button type="button" class="btn btn-sm btn-alt-danger" :disabled="busy" data-test="portal-revoke" @click="revoke">
              <i class="fa fa-ban me-1"></i>{{ $t('customers.portal.revoke') }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </BaseBlock>
</template>

<script setup>
/**
 * Client Portal link for one customer (customer edit page). Talks to
 * /customers/{uuid}/portal-access (CustomerPortalAccessController), which
 * wraps the existing ClientPortalService. The link is only ever known
 * right after create/regenerate - it is kept in memory for this view and
 * never persisted anywhere (the server only stores a hash).
 */
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { createToaster } from '@meforma/vue-toaster';

const props = defineProps({
  customerUuid: { type: String, required: true },
});

const { t, locale } = useI18n();
const toaster = createToaster();

const loading = ref(true);
const busy = ref(false);
const status = ref({ active: false, created_at: null, last_accessed_at: null });
const url = ref(null);
const copied = ref(false);
const linkInput = ref(null);

const endpoint = () => '/customers/' + encodeURIComponent(props.customerUuid) + '/portal-access';

function apply(data) {
  status.value = { active: !!data.active, created_at: data.created_at, last_accessed_at: data.last_accessed_at };
  if (data.url) url.value = data.url;
  if (!data.active) url.value = null;
  copied.value = false;
}

async function run(request) {
  if (busy.value) return;
  busy.value = true;
  try {
    const res = await request();
    apply(res.data);
    if (res.data.message) toaster.success(res.data.message);
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('customers.portal.error'));
  } finally {
    busy.value = false;
  }
}

const create = () => run(() => axios.post(endpoint()));

function regenerate() {
  if (!confirm(t('customers.portal.confirmRegenerate'))) return;
  url.value = null;
  run(() => axios.post(endpoint() + '/regenerate'));
}

function revoke() {
  if (!confirm(t('customers.portal.confirmRevoke'))) return;
  run(() => axios.delete(endpoint()));
}

async function copy() {
  try {
    await navigator.clipboard.writeText(url.value);
    copied.value = true;
    toaster.success(t('customers.portal.copied'));
  } catch (e) {
    linkInput.value?.select();
    toaster.error(t('customers.portal.copyFailed'));
  }
}

function formatDateTime(iso) {
  const d = new Date(iso);
  return isNaN(d) ? '' : d.toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' });
}

onMounted(async () => {
  try {
    const res = await axios.get(endpoint());
    apply(res.data);
  } catch (e) {
    toaster.error(t('customers.portal.error'));
  } finally {
    loading.value = false;
  }
});
</script>
