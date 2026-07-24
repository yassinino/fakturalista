<template>

  <!-- Loading skeleton -->
  <div v-if="!loaded" class="content">
    <div class="d-flex justify-content-center align-items-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">{{ $t('common.loading') }}</span>
      </div>
    </div>
  </div>

  <!-- Draft: fast edit form matching the create-invoice design -->
  <EditInvoiceForm
    v-else-if="!state.is_locked"
    :key="state.uuid"
    :invoice="state"
    @saveDocument="saveInvoice"
  />

  <!-- Locked (issued / paid / cancelled): read-only view with action buttons -->
  <div v-else class="content">
    <div class="block block-rounded">
      <!-- Redesigned invoice action bar -->
      <div class="ib-header">

        <!-- Left: reference + status badges -->
        <div class="ib-identity">
          <h3 class="ib-ref">{{ state.reference || '-' }}</h3>
          <div class="ib-badges">
            <span class="ib-badge" :class="`ib-badge--${state.status}`">
              {{ statusLabel(state.status) }}
            </span>
            <span
              v-if="state.sent_at"
              class="ib-badge ib-badge--sent"
              :title="t('invoices.sentTo', { email: state.sent_to })"
            >
              <i class="fa fa-check-circle"></i>{{ $t('invoices.sentBadge') }}
            </span>
            <span
              v-if="state.paid_via === 'stripe' && state.paid_at"
              class="ib-badge ib-badge--stripe"
              :title="'Payé via Stripe le ' + formatPaidAt(state.paid_at)"
            >
              <i class="fa fa-credit-card"></i> Stripe · {{ formatPaidAt(state.paid_at) }}
            </span>
          </div>
        </div>

        <!-- Right: actions -->
        <div class="ib-actions">

          <!-- PRIMARY: Send to client -->
          <button
            v-if="state.status !== 'cancelled'"
            class="ib-btn ib-btn--primary"
            @click="sendInvoice"
            :disabled="!state.customer_email"
            :title="!state.customer_email ? $t('invoices.noClientEmail') : ''"
          >
            <i class="fa fa-paper-plane"></i>
            <span>{{ $t('invoices.actionSend') }}</span>
          </button>

          <!-- SECONDARY: Mark paid -->
          <button
            v-if="state.status === 'issued'"
            class="ib-btn ib-btn--ghost"
            @click="markPaid"
          >
            <i class="fa fa-check-circle"></i>
            <span>{{ $t('invoices.actionMarkPaid') }}</span>
          </button>

          <!-- SECONDARY: WhatsApp -->
          <button
            v-if="state.status !== 'cancelled'"
            class="ib-btn ib-btn--ghost"
            @click="whatsappInvoice"
            :disabled="sendingWa || !state.customer_phone"
            :title="!state.customer_phone ? $t('invoices.noClientPhone') : ''"
          >
            <i v-if="sendingWa" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fab fa-whatsapp"></i>
            <span>WhatsApp</span>
          </button>

          <!-- SECONDARY: Duplicate -->
          <button class="ib-btn ib-btn--ghost" @click="duplicateInvoice">
            <i class="fa fa-copy"></i>
            <span>{{ $t('invoices.actionDuplicate') }}</span>
          </button>

          <!-- SECONDARY: PDF -->
          <button class="ib-btn ib-btn--ghost" @click="printInvoice">
            <i class="fa fa-file-pdf"></i>
            <span>{{ $t('invoices.document') }}</span>
          </button>

          <!-- Separator + DESTRUCTIVE: Cancel -->
          <template v-if="state.status !== 'cancelled'">
            <span class="ib-sep"></span>
            <button class="ib-btn ib-btn--danger" @click="cancelInvoice">
              <i class="fa fa-ban"></i>
              <span>{{ $t('invoices.actionCancel') }}</span>
            </button>
          </template>

        </div>
      </div>
      <div class="block-content">

        <!-- No-email / no-phone notices (non-blocking) -->
        <div v-if="!state.customer_email || !state.customer_phone" class="alert alert-warning d-flex align-items-start gap-2 mb-3">
          <i class="fa fa-address-card mt-1"></i>
          <span>
            <span v-if="!state.customer_email">{{ $t('invoices.noClientEmail') }}</span>
            <span v-if="!state.customer_email && !state.customer_phone"> · </span>
            <span v-if="!state.customer_phone">{{ $t('invoices.noClientPhone') }}</span>
          </span>
        </div>

        <!-- Sent confirmation notice -->
        <div v-if="state.sent_at" class="alert alert-success d-flex align-items-center gap-2 mb-3">
          <i class="fa fa-check-circle"></i>
          <span>{{ $t('invoices.sentTo', { email: state.sent_to }) }}</span>
        </div>

        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
          <i class="fa fa-lock"></i>
          <span>{{ $t('invoices.lockedNotice') }}</span>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ $t('invoices.table.number') }}</label>
            <p class="form-control-plaintext">{{ state.reference || '-' }}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ $t('invoices.table.date') }}</label>
            <p class="form-control-plaintext">{{ state.date }}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ $t('invoices.table.total') }}</label>
            <p class="form-control-plaintext fw-bold">{{ $toComma(state.total) }} €</p>
          </div>
        </div>

        <table class="table table-vcenter" v-if="state.carts && state.carts.length">
          <thead>
            <tr>
              <th>{{ $t('invoices.cart.description') }}</th>
              <th class="text-center">{{ $t('invoices.cart.qty') }}</th>
              <th class="text-end">{{ $t('invoices.cart.price') }}</th>
              <th class="text-end">{{ $t('invoices.cart.total') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, i) in state.carts" :key="i">
              <td>{{ line.description || '-' }}</td>
              <td class="text-center">{{ line.qty }}</td>
              <td class="text-end">{{ $toComma(line.price) }}</td>
              <td class="text-end">{{ $toComma(line.total) }}</td>
            </tr>
          </tbody>
        </table>

        <div class="d-flex justify-content-end mt-3">
          <router-link to="/admin/invoices" class="btn btn-outline-secondary">
            ← {{ $t('common.back') }}
          </router-link>
        </div>
      </div>
    </div>
  </div>

  <SendInvoiceModal
    v-if="showSendModal"
    :customer-email="state.customer_email"
    :customer-name="state.customer_name ?? ''"
    :invoice-ref="state.reference ?? ''"
    :company-name="state.company_name ?? ''"
    :sending="modalSending"
    @close="showSendModal = false"
    @send="handleModalSend"
  />

</template>

<script setup>
import { ref, onMounted }      from "vue";
import axios                   from 'axios';
import { createToaster }       from '@meforma/vue-toaster';
import { useRoute, useRouter } from 'vue-router';
import { useI18n }             from 'vue-i18n';
import EditInvoiceForm         from './EditInvoiceForm.vue';
import SendInvoiceModal        from './SendInvoiceModal.vue';

const toaster = createToaster();
const router  = useRouter();
const route   = useRoute();
const { t }   = useI18n();
const uuid    = ref(route.params.id);

const loaded         = ref(false);
const sending        = ref(false);
const sendingWa      = ref(false);
const showSendModal  = ref(false);
const modalSending   = ref(false);

const state = ref({
  customer_id:     '',
  customer_email:  '',
  customer_phone:  '',
  address:         '',
  discount_rate:   0,
  discount_amount: 0,
  date:            '',
  status:          '',
  is_locked:       false,
  expiration_date: '',
  carts:           [],
  sent_at:         null,
  sent_to:         null,
});

onMounted(async () => {
  const res    = await axios.get('/invoices/' + uuid.value + '/edit');
  state.value  = res.data.invoice;
  loaded.value = true;
});

// Called by EditInvoiceForm via @saveDocument - receives the reactive state object directly.
async function saveInvoice(s) {
  axios.post('/invoices/' + s.uuid, s, { params: { _method: 'put' } })
    .then(res => {
      toaster.success(res.data.message);
      router.push('/admin/invoices');
    });
}

// ── Status helpers ──────────────────────────────────────

function formatPaidAt(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function statusBadgeClass(status) {
  const map = {
    draft:     'bg-secondary text-white',
    issued:    'bg-info text-white',
    paid:      'bg-success text-white',
    cancelled: 'bg-danger text-white',
  };
  return map[status] ?? 'bg-secondary text-white';
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

// ── Lifecycle actions (locked view only) ────────────────

function sendInvoice() {
  if (!state.value.customer_email) return;
  showSendModal.value = true;
}

async function handleModalSend(message) {
  modalSending.value = true;
  try {
    const res = await axios.post('/invoices/' + uuid.value + '/send', { custom_message: message });
    toaster.success(res.data.message);
    state.value.status  = res.data.status;
    state.value.sent_at = res.data.sent_at;
    state.value.sent_to = res.data.sent_to;
    showSendModal.value = false;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  } finally {
    modalSending.value = false;
  }
}

async function whatsappInvoice() {
  if (!state.value.customer_phone) return;
  sendingWa.value = true;
  try {
    const res = await axios.post('/invoices/' + uuid.value + '/whatsapp');
    // Update status in case invoice was auto-issued (draft → issued)
    state.value.status = res.data.status;
    window.open(res.data.wa_link, '_blank', 'noopener,noreferrer');
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  } finally {
    sendingWa.value = false;
  }
}

async function markPaid() {
  if (!confirm(t('invoices.confirmMarkPaid', { ref: state.value.reference }))) return;
  try {
    const res = await axios.post('/invoices/' + uuid.value + '/mark-paid');
    toaster.success(res.data.message);
    state.value.status = res.data.status;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}

async function cancelInvoice() {
  if (!confirm(t('invoices.confirmCancel', { ref: state.value.reference }))) return;
  try {
    const res = await axios.post('/invoices/' + uuid.value + '/cancel');
    toaster.success(res.data.message);
    state.value.status = res.data.status;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}

async function duplicateInvoice() {
  try {
    const res = await axios.post('/invoices/' + uuid.value + '/duplicate');
    toaster.success(res.data.message);
    router.push('/admin/invoices/edit/' + res.data.duplicate_uuid);
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}

async function printInvoice() {
  try {
    const res = await axios.post('/invoices/print', { uuid: uuid.value });
    toaster.success(res.data.message);
    window.open(res.data.pdf_url, '_blank');
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('invoices.errorGeneric'));
  }
}
</script>

<style lang="scss">
// External library CSS - imported here once so both the locked view
// and EditInvoiceForm's FlatPickr have the styles they need.
@import "flatpickr/dist/flatpickr.css";
@import "@/assets/scss/vendor/flatpickr";
</style>

<style scoped>
/* ── Invoice header bar (ib-*) ────────────────────────────
   All rules scoped to edit.vue. Zero global leakage.
   ──────────────────────────────────────────────────────── */

.ib-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px 16px;
  padding: 18px 24px;
  border-bottom: 1px solid #f0f0f0;
}

/* Left column: reference + badges */
.ib-identity {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
}

.ib-ref {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: #1a1a1a;
}

.ib-badges {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}

/* Badge base */
.ib-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
  border: 1px solid transparent;
}

/* Status-specific badge colors - soft, muted */
.ib-badge--draft     { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
.ib-badge--issued    { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
.ib-badge--paid      { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
.ib-badge--cancelled { background: #fff1f2; color: #9f1239; border-color: #fecdd3; }
.ib-badge--sent      { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.ib-badge--stripe    { background: #eef2ff; color: #3730a3; border-color: #c7d2fe; }

/* Right column: action buttons */
.ib-actions {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
}

/* Thin vertical separator before the destructive action */
.ib-sep {
  display: inline-block;
  width: 1px;
  height: 20px;
  background: #e5e7eb;
  margin: 0 2px;
  flex-shrink: 0;
}

/* Button base - all buttons share identical sizing and radius */
.ib-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid transparent;
  background: none;
  line-height: 1;
  white-space: nowrap;
  transition: background .15s ease-in-out, border-color .15s ease-in-out,
              color .15s ease-in-out, box-shadow .15s ease-in-out,
              transform .15s ease-in-out;
}
.ib-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.ib-btn i { font-size: 13px; }

/* PRIMARY */
.ib-btn--primary {
  background: #E91E63;
  color: #fff;
  border-color: #E91E63;
}
.ib-btn--primary:hover:not(:disabled) {
  background: #c2185b;
  border-color: #c2185b;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
}

/* GHOST */
.ib-btn--ghost {
  background: #fff;
  color: #374151;
  border-color: #e5e7eb;
}
.ib-btn--ghost:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #d1d5db;
  color: #111827;
  transform: translateY(-1px);
}

/* DANGER */
.ib-btn--danger {
  background: transparent;
  color: #be123c;
  border-color: #fecdd3;
}
.ib-btn--danger:hover:not(:disabled) {
  background: #fff1f2;
  border-color: #fda4af;
  transform: translateY(-1px);
}

@media (max-width: 640px) {
  .ib-btn--ghost span { display: none; }
  .ib-btn--ghost { padding: 8px 10px; }
}
</style>
