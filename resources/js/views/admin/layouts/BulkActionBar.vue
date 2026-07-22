<template>
  <Transition name="bab">
    <div v-if="count > 0" class="bab-bar" role="status" aria-live="polite">
      <span class="bab-label">
        <i class="fa fa-check-square me-2"></i>
        <strong>{{ count }}</strong> {{ t('common.selected') }}
      </span>
      <div class="bab-actions">
        <button class="bab-btn bab-btn-clear" :disabled="loading" @click="$emit('clear')">
          <i class="fa fa-times me-1"></i>{{ t('common.clearSelection') }}
        </button>
        <button class="bab-btn bab-btn-delete" :disabled="loading" @click="$emit('delete')">
          <i v-if="loading" class="fa fa-spinner fa-spin me-1"></i>
          <i v-else class="fa fa-trash me-1"></i>{{ t('common.deleteSelected') }}
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
defineProps({
  count:   { type: Number,  default: 0     },
  loading: { type: Boolean, default: false },
});
defineEmits(['delete', 'clear']);
</script>

<style scoped>
.bab-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px 16px;
  margin-bottom: 12px;
  background: #fff3f7;
  border: 1px solid #fce4ec;
  border-radius: 8px;
}

.bab-label {
  font-size: 14px;
  font-weight: 500;
  color: #c2185b;
}

.bab-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.bab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.15s;
  line-height: 1.4;
}
.bab-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

.bab-btn-clear {
  background: transparent;
  border: 1px solid #d1d5db;
  color: #6b7280;
}
.bab-btn-clear:hover:not(:disabled) { background: #f3f4f6; border-color: #9ca3af; }

.bab-btn-delete {
  background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%);
  color: #fff;
}
.bab-btn-delete:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(233, 30, 99, 0.3);
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
  .bab-bar { background: rgba(233, 30, 99, 0.08); border-color: rgba(233, 30, 99, 0.25); }
  .bab-label { color: #f48fb1; }
  .bab-btn-clear { border-color: #4b5563; color: #9ca3af; }
  .bab-btn-clear:hover:not(:disabled) { background: rgba(255,255,255,0.05); border-color: #6b7280; }
}
:root[data-theme="dark"] .bab-bar { background: rgba(233, 30, 99, 0.08); border-color: rgba(233, 30, 99, 0.25); }
:root[data-theme="dark"] .bab-label { color: #f48fb1; }
:root[data-theme="dark"] .bab-btn-clear { border-color: #4b5563; color: #9ca3af; }
:root[data-theme="dark"] .bab-btn-clear:hover:not(:disabled) { background: rgba(255,255,255,0.05); border-color: #6b7280; }

/* Transition */
.bab-enter-active,
.bab-leave-active  { transition: opacity 0.18s ease, transform 0.18s ease; }
.bab-enter-from,
.bab-leave-to      { opacity: 0; transform: translateY(-6px); }
</style>
