<template>
  <div class="content">

    <BaseBlock :title="$t('invoices.title')">
      <template #options>
        <div class="block-options-item">
          <router-link to="invoices/new" class="btn btn-primary">
            {{ $t("invoices.newTitle") }}
          </router-link>
        </div>
      </template>

      <BulkActionBar
        :count="selectedIds.length"
        :loading="bulkDeleting"
        @delete="showBulkDeleteModal = true"
        @clear="clearSelection"
      />

      <DataTableShell
        :rows="invoices"
        :server-side="true"
        :meta="meta"
        :status-options="statusOptions"
        search-placeholder="Search invoices…"
        @filter="onFilter"
        @page="onPage"
        @per-page="onPerPage"
      >
        <template #head="{ sortField, sortDir, setSort }">
          <tr>
            <th class="dt-cb-col">
              <input type="checkbox" class="form-check-input" @change="clickedAll">
            </th>
            <th>{{ $t("invoices.table.number") }}</th>
            <th>{{ $t("invoices.table.customer") }}</th>
            <th class="dt-sortable" @click="setSort('date')">
              {{ $t("invoices.table.date") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'date' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th>{{ $t("invoices.table.subtotal") }}</th>
            <th class="dt-sortable" @click="setSort('total')">
              {{ $t("invoices.table.total") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'total' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-sortable" @click="setSort('status')">
              {{ $t("invoices.table.status") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'status' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-ac-col">{{ $t("common.actions") }}</th>
          </tr>
        </template>

        <template #body="{ items }">
          <tr v-for="invoice in items" :key="invoice.uuid">
            <td class="dt-cb-col">
              <input type="checkbox" class="form-check-input" v-model="invoice.checked">
            </td>
            <td>
              <router-link :to="'invoices/edit/' + invoice.uuid">{{ invoice.reference }}</router-link>
            </td>
            <td>{{ invoice.customer }}</td>
            <td>{{ invoice.date }}</td>
            <td>{{ $toComma(invoice.sub_total) }}</td>
            <td>{{ $toComma(invoice.total) }}</td>
            <td>
              <span class="fs-xs fw-semibold d-inline-block py-1 px-3 rounded-pill"
                :class="statusBadgeClass(invoice.status)">
                {{ statusLabel(invoice.status) }}
              </span>
            </td>
            <td class="dt-ac-col">
              <RowActionMenu>
                <a v-if="invoice.status === 'draft'" class="dropdown-item" href="javascript:void(0)" @click.prevent="issueInvoice(invoice)">
                  <i class="fa fa-paper-plane fa-fw me-1"></i>{{ $t("invoices.actionIssue") }}
                </a>
                <a v-if="invoice.status === 'issued'" class="dropdown-item" href="javascript:void(0)" @click.prevent="markPaid(invoice)">
                  <i class="fa fa-check-circle fa-fw me-1"></i>{{ $t("invoices.actionMarkPaid") }}
                </a>
                <a
                  v-if="invoice.status !== 'cancelled'"
                  class="dropdown-item"
                  :class="{ 'text-muted': !invoice.customer_email }"
                  href="javascript:void(0)"
                  @click.prevent="invoice.customer_email ? sendInvoice(invoice) : null"
                  :title="!invoice.customer_email ? $t('invoices.noClientEmail') : ''"
                >
                  <i class="fa fa-envelope fa-fw me-1"></i>{{ $t("invoices.actionSend") }}
                  <small v-if="!invoice.customer_email" class="text-muted ms-1">(sin email)</small>
                </a>
                <a
                  v-if="invoice.status !== 'cancelled'"
                  class="dropdown-item"
                  :class="{ 'text-muted': !invoice.customer_phone }"
                  href="javascript:void(0)"
                  @click.prevent="invoice.customer_phone ? sendWhatsApp(invoice) : null"
                  :title="!invoice.customer_phone ? $t('invoices.noClientPhone') : ''"
                >
                  <i class="fab fa-whatsapp fa-fw me-1" style="color:#25D366"></i>{{ $t("invoices.actionWhatsApp") }}
                  <small v-if="!invoice.customer_phone" class="text-muted ms-1">(sin teléfono)</small>
                </a>
                <a class="dropdown-item" href="javascript:void(0)" @click.prevent="duplicateInvoice(invoice)">
                  <i class="fa fa-copy fa-fw me-1"></i>{{ $t("invoices.actionDuplicate") }}
                </a>
                <a v-if="invoice.status !== 'draft'" class="dropdown-item" href="javascript:void(0)" @click.prevent="printInvoice(invoice)">
                  <i class="fa fa-file-pdf fa-fw me-1"></i>{{ $t("invoices.document") }}
                </a>
                <div class="dropdown-divider"></div>
                <a v-if="invoice.status !== 'cancelled'" class="dropdown-item text-danger" href="javascript:void(0)" @click.prevent="cancelInvoice(invoice)">
                  <i class="fa fa-ban fa-fw me-1"></i>{{ $t("invoices.actionCancel") }}
                </a>
                <a class="dropdown-item text-danger" href="javascript:void(0)" @click.prevent="deleteInvoice(invoice)">
                  <i class="fa fa-trash fa-fw me-1"></i>{{ $t("common.delete") }}
                </a>
              </RowActionMenu>
            </td>
          </tr>
        </template>
      </DataTableShell>
    </BaseBlock>

  </div>

  <BulkDeleteModal
    :show="showBulkDeleteModal"
    :count="selectedIds.length"
    :loading="bulkDeleting"
    @confirm="bulkDelete"
    @cancel="showBulkDeleteModal = false"
  />

  <SendInvoiceModal
    v-if="showSendModal && sendModalInvoice"
    :customer-email="sendModalInvoice.customer_email"
    :customer-name="sendModalInvoice.customer ?? ''"
    :invoice-ref="sendModalInvoice.reference ?? ''"
    :company-name="companyName"
    :sending="modalSending"
    @close="showSendModal = false; sendModalInvoice = null"
    @send="handleModalSend"
  />
</template>


<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import axios from 'axios';
import DataTableShell  from '@/views/admin/layouts/DataTableShell.vue';
import BulkActionBar   from '@/views/admin/layouts/BulkActionBar.vue';
import BulkDeleteModal from '@/views/admin/layouts/BulkDeleteModal.vue';
import RowActionMenu   from '@/views/admin/layouts/RowActionMenu.vue';
import SendInvoiceModal from './SendInvoiceModal.vue';
import { createToaster } from '@meforma/vue-toaster';
import { useI18n } from "vue-i18n";

const toaster = createToaster();
const { t }   = useI18n();

const invoices    = ref([]);
const companyName = ref('');

const showBulkDeleteModal = ref(false);
const bulkDeleting        = ref(false);
const selectedIds         = computed(() => invoices.value.filter(i => i.checked).map(i => i.uuid));

const showSendModal    = ref(false);
const sendModalInvoice = ref(null);
const modalSending     = ref(false);

const meta = reactive({
  current_page: 1,
  last_page:    1,
  per_page:     10,
  total:        0,
});

const filterParams = reactive({
  search:   '',
  status:   '',
  date_from: '',
  date_to:   '',
  sort_by:  '',
  sort_dir: 'desc',
});

const statusOptions = computed(() => [
  { value: 'draft',     label: t('invoices.statusDraft') },
  { value: 'issued',    label: t('invoices.statusIssued') },
  { value: 'paid',      label: t('invoices.statusPaid') },
  { value: 'cancelled', label: t('invoices.statusCancelled') },
]);

async function fetchInvoices(page = meta.current_page, perPage = meta.per_page) {
  const { data } = await axios.get('/invoices', {
    params: {
      page,
      per_page: perPage,
      search:   filterParams.search   || undefined,
      status:   filterParams.status   || undefined,
      date_from: filterParams.date_from || undefined,
      date_to:   filterParams.date_to   || undefined,
      sort_by:  filterParams.sort_by  || undefined,
      sort_dir: filterParams.sort_dir || undefined,
    },
  });
  invoices.value    = data.invoices;
  companyName.value = data.company_name ?? '';
  meta.current_page = data.meta.current_page;
  meta.last_page    = data.meta.last_page;
  meta.per_page     = data.meta.per_page ?? perPage;
  meta.total        = data.meta.total;
}

onMounted(() => fetchInvoices());

function onFilter(params) {
  Object.assign(filterParams, params);
  meta.current_page = 1;
  fetchInvoices(1, meta.per_page);
}

function onPage(n) {
  meta.current_page = n;
  fetchInvoices(n, meta.per_page);
}

function onPerPage(n) {
  meta.per_page     = n;
  meta.current_page = 1;
  fetchInvoices(1, n);
}

// ── Status helpers ──────────────────────────────────────────

function statusBadgeClass(status) {
  const map = {
    draft:     'bg-secondary-light text-secondary',
    issued:    'bg-info-light text-info',
    paid:      'bg-success-light text-success',
    cancelled: 'bg-danger-light text-danger',
  };
  return map[status] ?? 'bg-secondary-light text-secondary';
}

function statusLabel(status) {
  const map = {
    draft:     t('invoices.statusDraft'),
    issued:    t('invoices.statusIssued'),
    paid:      t('invoices.statusPaid'),
    cancelled: t('invoices.statusCancelled'),
  };
  return map[status] ?? status;
}

// ── Table helpers ───────────────────────────────────────────

function clickedAll(e) {
  const checked = e.target.checked;
  invoices.value = invoices.value.map(inv => ({ ...inv, checked }));
}

function clearSelection() {
  invoices.value = invoices.value.map(inv => ({ ...inv, checked: false }));
}

async function bulkDelete() {
  const ids = selectedIds.value.slice();
  if (!ids.length) return;
  bulkDeleting.value = true;
  try {
    await axios.post('/invoices/bulk-delete', { ids });
    invoices.value = invoices.value.filter(i => !ids.includes(i.uuid));
    showBulkDeleteModal.value = false;
    toaster.success(t('common.bulkDeleteSuccess', { count: ids.length }));
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  } finally {
    bulkDeleting.value = false;
  }
}

// ── Actions ─────────────────────────────────────────────────

async function printInvoice(invoice) {
  axios.post('/invoices/print', invoice).then(res => {
    toaster.success(res.data.message);
    window.open(res.data.pdf_url, '_blank');
  });
}

async function issueInvoice(invoice) {
  if (!confirm(t('invoices.confirmIssue', { ref: invoice.reference }))) return;
  try {
    const res = await axios.post('/invoices/' + invoice.uuid + '/issue');
    toaster.success(res.data.message);
    invoice.status    = res.data.status;
    invoice.reference = res.data.reference;
    invoice.is_locked = true;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}

async function markPaid(invoice) {
  if (!confirm(t('invoices.confirmMarkPaid', { ref: invoice.reference }))) return;
  try {
    const res = await axios.post('/invoices/' + invoice.uuid + '/mark-paid');
    toaster.success(res.data.message);
    invoice.status = res.data.status;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}

async function cancelInvoice(invoice) {
  if (!confirm(t('invoices.confirmCancel', { ref: invoice.reference }))) return;
  try {
    const res = await axios.post('/invoices/' + invoice.uuid + '/cancel');
    toaster.success(res.data.message);
    invoice.status = res.data.status;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}

async function duplicateInvoice(invoice) {
  try {
    const res = await axios.post('/invoices/' + invoice.uuid + '/duplicate');
    toaster.success(res.data.message);
    fetchInvoices();
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}

async function sendWhatsApp(invoice) {
  if (!invoice.customer_phone) return;
  try {
    const res = await axios.post('/invoices/' + invoice.uuid + '/whatsapp');
    invoice.status = res.data.status;
    window.open(res.data.wa_link, '_blank', 'noopener,noreferrer');
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}

function sendInvoice(invoice) {
  if (!invoice.customer_email) return;
  sendModalInvoice.value = invoice;
  showSendModal.value    = true;
}

async function handleModalSend(message) {
  const invoice = sendModalInvoice.value;
  if (!invoice) return;
  modalSending.value = true;
  try {
    const res = await axios.post('/invoices/' + invoice.uuid + '/send', { custom_message: message });
    toaster.success(res.data.message);
    invoice.status         = res.data.status;
    showSendModal.value    = false;
    sendModalInvoice.value = null;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  } finally {
    modalSending.value = false;
  }
}

const deleteInvoice = (invoice) => {
  if (confirm(t('invoices.confirmDelete'))) {
    axios.delete('/invoices/' + invoice.uuid)
      .then(() => {
        invoices.value = invoices.value.filter(inv => inv.uuid !== invoice.uuid);
      })
      .catch(error => {
        console.error('Error deleting invoice:', error);
      });
  }
};
</script>
