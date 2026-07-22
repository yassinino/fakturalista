<template>
  <div class="content">

    <BaseBlock :title="$t('quotes.title')">
      <template #options>
        <div class="block-options-item">
          <router-link to="quotes/new" class="btn btn-primary">
            {{ $t("quotes.newTitle") }}
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
        :rows="quotes"
        :server-side="false"
        :status-options="statusOptions"
        :search-fields="['reference', 'customer']"
        search-placeholder="Search quotes…"
      >
        <template #head="{ sortField, sortDir, setSort }">
          <tr>
            <th class="dt-cb-col">
              <input type="checkbox" class="form-check-input" @change="clickedAll">
            </th>
            <th class="dt-sortable" @click="setSort('reference')">
              {{ $t("quotes.table.number") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'reference' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-sortable" @click="setSort('customer')">
              {{ $t("quotes.table.customer") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'customer' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-sortable" @click="setSort('date')">
              {{ $t("quotes.table.date") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'date' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th>{{ $t("quotes.table.expirationDate") }}</th>
            <th class="dt-sortable" @click="setSort('total')">
              {{ $t("quotes.table.total") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'total' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-sortable" @click="setSort('status')">
              {{ $t("quotes.table.status") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'status' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-ac-col">{{ $t("common.actions") }}</th>
          </tr>
        </template>

        <template #body="{ items }">
          <tr v-for="quote in items" :key="quote.uuid">
            <td class="dt-cb-col">
              <input type="checkbox" class="form-check-input" v-model="quote.checked">
            </td>
            <td>
              <router-link :to="'quotes/edit/' + quote.uuid">{{ quote.reference }}</router-link>
            </td>
            <td>{{ quote.customer }}</td>
            <td>{{ quote.date }}</td>
            <td>{{ quote.expiration_date }}</td>
            <td>{{ $toComma(quote.total) }}</td>
            <td>
              <span class="fs-xs fw-semibold d-inline-block py-1 px-3 rounded-pill"
                :class="statusBadgeClass(quote.status)">
                {{ statusLabel(quote.status) }}
              </span>
            </td>
            <td class="dt-ac-col">
              <RowActionMenu>
                <a
                  v-if="quote.status !== 'converted' && quote.status !== 'cancelled'"
                  class="dropdown-item"
                  style="color:#16a34a;font-weight:600"
                  href="javascript:void(0)"
                  @click.prevent="openConvertModal(quote)"
                  :class="{ disabled: converting && quoteToConvert?.uuid === quote.uuid }"
                >
                  <i v-if="converting && quoteToConvert?.uuid === quote.uuid"
                    class="fa fa-spinner fa-spin fa-fw me-1"></i>
                  <i v-else class="fa fa-bolt fa-fw me-1" style="color:#16a34a"></i>
                  {{ $t("quotes.actionConvert") }}
                </a>

                <a
                  v-if="quote.status !== 'converted'"
                  class="dropdown-item"
                  :class="{ 'text-muted': !quote.customer_email }"
                  href="javascript:void(0)"
                  @click.prevent="quote.customer_email ? sendQuote(quote) : null"
                  :title="!quote.customer_email ? $t('quotes.noClientEmail') : ''"
                >
                  <i class="fa fa-envelope fa-fw me-1"></i>{{ $t("quotes.actionSend") }}
                  <small v-if="!quote.customer_email" class="text-muted ms-1">(sin email)</small>
                </a>

                <a
                  v-if="quote.status !== 'converted'"
                  class="dropdown-item"
                  :class="{ 'text-muted': !quote.customer_phone }"
                  href="javascript:void(0)"
                  @click.prevent="quote.customer_phone ? sendWhatsApp(quote) : null"
                  :title="!quote.customer_phone ? $t('quotes.noClientPhone') : ''"
                >
                  <i class="fab fa-whatsapp fa-fw me-1" style="color:#25D366"></i>{{ $t("quotes.actionWhatsApp") }}
                  <small v-if="!quote.customer_phone" class="text-muted ms-1">(sin teléfono)</small>
                </a>

                <a
                  class="dropdown-item"
                  href="javascript:void(0)"
                  @click.prevent="duplicateQuote(quote)"
                  :class="{ disabled: isProcessing(quote.uuid) }"
                >
                  <i v-if="processingMap[quote.uuid] === 'duplicate'"
                    class="fa fa-spinner fa-spin fa-fw me-1"></i>
                  <i v-else class="fa fa-copy fa-fw me-1"></i>
                  {{ $t("quotes.actionDuplicate") }}
                </a>

                <a
                  class="dropdown-item"
                  href="javascript:void(0)"
                  @click.prevent="printQuote(quote)"
                  :class="{ disabled: isProcessing(quote.uuid) }"
                >
                  <i v-if="processingMap[quote.uuid] === 'print'"
                    class="fa fa-spinner fa-spin fa-fw me-1"></i>
                  <i v-else class="fa fa-file-pdf fa-fw me-1"></i>
                  {{ $t("quotes.document") }}
                </a>

                <div class="dropdown-divider"></div>

                <a
                  v-if="quote.status !== 'cancelled' && quote.status !== 'converted'"
                  class="dropdown-item text-danger"
                  href="javascript:void(0)"
                  @click.prevent="cancelQuote(quote)"
                  :class="{ disabled: isProcessing(quote.uuid) }"
                >
                  <i v-if="processingMap[quote.uuid] === 'cancel'"
                    class="fa fa-spinner fa-spin fa-fw me-1"></i>
                  <i v-else class="fa fa-ban fa-fw me-1"></i>
                  {{ $t("quotes.actionCancel") }}
                </a>

                <a
                  class="dropdown-item text-danger"
                  href="javascript:void(0)"
                  @click.prevent="deleteQuote(quote)"
                  :class="{ disabled: isProcessing(quote.uuid) }"
                >
                  <i v-if="processingMap[quote.uuid] === 'delete'"
                    class="fa fa-spinner fa-spin fa-fw me-1"></i>
                  <i v-else class="fa fa-trash fa-fw me-1"></i>
                  {{ $t("common.delete") }}
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
    v-if="showSendModal && sendModalQuote"
    :customer-email="sendModalQuote.customer_email"
    :customer-name="sendModalQuote.customer ?? ''"
    :invoice-ref="sendModalQuote.reference ?? ''"
    :company-name="companyName"
    :sending="modalSending"
    :is-quote="true"
    @close="showSendModal = false; sendModalQuote = null"
    @send="handleModalSend"
  />

  <!-- Convert confirmation modal -->
  <Teleport to="body">
    <div v-if="showConvertModal" class="cq-overlay" @click.self="closeConvertModal">
      <div
        class="cq-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cq-title"
        @keydown.esc.prevent="closeConvertModal"
        tabindex="-1"
        ref="convertModalEl"
      >
        <div class="cq-header">
          <h3 class="cq-title" id="cq-title">
            <i class="fa fa-bolt me-2" style="color:#16a34a"></i>{{ $t("quotes.convertTitle") }}
          </h3>
          <button class="cq-close" @click="closeConvertModal" :disabled="converting">&times;</button>
        </div>
        <div class="cq-body">
          <p class="cq-message">
            {{ $t("quotes.convertMessage") }}
            <strong>{{ quoteToConvert?.reference }}</strong>
          </p>
          <p class="cq-hint">
            <i class="fa fa-info-circle me-1 text-muted"></i>
            {{ $t("quotes.convertHint") }}
          </p>
        </div>
        <div class="cq-footer">
          <button class="cq-btn cq-btn-cancel" @click="closeConvertModal" :disabled="converting">
            {{ $t("common.cancel") }}
          </button>
          <button class="cq-btn cq-btn-confirm" @click="confirmConvert" :disabled="converting">
            <i v-if="converting" class="fa fa-spinner fa-spin me-1"></i>
            <i v-else class="fa fa-bolt me-1"></i>
            Convert
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>


<script setup>
import { ref, reactive, computed, nextTick, onMounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import { createToaster } from "@meforma/vue-toaster";
import DataTableShell   from "@/views/admin/layouts/DataTableShell.vue";
import RowActionMenu    from "@/views/admin/layouts/RowActionMenu.vue";
import SendInvoiceModal from "@/views/admin/invoices/SendInvoiceModal.vue";
import BulkActionBar    from "@/views/admin/layouts/BulkActionBar.vue";
import BulkDeleteModal  from "@/views/admin/layouts/BulkDeleteModal.vue";

const { t }   = useI18n();
const router  = useRouter();
const toaster = createToaster();

// ── Data ──────────────────────────────────────────────────
const quotes      = ref([]);
const companyName = ref('');

const showBulkDeleteModal = ref(false);
const bulkDeleting        = ref(false);
const selectedIds         = computed(() => quotes.value.filter(q => q.checked).map(q => q.uuid));

const processingMap = reactive({});
const isProcessing  = (uuid) => !!processingMap[uuid];

const statusOptions = computed(() => [
  { value: 'draft',     label: t('quotes.statusDraft') },
  { value: 'sent',      label: t('quotes.statusSent') },
  { value: 'converted', label: t('quotes.statusConverted') },
  { value: 'cancelled', label: t('quotes.statusCancelled') },
]);

onMounted(async () => {
  const { data } = await axios.get("/quotes");
  quotes.value      = data.quotes;
  companyName.value = data.company_name ?? '';
});

// ── Status helpers ─────────────────────────────────────────

function statusBadgeClass(status) {
  const map = {
    draft:     'bg-secondary-light text-secondary',
    sent:      'bg-info-light text-info',
    converted: 'bg-success-light text-success',
    cancelled: 'bg-danger-light text-danger',
  };
  return map[status] ?? 'bg-secondary-light text-secondary';
}

function statusLabel(status) {
  const map = {
    draft:     t('quotes.statusDraft'),
    sent:      t('quotes.statusSent'),
    converted: t('quotes.statusConverted'),
    cancelled: t('quotes.statusCancelled'),
  };
  return map[status] ?? status;
}

// ── Table helpers ─────────────────────────────────────────

function clickedAll(e) {
  const checked = e.target.checked;
  quotes.value = quotes.value.map(q => ({ ...q, checked }));
}

function clearSelection() {
  quotes.value = quotes.value.map(q => ({ ...q, checked: false }));
}

async function bulkDelete() {
  const ids = selectedIds.value.slice();
  if (!ids.length) return;
  bulkDeleting.value = true;
  try {
    await axios.post('/quotes/bulk-delete', { ids });
    quotes.value = quotes.value.filter(q => !ids.includes(q.uuid));
    showBulkDeleteModal.value = false;
    toaster.success(t('common.bulkDeleteSuccess', { count: ids.length }));
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('quotes.errorGeneric'));
  } finally {
    bulkDeleting.value = false;
  }
}

// ── Action: Download Quote PDF ────────────────────────────

async function printQuote(quote) {
  if (isProcessing(quote.uuid)) return;
  processingMap[quote.uuid] = 'print';
  try {
    const res = await axios.get("/quotes/" + quote.uuid + "/pdf", { responseType: 'blob' });
    const blobUrl = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
    const a = document.createElement('a');
    a.href = blobUrl;
    a.download = 'quote-' + (quote.reference ?? quote.uuid) + '.pdf';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(blobUrl);
    toaster.success(t('quotes.pdfSuccess'));
  } catch (e) {
    toaster.error(t('quotes.pdfError'));
  } finally {
    delete processingMap[quote.uuid];
  }
}

// ── Action: Duplicate ─────────────────────────────────────

async function duplicateQuote(quote) {
  if (isProcessing(quote.uuid)) return;
  processingMap[quote.uuid] = 'duplicate';
  try {
    const res = await axios.post("/quotes/" + quote.uuid + "/duplicate");
    toaster.success(res.data.message);
    const { data } = await axios.get("/quotes");
    quotes.value = data.quotes;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t("quotes.errorGeneric"));
  } finally {
    delete processingMap[quote.uuid];
  }
}

// ── Action: Cancel ────────────────────────────────────────

async function cancelQuote(quote) {
  if (isProcessing(quote.uuid)) return;
  if (!confirm(t("quotes.confirmCancel", { ref: quote.reference }))) return;
  processingMap[quote.uuid] = 'cancel';
  try {
    const res = await axios.post("/quotes/" + quote.uuid + "/cancel");
    toaster.success(res.data.message);
    quote.status = res.data.status;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t("quotes.errorGeneric"));
  } finally {
    delete processingMap[quote.uuid];
  }
}

// ── Action: Delete ────────────────────────────────────────

async function deleteQuote(quote) {
  if (isProcessing(quote.uuid)) return;
  if (!confirm(t("quotes.confirmDelete"))) return;
  processingMap[quote.uuid] = 'delete';
  try {
    await axios.delete("/quotes/" + quote.uuid);
    quotes.value = quotes.value.filter(q => q.uuid !== quote.uuid);
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t("quotes.errorGeneric"));
  } finally {
    delete processingMap[quote.uuid];
  }
}

// ── Action: WhatsApp ──────────────────────────────────────

function sendWhatsApp(quote) {
  if (!quote.customer_phone) return;
  const phone   = quote.customer_phone.replace(/\D/g, '');
  const pdfText = encodeURIComponent(`Devis ${quote.reference}`);
  window.open(`https://wa.me/${phone}?text=${pdfText}`, '_blank', 'noopener,noreferrer');
}

// ── Action: Send (modal) ──────────────────────────────────

const showSendModal  = ref(false);
const sendModalQuote = ref(null);
const modalSending   = ref(false);

function sendQuote(quote) {
  if (!quote.customer_email) return;
  sendModalQuote.value = quote;
  showSendModal.value  = true;
}

async function handleModalSend(message) {
  const quote = sendModalQuote.value;
  if (!quote) return;
  modalSending.value = true;
  try {
    const res = await axios.post("/quotes/" + quote.uuid + "/send", { custom_message: message });
    toaster.success(res.data.message);
    quote.status         = res.data.status;
    showSendModal.value  = false;
    sendModalQuote.value = null;
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t("quotes.errorGeneric"));
  } finally {
    modalSending.value = false;
  }
}

// ── Action: Convert (modal) ───────────────────────────────

const showConvertModal = ref(false);
const quoteToConvert   = ref(null);
const converting       = ref(false);
const convertModalEl   = ref(null);

async function openConvertModal(quote) {
  if (converting.value) return;
  quoteToConvert.value   = quote;
  showConvertModal.value = true;
  await nextTick();
  convertModalEl.value?.focus();
}

function closeConvertModal() {
  if (converting.value) return;
  showConvertModal.value = false;
  quoteToConvert.value   = null;
}

async function confirmConvert() {
  if (converting.value || !quoteToConvert.value) return;
  converting.value = true;
  try {
    const res = await axios.post("/quotes/" + quoteToConvert.value.uuid + "/convert");
    if (res.data.success) {
      toaster.success(t("quotes.convertSuccess"));
      const row = quotes.value.find(q => q.uuid === quoteToConvert.value.uuid);
      if (row) row.status = "converted";
      showConvertModal.value = false;
      quoteToConvert.value   = null;
      router.push("/admin/invoices/edit/" + res.data.invoice_uuid);
    } else {
      toaster.error(res.data.message || t("quotes.errorGeneric"));
    }
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t("quotes.errorGeneric"));
  } finally {
    converting.value = false;
  }
}
</script>


<style scoped>
/* Convert confirmation modal */
.cq-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 1050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.cq-modal {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  outline: none;
}

.cq-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f0f0f0;
}

.cq-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #1a1a1a;
}

.cq-close {
  background: none;
  border: none;
  font-size: 22px;
  color: #9ca3af;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  transition: color 0.15s;
}
.cq-close:hover:not(:disabled) { color: #374151; }
.cq-close:disabled { opacity: 0.4; cursor: not-allowed; }

.cq-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cq-message {
  font-size: 14px;
  color: #374151;
  margin: 0;
  line-height: 1.6;
}

.cq-hint {
  font-size: 12.5px;
  color: #6b7280;
  margin: 0;
  line-height: 1.6;
  background: #f8f9fc;
  border-radius: 8px;
  padding: 10px 12px;
}

.cq-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid #f0f0f0;
}

.cq-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
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
.cq-btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

.cq-btn-cancel {
  background: #fff;
  border-color: #e5e7eb;
  color: #374151;
}
.cq-btn-cancel:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #d1d5db;
  color: #111827;
  transform: translateY(-1px);
}

.cq-btn-confirm {
  background: #22c55e;
  color: #fff;
  border-color: #22c55e;
}
.cq-btn-confirm:hover:not(:disabled) {
  background: #16a34a;
  border-color: #16a34a;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.22);
}
</style>
