<template>
  <!-- Rendered in place (inside #page-container) so it picks up .dark-mode. -->
  <div class="uc-overlay" @mousedown.self="$emit('stay')">
    <div class="uc-modal" role="alertdialog" aria-modal="true" :aria-labelledby="titleId" :aria-describedby="textId"
      @keydown.esc="$emit('stay')">
      <div class="uc-head">
        <span class="uc-icon" aria-hidden="true"><i class="fa fa-exclamation-triangle"></i></span>
        <h2 :id="titleId" class="uc-title">{{ $t('common.unsavedChanges.title') }}</h2>
      </div>
      <p :id="textId" class="uc-text">{{ $t('common.unsavedChanges.text') }}</p>
      <div class="uc-actions">
        <button type="button" class="uc-btn uc-btn--ghost" data-test="unsaved-leave" @click="$emit('leave')">
          {{ $t('common.unsavedChanges.leave') }}
        </button>
        <button ref="stayBtn" type="button" class="uc-btn uc-btn--primary" data-test="unsaved-stay" @click="$emit('stay')">
          {{ $t('common.unsavedChanges.stay') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * "Modifications non enregistrées" - shown by useUnsavedChanges() when an
 * in-app navigation would discard an unsaved invoice/quote. Keeping editing
 * is the focused, primary (safe) choice.
 */
import { ref, onMounted } from 'vue';

defineEmits(['stay', 'leave']);

const uid = Math.random().toString(36).slice(2, 8);
const titleId = 'uc-title-' + uid;
const textId = 'uc-text-' + uid;
const stayBtn = ref(null);

onMounted(() => stayBtn.value?.focus?.());
</script>

<style scoped>
.uc-overlay {
  --uc-accent: var(--brand-primary);
  --uc-bg:     #ffffff;
  --uc-subtle: #f8fafc;
  --uc-border: #e2e8f0;
  --uc-text:   #0f172a;
  --uc-body:   #374151;

  position: fixed;
  inset: 0;
  z-index: 1070;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.45);
}
.dark-mode .uc-overlay {
  --uc-bg:     var(--dark-surface);
  --uc-subtle: var(--dark-surface-elevated);
  --uc-border: var(--dark-border-subtle);
  --uc-text:   var(--dark-text);
  --uc-body:   var(--dark-text-secondary);
  background: rgba(0, 0, 0, 0.6);
}
.uc-modal {
  width: 100%;
  max-width: 420px;
  background: var(--uc-bg);
  color: var(--uc-text);
  border: 1px solid var(--uc-border);
  border-top: 3px solid var(--uc-accent);
  border-radius: 14px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
  padding: 20px 22px;
}
.uc-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.uc-icon {
  width: 32px; height: 32px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  border-radius: 9px;
  background: rgba(224, 123, 0, 0.12);
  color: #b45309;
  font-size: 0.85rem;
}
.uc-title { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--uc-text); }
.uc-text { margin: 0; font-size: 0.875rem; line-height: 1.55; color: var(--uc-body); }
.uc-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
.uc-btn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 9px 16px;
  border-radius: 8px;
  font-size: 0.875rem; font-weight: 600;
  border: 1.5px solid transparent;
  cursor: pointer;
  line-height: 1;
  white-space: nowrap;
}
.uc-btn--primary { background: var(--uc-accent); border-color: var(--uc-accent); color: #fff; }
.uc-btn--primary:hover { background: var(--brand-primary-hover); border-color: var(--brand-primary-hover); }
.uc-btn--ghost { background: transparent; border-color: var(--uc-border); color: var(--uc-body); }
.uc-btn--ghost:hover { background: var(--uc-subtle); }
.uc-btn:focus-visible { outline: 2px solid var(--uc-accent); outline-offset: 2px; }

@media (max-width: 480px) {
  .uc-overlay { align-items: flex-end; padding: 0; }
  .uc-modal { max-width: none; border-radius: 16px 16px 0 0; padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px)); }
  .uc-actions { flex-direction: column-reverse; }
  .uc-btn { width: 100%; }
}
</style>
