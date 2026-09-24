<template>
  <Teleport to="body">
    <div v-if="show" class="bdm-overlay" @click.self="$emit('cancel')">
      <div class="bdm-modal" role="dialog" aria-modal="true">
        <div class="bdm-header">
          <h3 class="bdm-title">
            <i class="fa fa-exclamation-triangle me-2" style="color:#ef4444"></i>
            {{ t('common.deleteSelected') }}
          </h3>
          <button class="bdm-close" @click="$emit('cancel')" :disabled="loading">&times;</button>
        </div>
        <div class="bdm-body">
          <p class="bdm-message" v-html="t('common.bulkDeleteConfirm', { count })"></p>
        </div>
        <div class="bdm-footer">
          <button class="bdm-btn bdm-btn-cancel" @click="$emit('cancel')" :disabled="loading">
            {{ t('common.cancel') }}
          </button>
          <button class="bdm-btn bdm-btn-confirm" @click="$emit('confirm')" :disabled="loading">
            <i v-if="loading" class="fa fa-spinner fa-spin me-1"></i>
            <i v-else class="fa fa-trash me-1"></i>
            {{ t('common.delete') }} ({{ count }})
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
defineProps({
  show:    { type: Boolean, default: false },
  count:   { type: Number,  default: 0     },
  loading: { type: Boolean, default: false },
});
defineEmits(['confirm', 'cancel']);
</script>

<style scoped>
.bdm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 1060;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.bdm-modal {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.18);
  width: 100%;
  max-width: 440px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.bdm-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f0f0f0;
}
.bdm-title { margin: 0; font-size: 16px; font-weight: 700; color: #1a1a1a; }
.bdm-close {
  background: none;
  border: none;
  font-size: 22px;
  color: #9ca3af;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  transition: color 0.15s;
}
.bdm-close:hover:not(:disabled) { color: #374151; }
.bdm-close:disabled { opacity: 0.4; cursor: not-allowed; }
.bdm-body  { padding: 20px 24px; }
.bdm-message { font-size: 14px; color: #374151; margin: 0; line-height: 1.6; }
.bdm-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid #f0f0f0;
}
.bdm-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.15s;
}
.bdm-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
.bdm-btn-cancel { background: #f3f4f6; color: #374151; }
.bdm-btn-cancel:hover:not(:disabled) { background: #e5e7eb; }
.bdm-btn-confirm {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: #fff;
}
.bdm-btn-confirm:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(239,68,68,0.3);
}

/* Dark mode - the app's real toggle (.dark-mode class on #page-container),
   not prefers-color-scheme/data-theme which don't reflect the in-app
   toggle state. */
:global(.dark-mode) .bdm-modal   { background: var(--dark-surface-elevated); }
:global(.dark-mode) .bdm-header  { border-color: var(--dark-border); }
:global(.dark-mode) .bdm-title   { color: var(--dark-text); }
:global(.dark-mode) .bdm-close   { color: var(--dark-text-disabled); }
:global(.dark-mode) .bdm-close:hover:not(:disabled) { color: var(--dark-text-muted); }
:global(.dark-mode) .bdm-message { color: var(--dark-text-secondary); }
:global(.dark-mode) .bdm-footer  { border-color: var(--dark-border); }
:global(.dark-mode) .bdm-btn-cancel { background: var(--dark-border); color: var(--dark-text-muted); }
:global(.dark-mode) .bdm-btn-cancel:hover:not(:disabled) { background: var(--dark-border); }
</style>
