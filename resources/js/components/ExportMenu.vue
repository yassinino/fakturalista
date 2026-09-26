<template>
  <div class="xpm-wrap" ref="wrapRef">
    <button type="button" class="xpm-btn" :disabled="isExporting" @click="open = !open">
      <i class="fa fa-spinner fa-spin xpm-btn-icon" v-if="isExporting"></i>
      <i class="fa fa-download xpm-btn-icon" v-else></i>
      {{ isExporting ? $t('exportMenu.exporting') : $t('exportMenu.label') }}
      <i class="fa fa-chevron-down xpm-btn-caret" v-if="!isExporting"></i>
    </button>

    <div class="xpm-panel" v-show="open">
      <button type="button" class="xpm-item" :disabled="isExporting" @click="run('pdf')">
        <i class="fa fa-file-pdf xpm-item-icon xpm-item-icon--pdf"></i>
        {{ $t('exportMenu.pdf') }}
      </button>
      <button type="button" class="xpm-item" :disabled="isExporting" @click="run('xlsx')">
        <i class="fa fa-file-excel xpm-item-icon xpm-item-icon--xlsx"></i>
        {{ $t('exportMenu.excel') }}
      </button>
      <button type="button" class="xpm-item" :disabled="isExporting" @click="run('csv')">
        <i class="fa fa-file-csv xpm-item-icon xpm-item-icon--csv"></i>
        {{ $t('exportMenu.csv') }}
      </button>
    </div>
  </div>
</template>

<script setup>
/**
 * Reusable "Export" dropdown - Reports, Invoices, Quotes, Payments and
 * Clients all use this same component instead of duplicating the
 * dropdown UI + download/toast plumbing on every page.
 *
 * The caller owns what gets exported (endpoint + params, matching that
 * page's own current filters) and the display filename (without
 * extension - this component only appends ".pdf/.xlsx/.csv").
 */
import { ref, computed, onMounted, onUnmounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { createToaster } from "@meforma/vue-toaster";

const props = defineProps({
  endpoint: { type: String, required: true },
  params:   { type: Object, default: () => ({}) },
  // Either a ready-made filename (Reports - its own period-aware naming),
  // or a plain prefix this component suffixes with the current "YYYY-MM"
  // itself (Invoices/Quotes/Payments/Clients - e.g. "invoices" -> "invoices-2026-09").
  filename: { type: String, default: "" },
  prefix:   { type: String, default: "" },
});

const { t } = useI18n();
const toaster = createToaster();

const open = ref(false);
const isExporting = ref(false);
const wrapRef = ref(null);

function close() {
  open.value = false;
}

function onDocClick(e) {
  if (open.value && wrapRef.value && !wrapRef.value.contains(e.target)) close();
}

onMounted(() => document.addEventListener("click", onDocClick));
onUnmounted(() => document.removeEventListener("click", onDocClick));

const resolvedFilename = computed(() => {
  if (props.filename) return props.filename;
  const ym = new Date().toISOString().slice(0, 7); // YYYY-MM
  return `${props.prefix}-${ym}`;
});

const EXTENSIONS = { pdf: "pdf", xlsx: "xlsx", csv: "csv" };
const MIME_TYPES  = {
  pdf:  "application/pdf",
  xlsx: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  csv:  "text/csv",
};

async function run(format) {
  if (isExporting.value) return; // prevent duplicate clicks while one is in flight
  isExporting.value = true;
  open.value = false;

  try {
    const response = await axios.get(props.endpoint, {
      params: { ...props.params, format },
      responseType: "blob",
    });

    const blob = new Blob([response.data], { type: MIME_TYPES[format] });
    const url  = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `${resolvedFilename.value}.${EXTENSIONS[format]}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    toaster.success(t("exportMenu.success"));
  } catch {
    toaster.error(t("exportMenu.error"));
  } finally {
    isExporting.value = false;
  }
}

defineExpose({ run });
</script>

<style scoped>
.xpm-wrap { position: relative; display: inline-block; }

.xpm-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 500;
  border: 1px solid var(--xpm-border, #e6eaef);
  background: var(--xpm-surface, #fff);
  color: var(--xpm-text, #445066);
  cursor: pointer;
  white-space: nowrap;
  transition: background .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
}
.xpm-btn:hover:not(:disabled) {
  border-color: var(--xpm-border-2, #ccd2db);
  color: var(--xpm-text-strong, #0d1117);
  transform: translateY(-1px);
}
.xpm-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.xpm-btn-icon { font-size: 12px; }
.xpm-btn-caret { font-size: 9px; margin-left: 2px; opacity: 0.7; }

.xpm-panel {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  min-width: 190px;
  background: var(--xpm-surface, #fff);
  border: 1px solid var(--xpm-border, #e6eaef);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(13, 17, 23, 0.12);
  padding: 6px;
  z-index: 60;
}

.xpm-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 9px 10px;
  border: none;
  background: none;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  color: var(--xpm-text, #445066);
  text-align: left;
  cursor: pointer;
  transition: background .12s ease;
}
.xpm-item:hover:not(:disabled) { background: var(--xpm-hover, #f3f5f8); }
.xpm-item:disabled { opacity: 0.6; cursor: not-allowed; }

.xpm-item-icon { width: 16px; text-align: center; font-size: 14px; }
.xpm-item-icon--pdf  { color: #dc2626; }
.xpm-item-icon--xlsx { color: #16a34a; }
.xpm-item-icon--csv  { color: #2563eb; }

/* Dark mode - the app's global .dark-mode class on #page-container */
:global(.dark-mode) .xpm-btn,
:global(.dark-mode) .xpm-panel {
  --xpm-surface: var(--dark-surface-elevated);
  --xpm-border: var(--dark-border-subtle);
  --xpm-border-2: var(--dark-border);
  --xpm-text: var(--dark-text-muted);
  --xpm-text-strong: var(--dark-text);
  --xpm-hover: var(--dark-surface);
}

/* RTL - the panel opens from the reading-start side instead of a fixed right:0 */
:global(.rtl-support) .xpm-panel { right: auto; left: 0; }
:global(.rtl-support) .xpm-item { text-align: right; }
</style>
