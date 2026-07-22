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
            <i class="fa fa-paper-plane me-2" style="color:#E91E63"></i>{{ isQuote ? 'Envoyer le devis' : 'Envoyer la facture' }}
          </h3>
          <button class="sim-close" @click="$emit('close')" aria-label="Fermer">&times;</button>
        </div>

        <!-- Body -->
        <div class="sim-body">

          <!-- To field -->
          <div class="sim-field">
            <label class="sim-label">Destinataire</label>
            <div v-if="customerEmail" class="sim-email-display">
              <i class="fa fa-envelope sim-email-icon"></i>
              <span>{{ customerEmail }}</span>
            </div>
            <div v-else class="sim-no-email">
              <i class="fa fa-exclamation-triangle me-2"></i>
              Ce client n'a pas d'adresse e-mail. Ajoutez-en une sur sa fiche avant d'envoyer.
            </div>
          </div>

          <!-- Message field -->
          <div class="sim-field">
            <label class="sim-label">Message</label>
            <textarea
              class="sim-textarea"
              v-model="message"
              rows="9"
              :disabled="!customerEmail"
              placeholder="Votre message personnalisé..."
            ></textarea>
            <p class="sim-hint">
              <i class="fa fa-paperclip me-1"></i>{{ isQuote ? 'Le devis PDF sera joint automatiquement.' : 'La facture PDF sera jointe automatiquement.' }}
            </p>
          </div>

        </div>

        <!-- Footer -->
        <div class="sim-footer">
          <button class="sim-btn sim-btn-cancel" @click="$emit('close')" :disabled="sending">
            Annuler
          </button>
          <button
            class="sim-btn sim-btn-send"
            @click="handleSend"
            :disabled="!customerEmail || sending"
          >
            <i v-if="sending" class="fa fa-spinner fa-spin me-1"></i>
            <i v-else class="fa fa-paper-plane me-1"></i>
            Envoyer
          </button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue';

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
  const salut   = props.customerName ? `Bonjour ${props.customerName},` : 'Bonjour,';
  const docWord = props.isQuote ? 'devis' : 'facture';
  const ref     = props.invoiceRef ? `votre ${docWord} ${props.invoiceRef}` : `votre ${docWord}`;
  const sign    = props.companyName  ? `Cordialement,\n${props.companyName}` : 'Cordialement,';
  return `${salut}\n\nVeuillez trouver ci-joint ${ref}.\n\nPour toute question, n'hésitez pas à nous contacter.\n\n${sign}`;
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
  color: #E91E63;
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
  border-color: #E91E63;
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
  background: #E91E63;
  color: #fff;
  border-color: #E91E63;
}
.sim-btn-send:hover:not(:disabled) {
  background: #c2185b;
  border-color: #c2185b;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
}
</style>
