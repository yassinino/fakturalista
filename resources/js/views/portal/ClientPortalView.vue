<template>
  <div class="pt-page" :style="accentStyle">

    <!-- Loading skeleton -->
    <div v-if="state === 'loading'" class="pt-skeleton" role="status" :aria-label="t('portal.loading')">
      <div class="pt-skel pt-skel-header"></div>
      <div class="pt-skel-main">
        <div class="pt-skel pt-skel-line" style="width: 60%; height: 26px;"></div>
        <div class="pt-skel pt-skel-line" style="width: 40%;"></div>
        <div class="pt-skel-cards">
          <div class="pt-skel pt-skel-card"></div>
          <div class="pt-skel pt-skel-card"></div>
          <div class="pt-skel pt-skel-card"></div>
        </div>
        <div class="pt-skel pt-skel-card" style="height: 220px;"></div>
      </div>
    </div>

    <!-- Not found -->
    <div v-else-if="state === 'notfound'" class="pt-state">
      <div class="pt-state-card">
        <div class="pt-state-icon">🔍</div>
        <h1>{{ t('portal.error.notFoundTitle') }}</h1>
        <p>{{ t('portal.error.notFoundText') }}</p>
      </div>
    </div>

    <!-- Revoked / expired -->
    <div v-else-if="state === 'revoked'" class="pt-state">
      <div class="pt-state-card">
        <div class="pt-state-icon">🔒</div>
        <h1>{{ t('portal.error.revokedTitle') }}</h1>
        <p>{{ t('portal.error.revokedText') }}</p>
      </div>
    </div>

    <!-- Generic error -->
    <div v-else-if="state === 'error'" class="pt-state">
      <div class="pt-state-card">
        <div class="pt-state-icon">⚠️</div>
        <h1>{{ t('portal.error.genericTitle') }}</h1>
        <p>{{ t('portal.error.genericText') }}</p>
        <button type="button" class="pt-retry-btn" @click="load">{{ t('portal.error.retry') }}</button>
      </div>
    </div>

    <!-- Ready -->
    <template v-else-if="state === 'ready' && data">
      <header class="pt-header">
        <div class="pt-header-inner">
          <div class="pt-brand">
            <img v-if="data.company.logo_url" :src="data.company.logo_url" :alt="data.company.name" class="pt-logo">
            <span v-else class="pt-logo-fallback" aria-hidden="true">{{ initials(data.company.name) }}</span>
            <span class="pt-company-name">{{ data.company.name }}</span>
          </div>

          <div class="pt-lang" ref="langMenuRef">
            <button type="button" class="pt-lang-btn" @click="langOpen = !langOpen" :aria-label="t('portal.language')" :aria-expanded="langOpen">
              <span aria-hidden="true">{{ flag(locale) }}</span>
              <span class="pt-lang-code">{{ locale.toUpperCase() }}</span>
            </button>
            <div v-if="langOpen" class="pt-lang-menu" role="menu">
              <button
                v-for="l in visibleLocales"
                :key="l"
                type="button"
                class="pt-lang-item"
                role="menuitem"
                :class="{ 'pt-lang-item--active': l === locale }"
                @click="changeLocale(l)"
              >
                <span aria-hidden="true">{{ flag(l) }}</span> {{ t('languages.' + l) }}
              </button>
            </div>
          </div>
        </div>
      </header>

      <main class="pt-main">
        <section class="pt-welcome">
          <h1 class="pt-greeting">{{ t('portal.greeting', { name: data.customer.name }) }}</h1>
          <p class="pt-subline">{{ t('portal.subline', { company: data.company.name }) }}</p>
        </section>

        <!-- Return from Stripe Checkout (Step 6B) - display only; the invoice
             status always comes from the server (signed webhook). -->
        <div
          v-if="paymentBanner"
          class="pt-pay-banner"
          :class="'pt-pay-banner--' + paymentBanner"
          role="status"
          aria-live="polite"
        >
          <span class="pt-pay-banner-icon" aria-hidden="true">
            <i v-if="paymentBanner === 'verifying'" class="fa fa-spinner fa-spin"></i>
            <i v-else-if="paymentBanner === 'confirmed'" class="fa fa-check-circle"></i>
            <i v-else-if="paymentBanner === 'pending'" class="fa fa-clock"></i>
            <i v-else class="fa fa-info-circle"></i>
          </span>
          <div class="pt-pay-banner-body">
            <p class="pt-pay-banner-title">{{ t('portal.payment.banner.' + paymentBanner + 'Title') }}</p>
            <p class="pt-pay-banner-text">{{ t('portal.payment.banner.' + paymentBanner + 'Text') }}</p>
          </div>
          <button
            v-if="paymentBanner !== 'verifying'"
            type="button"
            class="pt-pay-banner-close"
            :aria-label="t('portal.payment.banner.dismiss')"
            @click="paymentBanner = null"
          >
            <i class="fa fa-times" aria-hidden="true"></i>
          </button>
        </div>

        <section class="pt-summary" aria-label="portal summary">
          <div class="pt-summary-card">
            <span class="pt-summary-label">{{ t('portal.summary.toPay') }}</span>
            <span class="pt-summary-value">{{ formatAmount(data.summary.total_outstanding, primaryCurrency) }}</span>
          </div>
          <div class="pt-summary-card" :class="{ 'pt-summary-card--overdue': data.summary.overdue_amount > 0 }">
            <span class="pt-summary-label">{{ t('portal.summary.overdue') }}</span>
            <span class="pt-summary-value">{{ formatAmount(data.summary.overdue_amount, primaryCurrency) }}</span>
          </div>
          <div class="pt-summary-card">
            <span class="pt-summary-label">{{ t('portal.summary.pendingInvoices') }}</span>
            <span class="pt-summary-value">{{ data.summary.unpaid_invoices_count }}</span>
          </div>
        </section>

        <section class="pt-docs">
          <div class="pt-tabs" role="tablist">
            <button
              type="button"
              role="tab"
              class="pt-tab"
              :class="{ 'pt-tab--active': tab === 'invoices' }"
              :aria-selected="tab === 'invoices'"
              @click="tab = 'invoices'"
            >
              {{ t('portal.tabs.invoices') }}
              <span class="pt-tab-count">{{ invoices.length }}</span>
            </button>
            <button
              type="button"
              role="tab"
              class="pt-tab"
              :class="{ 'pt-tab--active': tab === 'quotes' }"
              :aria-selected="tab === 'quotes'"
              @click="tab = 'quotes'"
            >
              {{ t('portal.tabs.quotes') }}
              <span class="pt-tab-count">{{ quotes.length }}</span>
            </button>
          </div>

          <!-- Invoices -->
          <div v-show="tab === 'invoices'" role="tabpanel">
            <div v-if="invoices.length === 0" class="pt-empty">
              <div class="pt-empty-icon">🧾</div>
              <p class="pt-empty-title">{{ t('portal.empty.noInvoicesTitle') }}</p>
              <p class="pt-empty-text">{{ t('portal.empty.noInvoicesText') }}</p>
            </div>
            <template v-else>
              <div v-if="data.summary.unpaid_invoices_count === 0" class="pt-caught-up">
                <span aria-hidden="true">🎉</span> {{ t('portal.empty.allCaughtUp') }}
              </div>

              <!-- Desktop table -->
              <div class="pt-table-wrap">
                <table class="pt-table">
                  <thead>
                    <tr>
                      <th>{{ t('portal.table.number') }}</th>
                      <th>{{ t('portal.table.issueDate') }}</th>
                      <th>{{ t('portal.table.dueDate') }}</th>
                      <th>{{ t('portal.table.amount') }}</th>
                      <th>{{ t('portal.table.status') }}</th>
                      <th class="pt-table-actions-head">{{ t('portal.table.actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="invoice in invoices" :key="invoice.uuid">
                      <td class="pt-table-number">{{ invoice.number }}</td>
                      <td>{{ formatDate(invoice.issue_date) }}</td>
                      <td>{{ formatDate(invoice.due_date) }}</td>
                      <td class="pt-table-amount">{{ formatAmount(invoice.total, invoice.currency) }}</td>
                      <td>
                        <PortalStatusBadge :status="invoice.displayStatus" :label="t('portal.status.' + invoice.displayStatus)" />
                        <span v-if="invoice.paid_at" class="pt-paid-on">{{ t('portal.payment.paidOn', { date: formatDate(invoice.paid_at) }) }}</span>
                      </td>
                      <td>
                        <PortalInvoiceActions
                          :invoice="invoice"
                          variant="row"
                          :download-key="downloadKey"
                          :pay-busy="payBusy"
                          @pay="openPayConfirm"
                          @download-pdf="downloadInvoicePdf"
                          @download-ubl="downloadInvoiceUbl"
                        />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Mobile cards -->
              <div class="pt-cards">
                <div v-for="invoice in invoices" :key="invoice.uuid" class="pt-doc-card">
                  <div class="pt-doc-card-top">
                    <span class="pt-doc-card-number">{{ invoice.number }}</span>
                    <PortalStatusBadge :status="invoice.displayStatus" :label="t('portal.status.' + invoice.displayStatus)" />
                  </div>
                  <div class="pt-doc-card-amount">{{ formatAmount(invoice.total, invoice.currency) }}</div>
                  <div class="pt-doc-card-dates">
                    <span>{{ t('portal.table.issueDate') }}: {{ formatDate(invoice.issue_date) }}</span>
                    <span>{{ t('portal.table.dueDate') }}: {{ formatDate(invoice.due_date) }}</span>
                    <span v-if="invoice.paid_at" class="pt-paid-on-card">{{ t('portal.payment.paidOn', { date: formatDate(invoice.paid_at) }) }}</span>
                  </div>
                  <PortalInvoiceActions
                    :invoice="invoice"
                    variant="card"
                    :download-key="downloadKey"
                    :pay-busy="payBusy"
                    @pay="openPayConfirm"
                    @download-pdf="downloadInvoicePdf"
                    @download-ubl="downloadInvoiceUbl"
                  />
                </div>
              </div>
            </template>
          </div>

          <!-- Quotes -->
          <div v-show="tab === 'quotes'" role="tabpanel">
            <div v-if="quotes.length === 0" class="pt-empty">
              <div class="pt-empty-icon">📄</div>
              <p class="pt-empty-title">{{ t('portal.empty.noQuotesTitle') }}</p>
              <p class="pt-empty-text">{{ t('portal.empty.noQuotesText') }}</p>
            </div>
            <template v-else>
              <div class="pt-table-wrap">
                <table class="pt-table">
                  <thead>
                    <tr>
                      <th>{{ t('portal.table.number') }}</th>
                      <th>{{ t('portal.table.date') }}</th>
                      <th>{{ t('portal.table.amount') }}</th>
                      <th>{{ t('portal.table.status') }}</th>
                      <th class="pt-table-actions-head">{{ t('portal.table.actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="quote in quotes" :key="quote.uuid">
                      <td class="pt-table-number">{{ quote.number }}</td>
                      <td>{{ formatDate(quote.date) }}</td>
                      <td class="pt-table-amount">{{ formatAmount(quote.total, quote.currency) }}</td>
                      <td>
                        <PortalStatusBadge :status="quote.displayStatus" :label="t('portal.status.' + quote.displayStatus)" />
                      </td>
                      <td>
                        <div class="pt-actions">
                          <button
                            v-if="quote.status === 'sent'"
                            type="button"
                            class="pt-action-btn pt-action-btn--accept"
                            :aria-label="t('portal.quoteAction.accept')"
                            :title="t('portal.quoteAction.accept')"
                            @click="openQuoteConfirm('accept', quote)"
                          >
                            <i class="fa fa-check"></i>
                          </button>
                          <button
                            v-if="quote.status === 'sent'"
                            type="button"
                            class="pt-action-btn pt-action-btn--reject"
                            :aria-label="t('portal.quoteAction.reject')"
                            :title="t('portal.quoteAction.reject')"
                            @click="openQuoteConfirm('reject', quote)"
                          >
                            <i class="fa fa-times"></i>
                          </button>
                          <button
                            type="button"
                            class="pt-action-btn"
                            :disabled="downloadKey === 'quote-pdf-' + quote.uuid"
                            :aria-label="t('portal.download.pdf')"
                            :title="t('portal.download.pdf')"
                            @click="downloadQuotePdf(quote)"
                          >
                            <i v-if="downloadKey === 'quote-pdf-' + quote.uuid" class="fa fa-spinner fa-spin"></i>
                            <i v-else class="fa fa-file-pdf"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="pt-cards">
                <div v-for="quote in quotes" :key="quote.uuid" class="pt-doc-card">
                  <div class="pt-doc-card-top">
                    <span class="pt-doc-card-number">{{ quote.number }}</span>
                    <PortalStatusBadge :status="quote.displayStatus" :label="t('portal.status.' + quote.displayStatus)" />
                  </div>
                  <div class="pt-doc-card-amount">{{ formatAmount(quote.total, quote.currency) }}</div>
                  <div class="pt-doc-card-dates">
                    <span>{{ t('portal.table.date') }}: {{ formatDate(quote.date) }}</span>
                  </div>
                  <div v-if="quote.status === 'sent'" class="pt-doc-card-quote-actions">
                    <button
                      type="button"
                      class="pt-quote-btn pt-quote-btn--accept"
                      @click="openQuoteConfirm('accept', quote)"
                    >
                      <i class="fa fa-check"></i>
                      {{ t('portal.quoteAction.accept') }}
                    </button>
                    <button
                      type="button"
                      class="pt-quote-btn pt-quote-btn--reject"
                      @click="openQuoteConfirm('reject', quote)"
                    >
                      {{ t('portal.quoteAction.reject') }}
                    </button>
                  </div>
                  <div class="pt-doc-card-actions">
                    <button
                      type="button"
                      class="pt-action-btn pt-action-btn--wide"
                      :disabled="downloadKey === 'quote-pdf-' + quote.uuid"
                      @click="downloadQuotePdf(quote)"
                    >
                      <i v-if="downloadKey === 'quote-pdf-' + quote.uuid" class="fa fa-spinner fa-spin"></i>
                      <i v-else class="fa fa-file-pdf"></i>
                      {{ t('portal.download.pdf') }}
                    </button>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </section>
      </main>

      <footer class="pt-footer">
        <p class="pt-footer-brand">{{ t('portal.poweredBy') }}</p>
        <p class="pt-footer-tagline">{{ t('portal.tagline') }}</p>
      </footer>

      <!-- Quote accept/reject confirmation - Step 4 -->
      <div v-if="confirmState" class="pt-modal-overlay" @click.self="closeConfirm">
        <div class="pt-modal" role="dialog" aria-modal="true">
          <template v-if="confirmState.phase !== 'success'">
            <h2 class="pt-modal-title">
              {{ confirmState.action === 'accept' ? t('portal.quoteAction.confirmAcceptTitle') : t('portal.quoteAction.confirmRejectTitle') }}
            </h2>
            <div class="pt-modal-quote">
              <span class="pt-modal-quote-number">{{ confirmState.quote.number }}</span>
              <span class="pt-modal-quote-amount">{{ formatAmount(confirmState.quote.total, confirmState.quote.currency) }}</span>
            </div>
            <div class="pt-modal-actions">
              <button
                type="button"
                class="pt-modal-btn pt-modal-btn--secondary"
                :disabled="confirmState.phase === 'submitting'"
                @click="closeConfirm"
              >
                {{ t('portal.quoteAction.cancel') }}
              </button>
              <button
                type="button"
                class="pt-modal-btn"
                :class="confirmState.action === 'accept' ? 'pt-modal-btn--accept' : 'pt-modal-btn--reject'"
                :disabled="confirmState.phase === 'submitting'"
                @click="submitQuoteAction"
              >
                <i v-if="confirmState.phase === 'submitting'" class="fa fa-spinner fa-spin"></i>
                {{ confirmState.action === 'accept' ? t('portal.quoteAction.accept') : t('portal.quoteAction.reject') }}
              </button>
            </div>
          </template>
          <template v-else>
            <div class="pt-modal-success-icon" aria-hidden="true">{{ confirmState.action === 'accept' ? '✅' : '📩' }}</div>
            <h2 class="pt-modal-title">
              {{ confirmState.action === 'accept' ? t('portal.quoteAction.acceptedTitle') : t('portal.quoteAction.rejectedTitle') }}
            </h2>
            <p class="pt-modal-success-text">
              {{ confirmState.action === 'accept' ? t('portal.quoteAction.acceptedText') : t('portal.quoteAction.rejectedText') }}
            </p>
            <div class="pt-modal-actions">
              <button type="button" class="pt-modal-btn pt-modal-btn--accept" @click="closeConfirm">
                {{ t('portal.quoteAction.close') }}
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- Pay now confirmation (Step 6B). Number/amount/currency are display
           only - the request carries none of them; the server decides. -->
      <div v-if="payState" class="pt-modal-overlay" @click.self="closePay">
        <div class="pt-modal" role="dialog" aria-modal="true" aria-labelledby="pt-pay-title">
          <h2 id="pt-pay-title" class="pt-modal-title">{{ t('portal.payment.confirmTitle') }}</h2>
          <div class="pt-modal-pay-summary">
            <span class="pt-modal-quote-number">{{ payState.invoice.number }}</span>
            <span class="pt-modal-pay-amount">{{ formatAmount(payState.invoice.total, payState.invoice.currency) }}</span>
          </div>
          <p class="pt-modal-pay-note">
            <i class="fa fa-lock" aria-hidden="true"></i>
            {{ payState.phase === 'redirecting' ? t('portal.payment.redirecting') : t('portal.payment.secureNote') }}
          </p>
          <div class="pt-modal-actions">
            <button
              type="button"
              class="pt-modal-btn pt-modal-btn--secondary"
              :disabled="payState.phase === 'redirecting'"
              @click="closePay"
            >
              {{ t('portal.payment.cancel') }}
            </button>
            <button
              type="button"
              class="pt-modal-btn pt-modal-btn--accept"
              :disabled="payState.phase === 'redirecting'"
              data-test="confirm-pay"
              @click="submitPayment"
            >
              <i v-if="payState.phase === 'redirecting'" class="fa fa-spinner fa-spin"></i>
              {{ t('portal.payment.payAmount', { amount: formatAmount(payState.invoice.total, payState.invoice.currency) }) }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
/**
 * Client Portal, Step 2 - standalone public page (no admin layout, see
 * resources/js/router/index.js's top-level 'client-portal' route). Reads
 * :token from the URL and fetches GET /api/portal/{token} (Step 1's
 * endpoint, moved under /api in Step 2 - see routes/tenant_api.php).
 *
 * No auth/account exists for a customer, and this page never assumes
 * one - a failed/expired token just shows a state, never a login
 * redirect (see the router guard's 'client-portal' bypass).
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { createToaster } from '@meforma/vue-toaster';
import { setLocale, visibleLocales } from '@/i18n';
import PortalStatusBadge from './PortalStatusBadge.vue';
import PortalInvoiceActions from './PortalInvoiceActions.vue';
import { accentCssVars } from './brandColor.mjs';
import {
  POLL_INTERVAL_MS,
  POLL_MAX_ATTEMPTS,
  readPaymentReturn,
  paidInvoiceUuids,
  paymentConfirmation,
  safeCheckoutUrl,
  paymentErrorKey,
  paymentHintKey,
} from './portalPayment.mjs';

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const toaster = createToaster();

const state = ref('loading'); // loading | ready | notfound | revoked | error
const data = ref(null);
const tab = ref('invoices');
const langOpen = ref(false);
const langMenuRef = ref(null);
// Which single download is currently in flight, e.g. 'invoice-pdf-<uuid>' -
// disables just that one button and swaps its icon for a spinner.
const downloadKey = ref(null);
// Quote accept/reject confirmation dialog (Step 4) - null when closed,
// otherwise { action: 'accept'|'reject', quote, phase: 'confirm'|'submitting'|'success' }.
const confirmState = ref(null);
// Pay now (Step 6B) - null when closed, otherwise
// { invoice, phase: 'confirm'|'redirecting' }. Display state only: the
// invoice's real status always comes from the portal API.
const payState = ref(null);
const payBusy = computed(() => payState.value?.phase === 'redirecting');
// Return-from-Stripe banner: null | 'verifying' | 'confirmed' | 'pending' | 'cancelled'
const paymentBanner = ref(null);
let pollTimer = null;
let unmounted = false;

async function load() {
  state.value = 'loading';
  try {
    const response = await axios.get('portal/' + encodeURIComponent(route.params.token));
    data.value = response.data;
    state.value = 'ready';
  } catch (error) {
    const status = error?.response?.status;
    if (status === 404) {
      state.value = 'notfound';
    } else if (status === 410) {
      state.value = 'revoked';
    } else {
      state.value = 'error';
    }
  }
}

function handleOutsideClick(event) {
  if (langOpen.value && langMenuRef.value && !langMenuRef.value.contains(event.target)) {
    langOpen.value = false;
  }
}

// ── Document downloads (Step 3) - GET .../portal/{token}/... with the
// token already in the URL from the route, exactly like the summary
// fetch above. The endpoint returns the file bytes directly (not a
// stored-file URL), so this follows the same axios
// responseType:'blob' + synthetic <a download> pattern already used by
// the admin's own UBL export button (invoices/index.vue) and
// ExportMenu.vue. A failed download never tries to read the error
// body (it may be a JSON message wrapped as a blob) - it always shows
// the same generic, translated toast; the real reason is only ever in
// the server log.
async function downloadDocument(path, key, fallbackFilename) {
  if (downloadKey.value) return; // one download at a time
  downloadKey.value = key;

  try {
    const response = await axios.get('portal/' + encodeURIComponent(route.params.token) + path, {
      responseType: 'blob',
    });

    const disposition = response.headers['content-disposition'] || '';
    const match = disposition.match(/filename="?([^"]+)"?/);
    const filename = match ? match[1] : fallbackFilename;

    const blob = new Blob([response.data]);
    const blobUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(blobUrl);
  } catch (error) {
    toaster.error(t('portal.download.error'));
  } finally {
    downloadKey.value = null;
  }
}

function downloadInvoicePdf(invoice) {
  downloadDocument(
    '/invoices/' + invoice.uuid + '/pdf',
    'invoice-pdf-' + invoice.uuid,
    invoice.number + '.pdf'
  );
}

function downloadInvoiceUbl(invoice) {
  downloadDocument(
    '/invoices/' + invoice.uuid + '/ubl',
    'invoice-ubl-' + invoice.uuid,
    invoice.number + '.xml'
  );
}

function downloadQuotePdf(quote) {
  downloadDocument(
    '/quotes/' + quote.uuid + '/pdf',
    'quote-pdf-' + quote.uuid,
    quote.number + '.pdf'
  );
}

// ── Quote accept/reject (Step 4) - POST .../quotes/{uuid}/accept|reject,
// authorized by the same portal token already in the URL. On success the
// matching quote already in `data.value.quotes` is patched in place
// (status + accepted_at/rejected_at from the response) so the page
// reflects the decision immediately, with no full page reload and no
// second GET /portal/{token} round-trip.

function openQuoteConfirm(action, quote) {
  confirmState.value = { action, quote, phase: 'confirm' };
}

function closeConfirm() {
  confirmState.value = null;
}

async function submitQuoteAction() {
  if (!confirmState.value || confirmState.value.phase === 'submitting') return;

  const { action, quote } = confirmState.value;
  confirmState.value = { ...confirmState.value, phase: 'submitting' };

  try {
    const response = await axios.post(
      'portal/' + encodeURIComponent(route.params.token) + '/quotes/' + quote.uuid + '/' + action
    );

    const target = (data.value?.quotes ?? []).find((q) => q.uuid === quote.uuid);
    if (target) {
      target.status = response.data.status;
      if (action === 'accept') target.accepted_at = response.data.accepted_at;
      if (action === 'reject') target.rejected_at = response.data.rejected_at;
    }

    confirmState.value = { ...confirmState.value, phase: 'success' };
  } catch (error) {
    confirmState.value = null;
    toaster.error(t('portal.quoteAction.error'));
    // 422 = already decided elsewhere (another tab/device) - pull the
    // real status in the background so the stale buttons disappear.
    if (error?.response?.status === 422) {
      refreshQuietly();
    }
  }
}

async function refreshQuietly() {
  try {
    const response = await axios.get('portal/' + encodeURIComponent(route.params.token));
    data.value = response.data;
    return true;
  } catch (e) {
    // Keep the current view - the next full load() will surface any error.
    return false;
  }
}

// ── Pay now (Step 6B) - POST .../invoices/{uuid}/payment/stripe with NO
// body: amount, currency and the seller's Stripe account are decided by
// the server (PortalInvoicePaymentService). The browser only follows the
// returned Stripe Checkout URL. ─────────────────────────────────────────

function openPayConfirm(invoice) {
  if (payBusy.value || !invoice?.payment?.payable) return;
  payState.value = { invoice, phase: 'confirm' };
}

function closePay() {
  if (payBusy.value) return;
  payState.value = null;
}

function rememberPaymentHint(invoiceUuid) {
  try {
    sessionStorage.setItem(paymentHintKey(route.params.token), invoiceUuid);
  } catch (e) {
    // Storage unavailable (private mode) - the return flow copes without it.
  }
}

function takePaymentHint() {
  try {
    const key = paymentHintKey(route.params.token);
    const value = sessionStorage.getItem(key);
    sessionStorage.removeItem(key);
    return value;
  } catch (e) {
    return null;
  }
}

async function submitPayment() {
  if (!payState.value || payBusy.value) return;

  const { invoice } = payState.value;
  payState.value = { invoice, phase: 'redirecting' };

  try {
    const response = await axios.post(
      'portal/' + encodeURIComponent(route.params.token) + '/invoices/' + invoice.uuid + '/payment/stripe'
    );
    const url = safeCheckoutUrl(response.data?.checkout_url);
    if (!url) throw new Error('invalid checkout url');

    rememberPaymentHint(invoice.uuid);
    // Keep the modal in its "redirecting" state while the browser leaves.
    window.location.assign(url);
  } catch (error) {
    payState.value = null;
    const reason = error?.response?.data?.reason;
    toaster.error(t(paymentErrorKey(reason)), { duration: 6000 });
    // The invoice may have changed server-side (paid elsewhere, cancelled) -
    // re-read it so the button reflects reality.
    if (reason === 'already_paid' || reason === 'not_payable' || reason === 'currency_unsupported' || reason === 'unavailable') {
      refreshQuietly();
    }
  }
}

// ── Return from Stripe (?payment=success|cancelled) ─────────────────────
// success_url is UX only: "success" shows a verification state and
// re-fetches the portal until the SERVER reports the invoice paid (set by
// the signed webhook), for at most POLL_MAX_ATTEMPTS tries.

function clearPaymentQuery() {
  const { payment, ...rest } = route.query;
  router.replace({ query: rest }).catch(() => {});
}

function stopPolling() {
  if (pollTimer) {
    clearTimeout(pollTimer);
    pollTimer = null;
  }
}

function handlePaymentReturn() {
  const outcome = readPaymentReturn(route.query);
  if (!outcome) return;

  const targetUuid = takePaymentHint();
  clearPaymentQuery();

  if (outcome === 'cancelled') {
    paymentBanner.value = 'cancelled';
    return;
  }

  // Invoices already paid when the customer came back never count as
  // "this payment" (matters only when there is no/unknown hint).
  const context = { targetUuid, paidBefore: paidInvoiceUuids(data.value?.invoices) };

  if (targetUuid && paymentConfirmation(data.value?.invoices, context) === 'confirmed') {
    paymentBanner.value = 'confirmed';
    return;
  }

  paymentBanner.value = 'verifying';
  let attempts = 0;

  const poll = async () => {
    attempts += 1;
    await refreshQuietly();

    if (paymentConfirmation(data.value?.invoices, context) === 'confirmed') {
      paymentBanner.value = 'confirmed';
      stopPolling();
      return;
    }

    if (attempts >= POLL_MAX_ATTEMPTS) {
      paymentBanner.value = 'pending';
      stopPolling();
      return;
    }

    if (!unmounted) pollTimer = setTimeout(poll, POLL_INTERVAL_MS);
  };

  pollTimer = setTimeout(poll, POLL_INTERVAL_MS);
}

// Browser "Back" from Stripe can restore this page from the back/forward
// cache with the Pay modal frozen on "Redirecting…" - reset it.
function handlePageShow(event) {
  if (event.persisted) payState.value = null;
}

onMounted(async () => {
  document.addEventListener('click', handleOutsideClick, true);
  window.addEventListener('pageshow', handlePageShow);
  await load();
  if (state.value === 'ready') {
    handlePaymentReturn();
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick, true);
  window.removeEventListener('pageshow', handlePageShow);
  unmounted = true;
  stopPolling();
});

// ── Derived document lists (status classification only - never re-
// computes an amount; the figures come from the backend as-is) ────────

const today = new Date().toISOString().slice(0, 10);

function resolveInvoiceStatus(invoice) {
  if (invoice.status === 'paid') return 'paid';
  if (invoice.status === 'cancelled') return 'cancelled';
  // 'issued' - same "past its due date" rule the backend summary uses
  // (InvoicePaymentsController) to decide overdue vs pending.
  if (invoice.due_date && invoice.due_date < today) return 'overdue';
  return 'pending';
}

function resolveQuoteStatus(quote) {
  if (quote.status === 'converted') return 'converted';
  if (quote.status === 'cancelled') return 'cancelled';
  if (quote.status === 'accepted') return 'accepted';
  if (quote.status === 'rejected') return 'rejected';
  return 'sent';
}

const invoices = computed(() =>
  (data.value?.invoices ?? []).map((invoice) => ({ ...invoice, displayStatus: resolveInvoiceStatus(invoice) }))
);
const quotes = computed(() =>
  (data.value?.quotes ?? []).map((quote) => ({ ...quote, displayStatus: resolveQuoteStatus(quote) }))
);

const primaryCurrency = computed(() => data.value?.invoices?.[0]?.currency ?? data.value?.quotes?.[0]?.currency ?? '');

// ── Brand color: accent only, and only when it is actually a valid hex
// color - never trusted/used verbatim in a way that could break the
// page's own readability. ───────────────────────────────────────────

// Step 6C: plus a readable text colour for anything drawn ON the accent
// (see brandColor.mjs) - white on dark brands, dark ink on light ones.
const accentStyle = computed(() => accentCssVars(data.value?.company?.brand_color));

function initials(name) {
  if (!name) return '?';
  return name.trim().split(/\s+/).slice(0, 2).map((word) => word[0]).join('').toUpperCase();
}

// ── Language switcher - reuses the app's existing i18n system as-is
// (setLocale()/visibleLocales from @/i18n), just with its own small
// dropdown UI since no visible language-picker component exists yet to
// copy from. ─────────────────────────────────────────────────────────

const FLAGS = { fr: '🇫🇷', ar: '🇲🇦', en: '🇬🇧', es: '🇪🇸' };
function flag(l) {
  return FLAGS[l] || '🌐';
}
function changeLocale(l) {
  setLocale(l);
  langOpen.value = false;
}

// ── Formatting - mirrors app/Services/CurrencyFormatter.php's own
// separators-by-locale rule exactly (fr: comma+space, en: dot+comma,
// es/ar: comma+dot) and main.js's $toCurrency symbol map (EUR/USD/GBP
// only - every other currency, including MAD, shows its plain ISO code
// rather than a guessed symbol - see that file's own comment on why).
// Not reused directly: this page has no authenticated store/tenant
// context to read $toCurrency's currency from, so the same convention
// is applied here against the currency the API response itself gives. ──

const CURRENCY_SYMBOLS = { EUR: '€', USD: '$', GBP: '£' };

function separatorsFor(loc) {
  if (loc === 'en') return ['.', ','];
  if (loc === 'fr') return [',', ' '];
  return [',', '.'];
}

function formatAmount(value, currencyCode) {
  const amount = Number(value ?? 0);
  const negative = amount < 0;
  const [decimalSep, thousandsSep] = separatorsFor(locale.value);
  const [intPart, decPart] = Math.abs(amount).toFixed(2).split('.');
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
  const symbol = CURRENCY_SYMBOLS[currencyCode] || currencyCode || '';

  return `${negative ? '-' : ''}${withThousands}${decimalSep}${decPart}${symbol ? ' ' + symbol : ''}`;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const [y, m, d] = dateStr.split('-');
  return y && m && d ? `${d}/${m}/${y}` : dateStr;
}
</script>

<style scoped>
.pt-page {
  --pt-accent: #E4287C;
  /* Text on the accent - overridden with a contrast-checked value when the
     company sets its own brand colour (accentCssVars()). */
  --pt-on-accent: #FFFFFF;
  --pt-bg: #F7F7FB;
  --pt-surface: #FFFFFF;
  --pt-border: #E9E9F1;
  --pt-text: #1A1B25;
  --pt-text-muted: #6B6D80;

  min-height: 100vh;
  background: var(--pt-bg);
  color: var(--pt-text);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  display: flex;
  flex-direction: column;
}

/* ── Header ── */
.pt-header {
  background: var(--pt-surface);
  border-bottom: 1px solid var(--pt-border);
  position: sticky;
  top: 0;
  z-index: 10;
}
.pt-header-inner {
  max-width: 880px;
  margin: 0 auto;
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.pt-brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
.pt-logo { height: 32px; width: auto; max-width: 140px; object-fit: contain; }
.pt-logo-fallback {
  height: 32px;
  width: 32px;
  border-radius: 9px;
  background: var(--pt-accent);
  color: var(--pt-on-accent);
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pt-company-name {
  font-weight: 700;
  font-size: 15px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pt-lang { position: relative; }
.pt-lang-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  min-height: 40px;
  border: 1px solid var(--pt-border);
  border-radius: 10px;
  background: var(--pt-surface);
  font-size: 13px;
  font-weight: 600;
  color: var(--pt-text);
  cursor: pointer;
}
.pt-lang-code { letter-spacing: .02em; }
.pt-lang-menu {
  position: absolute;
  inset-inline-end: 0;
  top: calc(100% + 6px);
  background: var(--pt-surface);
  border: 1px solid var(--pt-border);
  border-radius: 10px;
  box-shadow: 0 10px 28px rgba(20, 20, 40, 0.12);
  padding: 6px;
  min-width: 160px;
  z-index: 20;
}
.pt-lang-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 10px;
  min-height: 40px;
  border: none;
  background: none;
  border-radius: 7px;
  font-size: 13.5px;
  font-weight: 500;
  text-align: start;
  color: var(--pt-text);
  cursor: pointer;
}
.pt-lang-item:hover { background: var(--pt-bg); }
.pt-lang-item--active { color: var(--pt-accent); font-weight: 700; }

/* ── Main ── */
.pt-main { flex: 1; max-width: 880px; margin: 0 auto; padding: 28px 18px 40px; width: 100%; box-sizing: border-box; }

.pt-welcome { margin-bottom: 22px; }
.pt-greeting { font-size: 22px; font-weight: 800; margin: 0 0 6px; }
.pt-subline { font-size: 14.5px; color: var(--pt-text-muted); margin: 0; line-height: 1.5; }

/* ── Summary cards ── */
.pt-summary {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  margin-bottom: 28px;
}
@media (min-width: 640px) {
  .pt-summary { grid-template-columns: repeat(3, 1fr); }
}
.pt-summary-card {
  background: var(--pt-surface);
  border: 1px solid var(--pt-border);
  border-radius: 14px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.pt-summary-label { font-size: 12.5px; font-weight: 600; color: var(--pt-text-muted); text-transform: uppercase; letter-spacing: .03em; }
.pt-summary-value { font-size: 21px; font-weight: 800; }
.pt-summary-card--overdue {
  border-color: #FCA5A5;
  background: #FEF2F2;
}
.pt-summary-card--overdue .pt-summary-value { color: #B91C1C; }

/* ── Tabs ── */
.pt-tabs { display: flex; gap: 6px; border-bottom: 1px solid var(--pt-border); margin-bottom: 16px; }
.pt-tab {
  appearance: none;
  border: none;
  background: none;
  padding: 10px 4px;
  margin-inline-end: 20px;
  min-height: 44px;
  font-size: 14.5px;
  font-weight: 700;
  color: var(--pt-text-muted);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.pt-tab--active { color: var(--pt-accent); border-bottom-color: var(--pt-accent); }
.pt-tab-count {
  background: var(--pt-bg);
  color: var(--pt-text-muted);
  font-size: 11.5px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 100px;
}
.pt-tab--active .pt-tab-count { background: var(--pt-accent); color: var(--pt-on-accent); }

/* ── "All caught up" banner ── */
.pt-caught-up {
  background: #ECFDF5;
  color: #15803D;
  border: 1px solid #A7F3D0;
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 16px;
}

/* ── Empty states ── */
.pt-empty {
  text-align: center;
  padding: 48px 20px;
  background: var(--pt-surface);
  border: 1px solid var(--pt-border);
  border-radius: 14px;
}
.pt-empty-icon { font-size: 32px; margin-bottom: 10px; }
.pt-empty-title { font-size: 15.5px; font-weight: 700; margin: 0 0 4px; }
.pt-empty-text { font-size: 13.5px; color: var(--pt-text-muted); margin: 0; }

/* ── Desktop table (hidden on mobile) ── */
.pt-table-wrap { display: none; }
.pt-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--pt-surface);
  border: 1px solid var(--pt-border);
  border-radius: 14px;
  overflow: hidden;
}
.pt-table th {
  text-align: start;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--pt-text-muted);
  padding: 12px 16px;
  border-bottom: 1px solid var(--pt-border);
}
.pt-table td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid var(--pt-border); }
.pt-table tr:last-child td { border-bottom: none; }
.pt-table-number { font-weight: 700; }
.pt-table-amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.pt-table-actions-head { width: 1%; white-space: nowrap; }

/* ── Document actions (download PDF/UBL) ── */
.pt-actions { display: flex; align-items: center; gap: 6px; }
.pt-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 40px;
  min-height: 40px;
  padding: 0 10px;
  border: 1px solid var(--pt-border);
  border-radius: 9px;
  background: var(--pt-surface);
  color: var(--pt-text-muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s ease, color .15s ease, border-color .15s ease;
}
.pt-action-btn:hover:not(:disabled) { background: var(--pt-bg); color: var(--pt-text); border-color: var(--pt-accent); }
.pt-action-btn:disabled { opacity: .6; cursor: not-allowed; }
.pt-action-btn--wide { flex: 1; min-height: 44px; }
.pt-doc-card-actions { display: flex; gap: 8px; margin-top: 12px; }

/* ── Mobile document cards ── */
.pt-cards { display: flex; flex-direction: column; gap: 12px; }
.pt-doc-card {
  background: var(--pt-surface);
  border: 1px solid var(--pt-border);
  border-radius: 14px;
  padding: 16px;
}
.pt-doc-card-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.pt-doc-card-number { font-weight: 700; font-size: 14.5px; }
.pt-doc-card-amount { font-size: 19px; font-weight: 800; margin-bottom: 8px; font-variant-numeric: tabular-nums; }
.pt-doc-card-dates { display: flex; flex-direction: column; gap: 3px; font-size: 12.5px; color: var(--pt-text-muted); }

@media (min-width: 768px) {
  .pt-table-wrap { display: block; }
  .pt-cards { display: none; }
}

/* ── Footer ── */
.pt-footer {
  text-align: center;
  padding: 24px 18px 32px;
  border-top: 1px solid var(--pt-border);
}
.pt-footer-brand { font-size: 13px; font-weight: 700; color: var(--pt-text-muted); margin: 0 0 2px; }
.pt-footer-tagline { font-size: 12px; color: var(--pt-text-muted); opacity: .8; margin: 0; }

/* ── Loading skeleton ── */
.pt-skeleton { min-height: 100vh; }
.pt-skel { background: linear-gradient(90deg, #EEEFF4 25%, #F6F6FA 37%, #EEEFF4 63%); background-size: 400% 100%; animation: pt-shimmer 1.4s ease infinite; border-radius: 12px; }
.pt-skel-header { height: 60px; border-radius: 0; }
.pt-skel-main { max-width: 880px; margin: 0 auto; padding: 28px 18px; display: flex; flex-direction: column; gap: 14px; }
.pt-skel-line { height: 16px; }
.pt-skel-cards { display: grid; grid-template-columns: 1fr; gap: 12px; margin: 10px 0; }
@media (min-width: 640px) { .pt-skel-cards { grid-template-columns: repeat(3, 1fr); } }
.pt-skel-card { height: 76px; }
@keyframes pt-shimmer {
  0% { background-position: 100% 50%; }
  100% { background-position: 0 50%; }
}

/* ── Error / not-found / revoked states ── */
.pt-state { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.pt-state-card { max-width: 420px; text-align: center; background: var(--pt-surface); border: 1px solid var(--pt-border); border-radius: 16px; padding: 36px 28px; }
.pt-state-icon { font-size: 38px; margin-bottom: 14px; }
.pt-state-card h1 { font-size: 18px; font-weight: 800; margin: 0 0 8px; }
.pt-state-card p { font-size: 14px; color: var(--pt-text-muted); margin: 0; line-height: 1.6; }
.pt-retry-btn {
  margin-top: 18px;
  min-height: 44px;
  padding: 0 22px;
  border: none;
  border-radius: 10px;
  background: var(--pt-accent);
  color: var(--pt-on-accent);
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
}

/* ── Quote accept/reject (Step 4) ── */
.pt-action-btn--accept:not(:disabled) { color: #15803D; border-color: #A7F3D0; }
.pt-action-btn--accept:hover:not(:disabled) { background: #ECFDF5; color: #15803D; border-color: #15803D; }
.pt-action-btn--reject:not(:disabled) { color: #B91C1C; border-color: #FCA5A5; }
.pt-action-btn--reject:hover:not(:disabled) { background: #FEF2F2; color: #B91C1C; border-color: #B91C1C; }

.pt-doc-card-quote-actions { display: flex; gap: 8px; margin-top: 12px; }
.pt-quote-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex: 1;
  min-height: 46px;
  padding: 0 14px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  border: 1.5px solid transparent;
}
.pt-quote-btn--accept { background: var(--pt-accent); color: var(--pt-on-accent); }
.pt-quote-btn--reject { background: var(--pt-surface); color: var(--pt-text-muted); border-color: var(--pt-border); }
.pt-quote-btn--reject:hover { color: var(--pt-text); border-color: var(--pt-text-muted); }

/* ── Confirmation modal (Step 4) ── */
.pt-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(20, 20, 30, 0.5);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0;
  z-index: 50;
}
@media (min-width: 640px) {
  .pt-modal-overlay { align-items: center; padding: 20px; }
}
.pt-modal {
  width: 100%;
  max-width: 420px;
  background: var(--pt-surface);
  border-radius: 18px 18px 0 0;
  padding: 26px 22px 22px;
  text-align: center;
}
@media (min-width: 640px) {
  .pt-modal { border-radius: 18px; }
}
.pt-modal-title { font-size: 17px; font-weight: 800; margin: 0 0 16px; }
.pt-modal-quote {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--pt-bg);
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 20px;
}
.pt-modal-quote-number { font-weight: 700; font-size: 14.5px; }
.pt-modal-quote-amount { font-weight: 800; font-size: 16px; font-variant-numeric: tabular-nums; }
.pt-modal-actions { display: flex; gap: 10px; }
.pt-modal-btn {
  flex: 1;
  min-height: 46px;
  padding: 0 16px;
  border: 1.5px solid transparent;
  border-radius: 11px;
  font-size: 14.5px;
  font-weight: 700;
  cursor: pointer;
}
.pt-modal-btn:disabled { opacity: .65; cursor: not-allowed; }
.pt-modal-btn--secondary { background: var(--pt-surface); color: var(--pt-text-muted); border-color: var(--pt-border); }
.pt-modal-btn--accept { background: var(--pt-accent); color: var(--pt-on-accent); }
.pt-modal-btn--reject { background: #B91C1C; color: #fff; }
.pt-modal-success-icon { font-size: 36px; margin-bottom: 10px; }
.pt-modal-success-text { font-size: 14px; color: var(--pt-text-muted); margin: 0 0 20px; line-height: 1.55; }

/* ── Pay now (Step 6B) ── */
.pt-paid-on { display: block; margin-top: 4px; font-size: 12px; color: var(--pt-text-muted); }
.pt-paid-on-card { color: #15803D; font-weight: 600; }

.pt-modal-pay-summary {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background: var(--pt-bg);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 14px;
}
.pt-modal-pay-amount {
  font-size: 26px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
  overflow-wrap: anywhere;
}
.pt-modal-pay-note {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--pt-text-muted);
  margin: 0 0 18px;
  line-height: 1.5;
}
/* Bottom sheet on phones: clear the home indicator, never taller than the screen */
.pt-modal { max-height: 100dvh; overflow-y: auto; padding-bottom: calc(22px + env(safe-area-inset-bottom, 0px)); }
@media (max-width: 380px) {
  .pt-modal-actions { flex-direction: column-reverse; }
}

/* Return-from-Stripe banner */
.pt-pay-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 18px;
  border-radius: 14px;
  border: 1px solid;
}
.pt-pay-banner-icon { font-size: 20px; line-height: 1.2; flex-shrink: 0; }
.pt-pay-banner-body { flex: 1; min-width: 0; }
.pt-pay-banner-title { margin: 0 0 2px; font-size: 14.5px; font-weight: 800; }
.pt-pay-banner-text { margin: 0; font-size: 13.5px; line-height: 1.5; }
.pt-pay-banner-close {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  margin: -6px -6px -6px 0;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: inherit;
  opacity: .7;
  cursor: pointer;
}
.pt-pay-banner-close:hover { opacity: 1; }
.pt-pay-banner--verifying { background: #EFF6FF; border-color: #BFDBFE; color: #1E3A8A; }
.pt-pay-banner--confirmed { background: #ECFDF5; border-color: #A7F3D0; color: #14532D; }
.pt-pay-banner--pending   { background: #FFFBEB; border-color: #FDE68A; color: #78350F; }
.pt-pay-banner--cancelled { background: var(--pt-surface); border-color: var(--pt-border); color: var(--pt-text); }
:global(html[dir="rtl"]) .pt-pay-banner-close { margin: -6px 0 -6px -6px; }

/* ── RTL (Arabic) ── */
:global(html[dir="rtl"]) .pt-page { direction: rtl; }
</style>
