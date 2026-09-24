<template>
  <Teleport to="body">
    <div class="sim-overlay" @click.self="$emit('close')">
      <div
        class="sim-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sim-title"
        ref="modalEl"
        tabindex="-1"
        @keydown.esc.prevent="$emit('close')"
      >

        <!-- Header -->
        <div class="sim-header">
          <h3 class="sim-title" id="sim-title">
            <i class="fa fa-paper-plane me-2" style="color:var(--brand-primary)"></i>{{ isQuote ? $t('sendModal.titleQuote') : $t('sendModal.titleInvoice') }}
          </h3>
          <button class="sim-close" @click="$emit('close')" :aria-label="$t('common.close')">&times;</button>
        </div>

        <!-- Body -->
        <div class="sim-body">

          <!-- To field -->
          <div class="sim-field">
            <label class="sim-label">{{ $t('sendModal.recipient') }}</label>
            <div v-if="customerEmail" class="sim-email-display">
              <i class="fa fa-envelope sim-email-icon"></i>
              <span>{{ customerEmail }}</span>
            </div>
            <div v-else class="sim-no-email">
              <i class="fa fa-exclamation-triangle me-2"></i>
              {{ $t('sendModal.noEmail') }}
            </div>
          </div>

          <!-- Message field -->
          <div class="sim-field">
            <label class="sim-label">{{ $t('sendModal.message') }}</label>
            <textarea
              class="sim-textarea"
              v-model="message"
              rows="9"
              :disabled="!customerEmail"
              :placeholder="$t('sendModal.customMsg')"
            ></textarea>
            <p class="sim-hint">
              <i class="fa fa-paperclip me-1"></i>{{ isQuote ? $t('sendModal.attachedQuote') : $t('sendModal.attachedInvoice') }}
            </p>
          </div>

        </div>

        <!-- Footer -->
        <div class="sim-footer">
          <button class="sim-btn sim-btn-cancel" @click="$emit('close')" :disabled="sending">
            {{ $t('common.cancel') }}
          </button>
          <button
            class="sim-btn sim-btn-send"
            @click="handleSend"
            :disabled="!customerEmail || sending"
          >
            <i v-if="sending" class="fa fa-spinner fa-spin me-1"></i>
            <i v-else class="fa fa-paper-plane me-1"></i>
            {{ $t('sendModal.send') }}
          </button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
  customerEmail: { type: String,  default: '' },
  customerName:  { type: String,  default: '' },
  invoiceRef:    { type: String,  default: '' },
  companyName:   { type: String,  default: '' },
  sending:       { type: Boolean, default: false },
  isQuote:       { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'send']);

const modalEl = ref(null);

const buildDefault = () => {
  const salut   = props.customerName
    ? t('sendModal.greetingName', { name: props.customerName })
    : t('sendModal.greeting');
  const docWord = t(props.isQuote ? 'sendModal.docWordQuote' : 'sendModal.docWordInvoice');
  const ref     = props.invoiceRef
    ? t('sendModal.refPhrase', { docWord, ref: props.invoiceRef })
    : t('sendModal.refPhraseNoRef', { docWord });
  const sign    = props.companyName
    ? t('sendModal.signatureWithName', { name: props.companyName })
    : t('sendModal.signature');
  return `${salut}\n\n${t('sendModal.bodyAttach', { ref })}\n\n${t('sendModal.bodyQuestion')}\n\n${sign}`;
};

const message = ref(buildDefault());

onMounted(() => {
  modalEl.value?.focus();
});

function handleSend() {
  if (!props.customerEmail || props.sending) return;
  emit('send', message.value);
}
</script>

<style scoped>
.sim-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 1050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.sim-modal {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
  width: 100%;
  max-width: 540px;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  outline: none;
}

.sim-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f0f0f0;
  flex-shrink: 0;
}

.sim-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #1a1a1a;
}

.sim-close {
  background: none;
  border: none;
  font-size: 22px;
  color: #9ca3af;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  transition: color 0.15s;
}
.sim-close:hover { color: #374151; }

.sim-body {
  padding: 20px 24px;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sim-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sim-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.sim-email-display {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f8f9fa;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.sim-email-icon {
  color: var(--brand-primary);
  font-size: 13px;
}

.sim-no-email {
  background: #fff9f0;
  border: 1px solid #fed7aa;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #92400e;
}

.sim-textarea {
  width: 100%;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px 14px;
  font-size: 14px;
  font-family: inherit;
  color: #374151;
  line-height: 1.65;
  resize: vertical;
  transition: border-color 0.15s, box-shadow 0.15s;
  min-height: 180px;
  box-sizing: border-box;
}
.sim-textarea:focus {
  outline: none;
  border-color: var(--brand-primary);
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.08);
}
.sim-textarea:disabled {
  background: #f9fafb;
  color: #9ca3af;
  cursor: not-allowed;
}

.sim-hint {
  margin: 0;
  font-size: 12px;
  color: #9ca3af;
}

.sim-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid #f0f0f0;
  flex-shrink: 0;
}

.sim-btn {
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
.sim-btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

.sim-btn-cancel {
  background: #fff;
  border-color: #e5e7eb;
  color: #374151;
}
.sim-btn-cancel:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #d1d5db;
  color: #111827;
  transform: translateY(-1px);
}

.sim-btn-send {
  background: var(--brand-primary);
  color: #fff;
  border-color: var(--brand-primary);
}
.sim-btn-send:hover:not(:disabled) {
  background: var(--brand-primary-hover);
  border-color: var(--brand-primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
}

/* ── Dark mode - the app's real toggle (.dark-mode class on
   #page-container, see BaseLayout.vue), additive overrides only. ──── */
:global(.dark-mode) .sim-modal { background: var(--dark-surface); }
:global(.dark-mode) .sim-header,
:global(.dark-mode) .sim-footer { border-color: var(--dark-border); }
:global(.dark-mode) .sim-title { color: var(--dark-text); }
:global(.dark-mode) .sim-close { color: var(--dark-text-disabled); }
:global(.dark-mode) .sim-close:hover { color: var(--dark-text-secondary); }
:global(.dark-mode) .sim-label,
:global(.dark-mode) .sim-hint { color: var(--dark-text-muted); }

:global(.dark-mode) .sim-email-display {
  background: #0f172a;
  border-color: #334155;
  color: #e2e8f0;
}

:global(.dark-mode) .sim-no-email {
  background: rgba(245, 158, 11, 0.1);
  border-color: rgba(245, 158, 11, 0.3);
  color: #fbbf24;
}

:global(.dark-mode) .sim-textarea {
  background: #0f172a;
  border-color: #334155;
  color: #e2e8f0;
}
:global(.dark-mode) .sim-textarea:disabled {
  background: #1e293b;
  color: #64748b;
}

:global(.dark-mode) .sim-btn-cancel {
  background: #1e293b;
  border-color: #334155;
  color: #94a3b8;
}
:global(.dark-mode) .sim-btn-cancel:hover:not(:disabled) {
  background: #0f172a;
  border-color: #475569;
  color: #e2e8f0;
}
</style>
