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

/* Dark mode */
@media (prefers-color-scheme: dark) {
  .bdm-modal   { background: #1e2235; }
  .bdm-header  { border-color: #2d3250; }
  .bdm-title   { color: #f1f5f9; }
  .bdm-close   { color: #6b7280; }
  .bdm-close:hover:not(:disabled) { color: #9ca3af; }
  .bdm-message { color: #cbd5e1; }
  .bdm-footer  { border-color: #2d3250; }
  .bdm-btn-cancel { background: #2d3250; color: #94a3b8; }
  .bdm-btn-cancel:hover:not(:disabled) { background: #374168; }
}
:root[data-theme="dark"] .bdm-modal   { background: #1e2235; }
:root[data-theme="dark"] .bdm-header  { border-color: #2d3250; }
:root[data-theme="dark"] .bdm-title   { color: #f1f5f9; }
:root[data-theme="dark"] .bdm-message { color: #cbd5e1; }
:root[data-theme="dark"] .bdm-footer  { border-color: #2d3250; }
:root[data-theme="dark"] .bdm-btn-cancel { background: #2d3250; color: #94a3b8; }
:root[data-theme="dark"] .bdm-btn-cancel:hover:not(:disabled) { background: #374168; }
</style>
