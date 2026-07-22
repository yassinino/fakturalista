<template>
  <div class="content">

    <!-- ── Header ──────────────────────────────────────────────── -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
      <div>
        <h2 class="fw-bold mb-1">{{ $t('payments.title') }}</h2>
        <p class="text-muted mb-0 fs-sm">{{ $t('payments.subtitle') }}</p>
      </div>
      <button class="btn btn-primary" @click="openRecordModal">
        <i class="fa fa-plus me-1"></i>{{ $t('payments.recordPayment') }}
      </button>
    </div>

    <!-- ── Summary Cards ────────────────────────────────────────── -->
    <div class="pay-kpi-grid">

      <!-- Total Received -->
      <div class="pay-kpi">
        <div class="pay-kpi-icon-wrap pay-kpi-icon-wrap--pink">
          <i class="fa fa-chart-line"></i>
        </div>
        <div class="pay-kpi-body">
          <p class="pay-kpi-label">{{ $t('payments.totalReceived') }}</p>
          <p class="pay-kpi-value" :class="{ 'pay-kpi-sk': loadingSummary }">
            {{ loadingSummary ? '' : $toComma(summary.totalReceived) + ' €' }}
          </p>
          <p class="pay-kpi-meta pay-kpi-meta--neutral" v-if="!loadingSummary">All time</p>
          <p class="pay-kpi-meta" v-else>&nbsp;</p>
        </div>
      </div>

      <!-- Pending Payments -->
      <div class="pay-kpi">
        <div class="pay-kpi-icon-wrap pay-kpi-icon-wrap--blue">
          <i class="fa fa-clock"></i>
        </div>
        <div class="pay-kpi-body">
          <p class="pay-kpi-label">{{ $t('payments.pendingPayments') }}</p>
          <p class="pay-kpi-value" :class="{ 'pay-kpi-sk': loadingSummary }">
            {{ loadingSummary ? '' : $toComma(summary.pending) + ' €' }}
          </p>
          <p class="pay-kpi-meta pay-kpi-meta--blue" v-if="!loadingSummary && summary.pending > 0">En proceso</p>
          <p class="pay-kpi-meta pay-kpi-meta--green" v-else-if="!loadingSummary">
            <i class="fa fa-check fa-xs me-1"></i>Al día
          </p>
          <p class="pay-kpi-meta" v-else>&nbsp;</p>
        </div>
      </div>

      <!-- Overdue — only accented when > 0 -->
      <div class="pay-kpi" :class="{ 'pay-kpi--alert': !loadingSummary && summary.overdue > 0 }">
        <div class="pay-kpi-icon-wrap"
          :class="!loadingSummary && summary.overdue > 0 ? 'pay-kpi-icon-wrap--orange' : 'pay-kpi-icon-wrap--muted'">
          <i class="fa fa-exclamation-circle"></i>
        </div>
        <div class="pay-kpi-body">
          <p class="pay-kpi-label">{{ $t('payments.overdue') }}</p>
          <p class="pay-kpi-value"
            :class="[{ 'pay-kpi-sk': loadingSummary }, !loadingSummary && summary.overdue > 0 ? 'pay-kpi-value--warn' : '']">
            {{ loadingSummary ? '' : $toComma(summary.overdue) + ' €' }}
          </p>
          <p class="pay-kpi-meta pay-kpi-meta--orange" v-if="!loadingSummary && summary.overdue > 0">
            Requiere atención
          </p>
          <p class="pay-kpi-meta pay-kpi-meta--green" v-else-if="!loadingSummary">
            <i class="fa fa-check fa-xs me-1"></i>Todo en orden
          </p>
          <p class="pay-kpi-meta" v-else>&nbsp;</p>
        </div>
      </div>

      <!-- This Month -->
      <div class="pay-kpi">
        <div class="pay-kpi-icon-wrap pay-kpi-icon-wrap--green">
          <i class="fa fa-check-circle"></i>
        </div>
        <div class="pay-kpi-body">
          <p class="pay-kpi-label">{{ $t('payments.thisMonth') }}</p>
          <p class="pay-kpi-value" :class="{ 'pay-kpi-sk': loadingSummary }">
            {{ loadingSummary ? '' : $toComma(summary.thisMonth) + ' €' }}
          </p>
          <p class="pay-kpi-meta pay-kpi-meta--green" v-if="!loadingSummary && summary.thisMonth > 0">Este mes</p>
          <p class="pay-kpi-meta pay-kpi-meta--neutral" v-else-if="!loadingSummary">Sin cobros aún</p>
          <p class="pay-kpi-meta" v-else>&nbsp;</p>
        </div>
      </div>

    </div>

    <!-- ── Table ──────────────────────────────────────────────────── -->
    <BaseBlock>
      <template #options>
        <div class="d-flex flex-wrap gap-2 align-items-center py-1">

          <!-- Client filter (payment-specific — not in DataTableShell toolbar) -->
          <select v-model="filters.client_id" class="form-select form-select-sm" style="width:auto;" @change="fetchPayments(1)">
            <option value="">{{ $t('payments.allClients') }}</option>
            <option v-for="c in clients" :key="c.uuid" :value="c.uuid">{{ c.label }}</option>
          </select>

          <!-- Sort -->
          <select v-model="filters.sort" class="form-select form-select-sm" style="width:auto;" @change="fetchPayments(1)">
            <option value="newest">{{ $t('payments.sortNewest') }}</option>
            <option value="oldest">{{ $t('payments.sortOldest') }}</option>
            <option value="amount">{{ $t('payments.sortAmount') }}</option>
            <option value="status">{{ $t('payments.sortStatus') }}</option>
          </select>

        </div>
      </template>

      <DataTableShell
        :rows="payments"
        :server-side="true"
        :meta="meta"
        :status-options="statusOptions"
        search-placeholder="Search payments…"
        @filter="onDtFilter"
        @page="onPage"
        @per-page="onPerPage"
      >
        <template #head>
          <tr>
            <th>{{ $t('payments.tablePaymentId') }}</th>
            <th>{{ $t('payments.tableClient') }}</th>
            <th>{{ $t('payments.tableInvoice') }}</th>
            <th class="text-end">{{ $t('payments.tableAmount') }}</th>
            <th>{{ $t('payments.tableMethod') }}</th>
            <th>{{ $t('payments.tableDate') }}</th>
            <th>{{ $t('payments.tableStatus') }}</th>
            <th class="dt-ac-col">{{ $t('common.actions') }}</th>
          </tr>
        </template>

        <template #body="{ items }">
          <tr v-for="p in items" :key="p.payment_number">

            <td>
              <span class="fw-semibold fs-sm text-muted">{{ p.payment_number }}</span>
            </td>

            <td>
              <div class="fw-semibold">{{ p.client_name }}</div>
              <div class="fs-xs text-muted" v-if="p.client_email">{{ p.client_email }}</div>
            </td>

            <td>
              <router-link
                :to="'/admin/invoices/edit/' + p.invoice_uuid"
                class="fw-semibold text-primary text-decoration-none"
              >
                {{ p.invoice_reference }}
              </router-link>
            </td>

            <td class="text-end fw-bold">{{ $toComma(p.amount) }} €</td>

            <td>
              <span class="fs-sm">{{ methodLabel(p.payment_method) }}</span>
            </td>

            <td>
              <span class="fs-sm text-muted">
                {{ p.payment_date || p.invoice_date || '—' }}
              </span>
            </td>

            <td>
              <span
                class="fs-xs fw-semibold d-inline-block py-1 px-3 rounded-pill"
                :class="statusBadgeClass(p.status)"
              >
                {{ statusLabel(p.status) }}
              </span>
            </td>

            <td class="dt-ac-col">
              <div class="dropdown dropstart">
                <button type="button" class="dt-action-btn dropdown-toggle"
                  data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fa fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu fs-sm">
                  <a class="dropdown-item" href="javascript:void(0)" @click.prevent="viewPayment(p)">
                    <i class="fa fa-eye fa-fw me-1"></i>{{ $t('payments.actionViewPayment') }}
                  </a>
                  <router-link class="dropdown-item" :to="'/admin/invoices/edit/' + p.invoice_uuid">
                    <i class="fa fa-file-invoice fa-fw me-1"></i>{{ $t('payments.actionViewInvoice') }}
                  </router-link>
                  <a class="dropdown-item" href="javascript:void(0)" @click.prevent="downloadReceipt(p)">
                    <i class="fa fa-file-pdf fa-fw me-1"></i>{{ $t('payments.actionDownloadReceipt') }}
                    <span v-if="processingMap[p.invoice_uuid] === 'pdf'" class="ms-1">
                      <i class="fa fa-spinner fa-spin fa-fw"></i>
                    </span>
                  </a>
                  <a
                    v-if="p.status === 'paid'"
                    class="dropdown-item"
                    href="javascript:void(0)"
                    @click.prevent="openEdit(p)"
                  >
                    <i class="fa fa-pen fa-fw me-1"></i>{{ $t('payments.actionEdit') }}
                  </a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="javascript:void(0)" @click.prevent="deletePayment(p)">
                    <i class="fa fa-trash fa-fw me-1"></i>{{ $t('payments.actionDelete') }}
                    <span v-if="processingMap[p.invoice_uuid] === 'delete'" class="ms-1">
                      <i class="fa fa-spinner fa-spin fa-fw"></i>
                    </span>
                  </a>
                </div>
              </div>
            </td>

          </tr>
        </template>
      </DataTableShell>
    </BaseBlock>

    <!-- ── Payment Detail Side Panel ────────────────────────────── -->
    <Transition name="fade">
      <div v-if="showDetail" class="pay-overlay" @click.self="showDetail = false">
        <Transition name="slide-right" appear>
          <div v-if="showDetail" class="pay-panel">

            <div class="pay-panel-header">
              <h5 class="fw-bold mb-0">{{ $t('payments.detailTitle') }}</h5>
              <button class="btn btn-sm btn-link p-0 text-muted" @click="showDetail = false">
                <i class="fa fa-times fa-lg"></i>
              </button>
            </div>

            <div v-if="selectedPayment" class="pay-panel-body">

              <!-- Status badge -->
              <div class="text-center mb-4">
                <span
                  class="fs-sm fw-semibold d-inline-block py-1 px-4 rounded-pill"
                  :class="statusBadgeClass(selectedPayment.status)"
                >
                  {{ statusLabel(selectedPayment.status) }}
                </span>
                <div class="fw-bold fs-3 mt-2">{{ $toComma(selectedPayment.amount) }} €</div>
                <div class="text-muted fs-sm">{{ selectedPayment.payment_number }}</div>
              </div>

              <hr class="my-3">

              <dl class="pay-dl">
                <dt>{{ $t('payments.detailClient') }}</dt>
                <dd>
                  <div class="fw-semibold">{{ selectedPayment.client_name }}</div>
                  <div class="text-muted fs-sm">{{ selectedPayment.client_email }}</div>
                </dd>

                <dt>{{ $t('payments.detailInvoice') }}</dt>
                <dd>
                  <router-link
                    :to="'/admin/invoices/edit/' + selectedPayment.invoice_uuid"
                    class="fw-semibold"
                    @click="showDetail = false"
                  >
                    {{ selectedPayment.invoice_reference }}
                  </router-link>
                </dd>

                <dt>{{ $t('payments.detailDate') }}</dt>
                <dd>{{ selectedPayment.payment_date || '—' }}</dd>

                <dt>{{ $t('payments.detailDue') }}</dt>
                <dd>{{ selectedPayment.due_date || '—' }}</dd>

                <dt>{{ $t('payments.detailMethod') }}</dt>
                <dd>{{ methodLabel(selectedPayment.payment_method) }}</dd>

                <dt v-if="selectedPayment.transaction_reference">{{ $t('payments.detailTransaction') }}</dt>
                <dd v-if="selectedPayment.transaction_reference" class="fs-sm text-break">
                  {{ selectedPayment.transaction_reference }}
                </dd>

                <dt v-if="selectedPayment.notes">{{ $t('payments.detailNotes') }}</dt>
                <dd v-if="selectedPayment.notes" class="fs-sm" style="white-space:pre-line;">{{ selectedPayment.notes }}</dd>
              </dl>

              <div class="mt-4 d-flex gap-2">
                <router-link
                  class="btn btn-outline-primary btn-sm flex-fill"
                  :to="'/admin/invoices/edit/' + selectedPayment.invoice_uuid"
                  @click="showDetail = false"
                >
                  <i class="fa fa-file-invoice me-1"></i>{{ $t('payments.actionViewInvoice') }}
                </router-link>
                <button
                  v-if="selectedPayment.status === 'paid'"
                  class="btn btn-outline-secondary btn-sm flex-fill"
                  @click="openEdit(selectedPayment); showDetail = false"
                >
                  <i class="fa fa-pen me-1"></i>{{ $t('payments.actionEdit') }}
                </button>
              </div>
            </div>

          </div>
        </Transition>
      </div>
    </Transition>

    <!-- ── Record Payment Modal ──────────────────────────────────── -->
    <Transition name="fade">
      <div v-if="showRecord" class="pay-modal-overlay" @click.self="showRecord = false">
        <div class="pay-modal-dialog" role="document">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title fw-bold">{{ $t('payments.recordTitle') }}</h5>
              <button type="button" class="btn-close" @click="showRecord = false"></button>
            </div>

            <div class="modal-body">

              <div class="mb-3">
                <label class="form-label fw-semibold">{{ $t('payments.recordInvoice') }} <span class="text-danger">*</span></label>
                <select v-model="recordForm.invoice_uuid" class="form-select" @change="onInvoiceSelected">
                  <option value="">{{ $t('payments.recordSelectInvoice') }}</option>
                  <option v-for="inv in payableInvoices" :key="inv.uuid" :value="inv.uuid">
                    {{ inv.reference }} — {{ inv.customer }} — {{ $toComma(inv.total) }} €
                    <template v-if="inv.overdue"> ⚠️</template>
                  </option>
                </select>
                <div v-if="!payableInvoices.length && !loadingPayable" class="form-text text-warning">
                  {{ $t('payments.recordNoInvoices') }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">{{ $t('payments.recordAmount') }}</label>
                <div class="input-group">
                  <span class="input-group-text">€</span>
                  <input :value="recordAmountDisplay" type="text" class="form-control" readonly tabindex="-1" />
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">{{ $t('payments.recordMethod') }}</label>
                <select v-model="recordForm.payment_method" class="form-select">
                  <option value="">— {{ $t('payments.methodNone') }} —</option>
                  <option value="cash">{{ $t('payments.methodCash') }}</option>
                  <option value="bank_transfer">{{ $t('payments.methodBankTransfer') }}</option>
                  <option value="card">{{ $t('payments.methodCard') }}</option>
                  <option value="stripe">Stripe</option>
                  <option value="check">{{ $t('payments.methodCheck') }}</option>
                  <option value="other">{{ $t('payments.methodOther') }}</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">{{ $t('payments.recordDate') }}</label>
                <input v-model="recordForm.payment_date" type="date" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">{{ $t('payments.recordNotes') }}</label>
                <textarea v-model="recordForm.notes" class="form-control" rows="3" :placeholder="$t('payments.recordNotesPlaceholder')"></textarea>
              </div>

            </div>

            <div class="modal-footer">
              <button class="pay-modal-btn pay-modal-btn-cancel" @click="showRecord = false">{{ $t('common.cancel') }}</button>
              <button class="pay-modal-btn pay-modal-btn-save" :disabled="!recordForm.invoice_uuid || submitting" @click="submitRecord">
                <i v-if="submitting" class="fa fa-spinner fa-spin"></i>
                {{ $t('payments.recordSubmit') }}
              </button>
            </div>

          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Edit Payment Modal ────────────────────────────────────── -->
    <Transition name="fade">
      <div v-if="showEdit" class="pay-modal-overlay" @click.self="showEdit = false">
        <div class="pay-modal-dialog" role="document">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title fw-bold">{{ $t('payments.editTitle') }}</h5>
              <button type="button" class="btn-close" @click="showEdit = false"></button>
            </div>

            <div class="modal-body">

              <div class="mb-3">
                <label class="form-label fw-semibold">{{ $t('payments.recordMethod') }}</label>
                <select v-model="editForm.payment_method" class="form-select">
                  <option value="">— {{ $t('payments.methodNone') }} —</option>
                  <option value="cash">{{ $t('payments.methodCash') }}</option>
                  <option value="bank_transfer">{{ $t('payments.methodBankTransfer') }}</option>
                  <option value="card">{{ $t('payments.methodCard') }}</option>
                  <option value="stripe">Stripe</option>
                  <option value="check">{{ $t('payments.methodCheck') }}</option>
                  <option value="other">{{ $t('payments.methodOther') }}</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">{{ $t('payments.recordDate') }}</label>
                <input v-model="editForm.payment_date" type="date" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">{{ $t('payments.recordNotes') }}</label>
                <textarea v-model="editForm.notes" class="form-control" rows="3" :placeholder="$t('payments.recordNotesPlaceholder')"></textarea>
              </div>

            </div>

            <div class="modal-footer">
              <button class="pay-modal-btn pay-modal-btn-cancel" @click="showEdit = false">{{ $t('common.cancel') }}</button>
              <button class="pay-modal-btn pay-modal-btn-save" :disabled="submitting" @click="submitEdit">
                <i v-if="submitting" class="fa fa-spinner fa-spin"></i>
                {{ $t('payments.editSubmit') }}
              </button>
            </div>

          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { createToaster } from '@meforma/vue-toaster';
import { useI18n } from 'vue-i18n';
import DataTableShell from '@/views/admin/layouts/DataTableShell.vue';

const toaster = createToaster();
const { t }   = useI18n();

// ── State ─────────────────────────────────────────────────────────

const payments        = ref([]);
const clients         = ref([]);
const payableInvoices = ref([]);

const summary = reactive({
  totalReceived: 0,
  pending:       0,
  overdue:       0,
  thisMonth:     0,
});

const meta = reactive({
  current_page: 1,
  last_page:    1,
  per_page:     10,
  total:        0,
});

const filters = reactive({
  search:    '',
  status:    '',
  client_id: '',
  date_from: '',
  date_to:   '',
  sort:      'newest',
});

const loadingSummary = ref(false);
const loadingPayable = ref(false);
const submitting     = ref(false);

const showDetail = ref(false);
const showRecord = ref(false);
const showEdit   = ref(false);

const selectedPayment = ref(null);
const editingPayment  = ref(null);

const processingMap = reactive({});

const recordForm = reactive({
  invoice_uuid:   '',
  payment_method: '',
  payment_date:   today(),
  notes:          '',
});

const editForm = reactive({
  payment_method: '',
  payment_date:   '',
  notes:          '',
});

// ── Computed ──────────────────────────────────────────────────────

const recordAmountDisplay = computed(() => {
  const inv = payableInvoices.value.find(i => i.uuid === recordForm.invoice_uuid);
  return inv ? inv.total.toFixed(2).replace('.', ',') : '—';
});

const statusOptions = computed(() => [
  { value: 'paid',      label: t('payments.statusPaid') },
  { value: 'pending',   label: t('payments.statusPending') },
  { value: 'overdue',   label: t('payments.statusOverdue') },
  { value: 'cancelled', label: t('payments.statusCancelled') },
]);

// ── Lifecycle ─────────────────────────────────────────────────────

onMounted(() => {
  fetchSummary();
  fetchPayments(1);
  fetchClients();
});

// ── Data fetchers ─────────────────────────────────────────────────

async function fetchSummary() {
  loadingSummary.value = true;
  try {
    const { data } = await axios.get('/payments/summary');
    summary.totalReceived = data.totalReceived;
    summary.pending       = data.pending;
    summary.overdue       = data.overdue;
    summary.thisMonth     = data.thisMonth;
  } catch (e) {
    console.error('[Payments:fetchSummary]', e);
  } finally {
    loadingSummary.value = false;
  }
}

async function fetchPayments(page = meta.current_page) {
  try {
    const { data } = await axios.get('/payments', {
      params: {
        page,
        per_page:  meta.per_page,
        search:    filters.search    || undefined,
        status:    filters.status    || undefined,
        client_id: filters.client_id || undefined,
        date_from: filters.date_from || undefined,
        date_to:   filters.date_to   || undefined,
        sort:      filters.sort,
      },
    });
    payments.value    = data.payments;
    meta.current_page = data.meta.current_page;
    meta.last_page    = data.meta.last_page;
    meta.per_page     = data.meta.per_page;
    meta.total        = data.meta.total;
  } catch (e) {
    console.error('[Payments:fetchPayments]', e);
    toaster.error(t('payments.errorGeneric'));
  }
}

async function fetchClients() {
  try {
    const { data } = await axios.get('/payments/clients');
    clients.value = data.clients;
  } catch (e) {
    console.error('[Payments:fetchClients]', e);
  }
}

async function fetchPayable() {
  loadingPayable.value = true;
  try {
    const { data } = await axios.get('/payments/payable');
    payableInvoices.value = data.invoices;
  } catch (e) {
    console.error('[Payments:fetchPayable]', e);
  } finally {
    loadingPayable.value = false;
  }
}

// ── DataTableShell event handlers ─────────────────────────────────

function onDtFilter(params) {
  filters.search   = params.search    ?? '';
  filters.status   = params.status    ?? '';
  filters.date_from = params.date_from ?? '';
  filters.date_to   = params.date_to   ?? '';
  // filters.client_id and filters.sort are controlled by the external selects
  fetchPayments(1);
}

function onPage(n) {
  meta.current_page = n;
  fetchPayments(n);
}

function onPerPage(n) {
  meta.per_page     = n;
  meta.current_page = 1;
  fetchPayments(1);
}

// ── Detail panel ──────────────────────────────────────────────────

function viewPayment(p) {
  selectedPayment.value = p;
  showDetail.value      = true;
}

// ── Record modal ──────────────────────────────────────────────────

async function openRecordModal() {
  recordForm.invoice_uuid   = '';
  recordForm.payment_method = '';
  recordForm.payment_date   = today();
  recordForm.notes          = '';
  await fetchPayable();
  showRecord.value = true;
}

function onInvoiceSelected() { /* amount auto-updates via computed */ }

async function submitRecord() {
  if (!recordForm.invoice_uuid) return;
  submitting.value = true;
  try {
    const { data } = await axios.post('/payments/record', {
      invoice_uuid:   recordForm.invoice_uuid,
      payment_method: recordForm.payment_method || null,
      payment_date:   recordForm.payment_date   || null,
      notes:          recordForm.notes          || null,
    });
    toaster.success(data.message || t('payments.recorded'));
    showRecord.value = false;
    fetchPayments(1);
    fetchSummary();
    fetchClients();
  } catch (e) {
    console.error('[Payments:submitRecord]', { error: e });
    toaster.error(e.response?.data?.message ?? t('payments.errorGeneric'));
  } finally {
    submitting.value = false;
  }
}

// ── Edit modal ────────────────────────────────────────────────────

function openEdit(p) {
  editingPayment.value      = p;
  editForm.payment_method   = p.payment_method || '';
  editForm.payment_date     = p.payment_date   || '';
  editForm.notes            = p.notes          || '';
  showEdit.value            = true;
}

async function submitEdit() {
  if (!editingPayment.value) return;
  submitting.value = true;
  try {
    const { data } = await axios.put('/payments/' + editingPayment.value.invoice_uuid, {
      payment_method: editForm.payment_method || null,
      payment_date:   editForm.payment_date   || null,
      notes:          editForm.notes          || null,
    });
    toaster.success(data.message || t('payments.updated'));

    const idx = payments.value.findIndex(p => p.invoice_uuid === editingPayment.value.invoice_uuid);
    if (idx !== -1) payments.value[idx] = data.payment;

    if (selectedPayment.value?.invoice_uuid === editingPayment.value.invoice_uuid) {
      selectedPayment.value = data.payment;
    }

    showEdit.value = false;
  } catch (e) {
    console.error('[Payments:submitEdit]', { invoiceUuid: editingPayment.value.invoice_uuid, error: e });
    toaster.error(e.response?.data?.message ?? t('payments.errorGeneric'));
  } finally {
    submitting.value = false;
  }
}

// ── Download receipt ──────────────────────────────────────────────

async function downloadReceipt(p) {
  if (processingMap[p.invoice_uuid] === 'pdf') return;
  processingMap[p.invoice_uuid] = 'pdf';
  try {
    const res = await axios.post('/invoices/print', { uuid: p.invoice_uuid });
    window.open(res.data.pdf_url, '_blank');
  } catch (e) {
    console.error('[Payments:downloadReceipt]', { invoiceUuid: p.invoice_uuid, error: e });
    toaster.error(t('payments.errorGeneric'));
  } finally {
    delete processingMap[p.invoice_uuid];
  }
}

// ── Delete ────────────────────────────────────────────────────────

async function deletePayment(p) {
  if (!confirm(t('payments.confirmDelete'))) return;
  if (processingMap[p.invoice_uuid] === 'delete') return;
  processingMap[p.invoice_uuid] = 'delete';
  try {
    await axios.delete('/invoices/' + p.invoice_uuid);
    toaster.success(t('payments.deleted'));
    payments.value = payments.value.filter(x => x.invoice_uuid !== p.invoice_uuid);
    if (showDetail.value && selectedPayment.value?.invoice_uuid === p.invoice_uuid) {
      showDetail.value = false;
    }
    fetchSummary();
  } catch (e) {
    console.error('[Payments:deletePayment]', { invoiceUuid: p.invoice_uuid, error: e });
    toaster.error(e.response?.data?.message ?? t('payments.errorGeneric'));
  } finally {
    delete processingMap[p.invoice_uuid];
  }
}

// ── Utilities ─────────────────────────────────────────────────────

function today() {
  return new Date().toISOString().slice(0, 10);
}

function statusLabel(status) {
  return {
    paid:      t('payments.statusPaid'),
    pending:   t('payments.statusPending'),
    overdue:   t('payments.statusOverdue'),
    cancelled: t('payments.statusCancelled'),
  }[status] ?? status;
}

function statusBadgeClass(status) {
  return {
    paid:      'bg-success-light text-success',
    pending:   'bg-warning-light text-warning',
    overdue:   'pay-badge-overdue',
    cancelled: 'bg-secondary-light text-secondary',
  }[status] ?? 'bg-secondary-light text-secondary';
}

function methodLabel(method) {
  return {
    cash:          t('payments.methodCash'),
    bank_transfer: t('payments.methodBankTransfer'),
    card:          t('payments.methodCard'),
    stripe:        'Stripe',
    check:         t('payments.methodCheck'),
    other:         t('payments.methodOther'),
  }[method] ?? (method ? method : '—');
}
</script>

<style scoped>

/* ── Skeleton ─────────────────────────────────────────────────── */
.skel {
  border-radius: 4px;
  background: linear-gradient(90deg, #e8e8e8 25%, #d4d4d4 50%, #e8e8e8 75%);
  background-size: 200% 100%;
  animation: skel-pulse 1.4s infinite;
}
.skel-text { height: 14px; width: 100%; }
@keyframes skel-pulse {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── KPI Grid ─────────────────────────────────────────────────── */
.pay-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
@media (max-width: 1100px) {
  .pay-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 580px) {
  .pay-kpi-grid { grid-template-columns: 1fr; }
}

/* ── KPI Card ─────────────────────────────────────────────────── */
.pay-kpi {
  background: #ffffff;
  border: 1px solid #e6eaef;
  border-radius: 12px;
  padding: 20px 20px 18px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
  box-shadow: 0 1px 4px rgba(13,17,23,.06), 0 2px 8px rgba(13,17,23,.05);
  transition: box-shadow .2s, transform .16s, border-color .2s;
}
.pay-kpi:hover {
  box-shadow: 0 4px 16px rgba(13,17,23,.10), 0 1px 4px rgba(13,17,23,.05);
  transform: translateY(-2px);
  border-color: #ccd2db;
}
.pay-kpi--alert {
  border-color: rgba(224, 123, 0, .28);
}

/* ── Icon badge ───────────────────────────────────────────────── */
.pay-kpi-icon-wrap {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
  transition: transform .18s;
}
.pay-kpi:hover .pay-kpi-icon-wrap { transform: scale(1.07); }

.pay-kpi-icon-wrap--pink   { background: rgba(233, 30, 99, .08);   color: #e91e63; }
.pay-kpi-icon-wrap--green  { background: rgba(0, 168, 84, .09);    color: #00a854; }
.pay-kpi-icon-wrap--blue   { background: rgba(37, 99, 235, .09);   color: #2563eb; }
.pay-kpi-icon-wrap--orange { background: rgba(224, 123, 0, .09);   color: #e07b00; }
.pay-kpi-icon-wrap--muted  { background: rgba(107, 114, 128, .09); color: #6b7280; }

/* ── Card body ────────────────────────────────────────────────── */
.pay-kpi-body { flex: 1; min-width: 0; }

.pay-kpi-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: #8b96a8;
  margin: 0 0 7px;
}

.pay-kpi-value {
  font-size: 22px;
  font-weight: 720;
  color: #0d1117;
  letter-spacing: -.03em;
  margin: 0 0 5px;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
}
.pay-kpi-value--warn { color: #e07b00; }

.pay-kpi-sk {
  display: inline-block;
  border-radius: 6px;
  height: 26px;
  width: 90px;
  background: linear-gradient(90deg, #e8e8e8 25%, #d4d4d4 50%, #e8e8e8 75%);
  background-size: 200% 100%;
  animation: skel-pulse 1.4s infinite;
  color: transparent !important;
  user-select: none;
}

.pay-kpi-meta {
  font-size: 12px;
  margin: 0;
  line-height: 1;
  color: #8b96a8;
}
.pay-kpi-meta--neutral { color: #8b96a8; }
.pay-kpi-meta--green   { color: #00a854; }
.pay-kpi-meta--blue    { color: #2563eb; }
.pay-kpi-meta--orange  { color: #e07b00; }

/* ── Status badge (overdue = orange) ──────────────────────────── */
.pay-badge-overdue {
  background-color: #fff3e0;
  color: #e65100;
}

/* ── Overlay (shared by panel and modals) ─────────────────────── */
.pay-overlay,
.pay-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, .52);
  z-index: 1055;
  display: flex;
}
.pay-modal-overlay {
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

/* ── Side panel (detail) ──────────────────────────────────────── */
.pay-overlay { justify-content: flex-end; }
.pay-panel {
  width: 420px;
  max-width: 100vw;
  height: 100vh;
  background: var(--bs-body-bg);
  border-left: 1px solid var(--bs-border-color);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.pay-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--bs-border-color);
  position: sticky;
  top: 0;
  background: var(--bs-body-bg);
  z-index: 1;
}
.pay-panel-body { padding: 1.5rem; flex: 1; }

/* ── Detail definition list ───────────────────────────────────── */
.pay-dl { display: grid; grid-template-columns: 120px 1fr; gap: .5rem .75rem; align-items: start; }
.pay-dl dt { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary); padding-top: 2px; }
.pay-dl dd { margin: 0; }

/* ── Record / Edit modal dialog ───────────────────────────────── */
.pay-modal-dialog {
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 24px 64px rgba(0, 0, 0, .18), 0 4px 16px rgba(0, 0, 0, .08);
  overflow: hidden;
}

.pay-modal-dialog .modal-content {
  border: none;
  border-radius: 0;
  box-shadow: none;
  background: transparent;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.pay-modal-dialog .modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 28px 20px;
  border-bottom: 1px solid #f0f2f5;
  flex-shrink: 0;
}

.pay-modal-dialog .modal-title {
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.01em;
}

.pay-modal-dialog .modal-body {
  padding: 24px 28px;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}

.pay-modal-dialog .modal-body .mb-3 {
  margin-bottom: 20px !important;
}

.pay-modal-dialog .modal-footer {
  padding: 16px 28px;
  border-top: 1px solid #f0f2f5;
  flex-shrink: 0;
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  background: #fafbfc;
}

/* ── Field labels ─────────────────────────────────────────────── */
.pay-modal-dialog .form-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
  display: block;
}

.pay-modal-dialog .form-label .text-danger {
  color: #E91E63 !important;
}

/* ── Inputs & selects ─────────────────────────────────────────── */
.pay-modal-dialog .form-control,
.pay-modal-dialog .form-select {
  height: 40px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 0.875rem;
  color: #111827;
  background: #fff;
  box-shadow: none;
  transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
}

.pay-modal-dialog .form-control:focus,
.pay-modal-dialog .form-select:focus {
  border-color: #E91E63;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.10);
  outline: none;
}

/* ── Textarea: fix permanent accent border bug ────────────────── */
.pay-modal-dialog textarea.form-control {
  height: auto;
  min-height: 88px;
  border-color: #e5e7eb !important;
  box-shadow: none !important;
  resize: vertical;
}

.pay-modal-dialog textarea.form-control:focus {
  border-color: #E91E63 !important;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.10) !important;
}

/* ── € prefix: visually merged into the input ────────────────── */
.pay-modal-dialog .input-group > .input-group-text {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-right: none;
  border-radius: 8px 0 0 8px;
  color: #6b7280;
  font-size: 0.875rem;
  font-weight: 500;
  padding: 0 12px;
  min-height: 40px;
  transition: border-color .15s ease-in-out;
}

.pay-modal-dialog .input-group > .form-control {
  border-left: none;
  border-radius: 0 8px 8px 0 !important;
  box-shadow: none !important;
}

.pay-modal-dialog .input-group:focus-within > .input-group-text {
  border-color: #E91E63;
}

.pay-modal-dialog .input-group > .form-control:focus {
  border-color: #E91E63;
  box-shadow: none !important;
}

/* ── Empty-state hint ─────────────────────────────────────────── */
.pay-modal-dialog .form-text {
  font-size: 0.8rem;
  margin-top: 6px;
}

/* ── Modal footer buttons ─────────────────────────────────────── */
.pay-modal-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
  border: 1px solid transparent;
  cursor: pointer;
  transition: background .15s ease-in-out, border-color .15s ease-in-out,
              box-shadow .15s ease-in-out, transform .15s ease-in-out;
  line-height: 1;
  white-space: nowrap;
}

.pay-modal-btn-cancel {
  background: #fff;
  border-color: #e5e7eb;
  color: #374151;
}
.pay-modal-btn-cancel:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #d1d5db;
  color: #111827;
  transform: translateY(-1px);
}

.pay-modal-btn-save {
  background: #E91E63;
  color: #fff;
  border-color: #E91E63;
}
.pay-modal-btn-save:hover:not(:disabled) {
  background: #c2185b;
  border-color: #c2185b;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
}
.pay-modal-btn-save:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* ── Transitions ──────────────────────────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from,  .fade-leave-to      { opacity: 0; }

.slide-right-enter-active { transition: transform .25s cubic-bezier(.22,.61,.36,1); }
.slide-right-leave-active { transition: transform .2s ease-in; }
.slide-right-enter-from   { transform: translateX(100%); }
.slide-right-leave-to     { transform: translateX(100%); }

/* ── Dark mode ────────────────────────────────────────────────── */
@media (prefers-color-scheme: dark) {
  .skel {
    background: linear-gradient(90deg, #2a2a2a 25%, #333 50%, #2a2a2a 75%);
    background-size: 200% 100%;
  }
  .pay-badge-overdue { background-color: #431407; color: #fb923c; }

  .pay-kpi {
    background: #161b22;
    border-color: #29323f;
    box-shadow: 0 1px 4px rgba(0,0,0,.35), 0 2px 8px rgba(0,0,0,.25);
  }
  .pay-kpi:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.45), 0 1px 4px rgba(0,0,0,.3);
    border-color: #3b4758;
  }
  .pay-kpi--alert { border-color: rgba(224, 123, 0, .35); }

  .pay-kpi-icon-wrap--pink   { background: rgba(233, 30, 99, .14); }
  .pay-kpi-icon-wrap--green  { background: rgba(0, 168, 84, .14);  }
  .pay-kpi-icon-wrap--blue   { background: rgba(37, 99, 235, .14); }
  .pay-kpi-icon-wrap--orange { background: rgba(224, 123, 0, .14); }
  .pay-kpi-icon-wrap--muted  { background: rgba(107, 114, 128, .14); color: #8b949e; }

  .pay-kpi-label { color: #606b79; }
  .pay-kpi-value { color: #e6edf3; }
  .pay-kpi-meta--neutral { color: #606b79; }

  .pay-kpi-sk {
    background: linear-gradient(90deg, #2a2a2a 25%, #333 50%, #2a2a2a 75%);
    background-size: 200% 100%;
  }
}
</style>
