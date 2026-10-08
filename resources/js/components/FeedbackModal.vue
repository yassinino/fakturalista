<script setup>
/**
 * Feedback modal - tenant app (opened from layouts/partials/Header.vue).
 * Posts multipart to POST /api/feedback (FeedbackController::storeTenant);
 * the server attributes tenant, user and email itself. Public-site twin:
 * resources/views/partials/feedback.blade.php.
 */
import { ref, computed, watch, nextTick, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";
import { useThemeScope } from "@/composables/useThemeScope";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  email: { type: String, default: "" },
});
const emit = defineEmits(["update:modelValue"]);

const { t } = useI18n();
const themeScope = useThemeScope();

const MAX_LENGTH = 5000;
const MAX_BYTES = 5 * 1024 * 1024;
const ACCEPTED = ["image/png", "image/jpeg", "image/webp"];

const TYPES = [
  { value: "suggestion", icon: "fa-lightbulb" },
  { value: "bug", icon: "fa-bug" },
  { value: "improvement", icon: "fa-wand-magic-sparkles" },
  { value: "other", icon: "fa-ellipsis" },
];

const type = ref("suggestion");
const message = ref("");
const file = ref(null);
const previewUrl = ref(null);
const errors = ref({});
const alert = ref("");
const submitting = ref(false);
const sent = ref(false);
const dragging = ref(false);

const dialogRef = ref(null);
const messageRef = ref(null);
const fileInputRef = ref(null);
const successRef = ref(null);
let lastFocus = null;

const count = computed(() => `${message.value.length} / ${MAX_LENGTH}`);

function reset() {
  type.value = "suggestion";
  message.value = "";
  setFile(null);
  errors.value = {};
  alert.value = "";
  sent.value = false;
}

function close() {
  emit("update:modelValue", false);
}

watch(
  () => props.modelValue,
  async (open) => {
    document.documentElement.classList.toggle("fkfb-lock", open);
    if (open) {
      lastFocus = document.activeElement;
      if (sent.value) reset();
      await nextTick();
      messageRef.value?.focus();
    } else {
      if (sent.value) setTimeout(reset, 250);
      lastFocus?.focus?.();
    }
  }
);

onUnmounted(() => {
  document.documentElement.classList.remove("fkfb-lock");
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

// ── Screenshot ────────────────────────────────────────────────
function setFile(f) {
  const next = { ...errors.value };
  delete next.screenshot;
  errors.value = next;

  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = null;
  file.value = null;

  if (f) {
    if (!ACCEPTED.includes(f.type)) errors.value = { ...errors.value, screenshot: t("feedback.errors.screenshot_type") };
    else if (f.size > MAX_BYTES) errors.value = { ...errors.value, screenshot: t("feedback.errors.screenshot_size") };
    else {
      file.value = f;
      previewUrl.value = URL.createObjectURL(f);
    }
  }
  if (fileInputRef.value) fileInputRef.value.value = "";
}

function onFileChange(e) {
  setFile(e.target.files?.[0] ?? null);
}

function onDrop(e) {
  dragging.value = false;
  setFile(e.dataTransfer?.files?.[0] ?? null);
}

function onPaste(e) {
  const f = e.clipboardData?.files?.[0];
  if (f && f.type.startsWith("image/")) {
    e.preventDefault();
    setFile(f);
  }
}

function removeFile() {
  setFile(null);
  nextTick(() => fileInputRef.value?.focus());
}

// ── Validation + submit ───────────────────────────────────────
function messageError() {
  const m = message.value.trim();
  if (!m) return t("feedback.errors.message_required");
  if (m.length < 5) return t("feedback.errors.message_min");
  if (m.length > MAX_LENGTH) return t("feedback.errors.message_max");
  return null;
}

watch(message, () => {
  if (errors.value.message) {
    const next = { ...errors.value };
    delete next.message;
    errors.value = next;
  }
});

async function submit() {
  if (submitting.value) return;
  alert.value = "";

  const msgError = messageError();
  if (msgError) {
    errors.value = { ...errors.value, message: msgError };
    messageRef.value?.focus();
    return;
  }

  const data = new FormData();
  data.append("type", type.value);
  data.append("message", message.value);
  data.append("page_url", window.location.href);
  if (file.value) data.append("screenshot", file.value, file.value.name || "capture.png");

  submitting.value = true;
  try {
    await axios.post("/feedback", data, { headers: { Accept: "application/json" } });
    sent.value = true;
    await nextTick();
    successRef.value?.focus();
  } catch (error) {
    const status = error?.response?.status;
    if (status === 422) {
      const bag = error.response.data?.errors ?? {};
      const next = {};
      if (bag.message) next.message = messageError() ?? t("feedback.errors.message_max");
      if (bag.screenshot) next.screenshot = file.value?.size > MAX_BYTES ? t("feedback.errors.screenshot_size") : t("feedback.errors.screenshot_type");
      if (bag.type || (!next.message && !next.screenshot)) alert.value = t("feedback.errors.generic");
      errors.value = next;
    } else {
      alert.value = status === 429 ? t("feedback.errors.rate_limited") : t("feedback.errors.generic");
    }
  } finally {
    submitting.value = false;
  }
}

// ── Keyboard: Esc + focus trap ────────────────────────────────
function onKeydown(e) {
  if (e.key === "Escape") {
    e.preventDefault();
    close();
    return;
  }
  if (e.key !== "Tab" || !dialogRef.value) return;
  const list = [...dialogRef.value.querySelectorAll(
    'button, [href], input:not([tabindex="-1"]), textarea, select, [tabindex]:not([tabindex="-1"])'
  )].filter((el) => !el.disabled && el.offsetParent !== null);
  if (!list.length) return;
  const first = list[0];
  const last = list[list.length - 1];
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault();
    first.focus();
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="theme-scope" :class="themeScope">
      <Transition name="fkfb">
        <div v-if="modelValue" class="fkfb-backdrop" @click.self="close" @keydown="onKeydown">
          <div
            ref="dialogRef"
            class="fkfb-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="fkfb-app-title"
            aria-describedby="fkfb-app-subtitle"
            @paste="onPaste"
          >
            <button type="button" class="fkfb-close" :aria-label="t('feedback.close')" @click="close">
              <i class="fa fa-xmark" aria-hidden="true"></i>
            </button>

            <form v-if="!sent" novalidate @submit.prevent="submit">
              <div class="fkfb-head">
                <span class="fkfb-badge" aria-hidden="true"><i class="fa fa-comment-dots"></i></span>
                <h2 id="fkfb-app-title" class="fkfb-title">{{ t("feedback.title") }}</h2>
                <p id="fkfb-app-subtitle" class="fkfb-subtitle">{{ t("feedback.subtitle") }}</p>
              </div>

              <fieldset class="fkfb-field">
                <legend class="fkfb-label">{{ t("feedback.type_label") }}</legend>
                <div class="fkfb-types">
                  <label v-for="option in TYPES" :key="option.value" class="fkfb-type">
                    <input v-model="type" type="radio" name="fkfb-type" :value="option.value" />
                    <span class="fkfb-type-inner">
                      <i class="fa" :class="option.icon" aria-hidden="true"></i>
                      {{ t(`feedback.types.${option.value}`) }}
                    </span>
                  </label>
                </div>
              </fieldset>

              <div class="fkfb-field">
                <label class="fkfb-label" for="fkfb-app-message">
                  {{ t("feedback.message_label") }} <span class="fkfb-req" aria-hidden="true">*</span>
                </label>
                <textarea
                  id="fkfb-app-message"
                  ref="messageRef"
                  v-model="message"
                  class="fkfb-input fkfb-textarea"
                  rows="5"
                  :maxlength="MAX_LENGTH"
                  required
                  :aria-invalid="errors.message ? 'true' : 'false'"
                  aria-describedby="fkfb-app-message-error"
                  :placeholder="t('feedback.message_placeholder')"
                ></textarea>
                <div class="fkfb-meta">
                  <p id="fkfb-app-message-error" class="fkfb-error" role="alert">{{ errors.message }}</p>
                  <span class="fkfb-count" aria-hidden="true">{{ count }}</span>
                </div>
              </div>

              <p v-if="email" class="fkfb-sender">
                <i class="fa fa-envelope" aria-hidden="true"></i>
                {{ t("feedback.sent_as", { email }) }}
              </p>

              <div class="fkfb-field">
                <span id="fkfb-app-shot-label" class="fkfb-label">{{ t("feedback.screenshot_label") }}</span>
                <label
                  v-if="!file"
                  class="fkfb-drop"
                  :class="{ 'is-drag': dragging }"
                  for="fkfb-app-file"
                  @dragenter.prevent="dragging = true"
                  @dragover.prevent="dragging = true"
                  @dragleave.prevent="dragging = false"
                  @drop.prevent="onDrop"
                >
                  <input
                    id="fkfb-app-file"
                    ref="fileInputRef"
                    type="file"
                    class="fkfb-file"
                    accept="image/png,image/jpeg,image/webp"
                    aria-labelledby="fkfb-app-shot-label"
                    aria-describedby="fkfb-app-shot-hint fkfb-app-screenshot-error"
                    @change="onFileChange"
                  />
                  <i class="fa fa-image fkfb-drop-icon" aria-hidden="true"></i>
                  <span>
                    <strong>{{ t("feedback.screenshot_add") }}</strong>
                    <small id="fkfb-app-shot-hint">{{ t("feedback.screenshot_hint") }}</small>
                  </span>
                </label>
                <div v-else class="fkfb-preview">
                  <img :src="previewUrl" alt="" />
                  <span class="fkfb-preview-name">{{ file.name }}</span>
                  <button type="button" class="fkfb-preview-remove" :aria-label="t('feedback.screenshot_remove')" @click="removeFile">
                    <i class="fa fa-xmark" aria-hidden="true"></i>
                  </button>
                </div>
                <p id="fkfb-app-screenshot-error" class="fkfb-error" role="alert">{{ errors.screenshot }}</p>
              </div>

              <p v-if="alert" class="fkfb-alert" role="alert">{{ alert }}</p>

              <div class="fkfb-actions">
                <button type="button" class="fkfb-btn fkfb-btn--ghost" @click="close">{{ t("feedback.cancel") }}</button>
                <button
                  type="submit"
                  class="fkfb-btn fkfb-btn--primary"
                  :class="{ 'is-loading': submitting }"
                  :disabled="submitting"
                  :aria-busy="submitting ? 'true' : 'false'"
                >
                  <span class="fkfb-spinner" aria-hidden="true"></span>
                  {{ submitting ? t("feedback.submitting") : t("feedback.submit") }}
                </button>
              </div>
            </form>

            <div v-else ref="successRef" class="fkfb-success" tabindex="-1">
              <span class="fkfb-success-icon" aria-hidden="true"><i class="fa fa-check"></i></span>
              <h2 id="fkfb-app-title" class="fkfb-title">{{ t("feedback.success_title") }}</h2>
              <p class="fkfb-subtitle" role="status">{{ t("feedback.success") }}</p>
              <button type="button" class="fkfb-btn fkfb-btn--primary" @click="close">{{ t("feedback.close") }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </Teleport>
</template>

<style scoped>
/* Colours come from the app's semantic tokens (custom/_main.scss), which
   .theme-scope re-applies for dark mode on this teleported content. */
.fkfb-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
}

.fkfb-dialog {
  position: relative;
  width: 100%;
  max-width: 480px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
  overscroll-behavior: contain;
  background: var(--bg-elevated);
  color: var(--text-primary);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 28px 28px 24px;
  box-shadow: 0 24px 60px -12px rgba(15, 23, 42, 0.35);
  text-align: start;
  font-size: 14px;
  line-height: 1.5;
}

.fkfb-enter-active,
.fkfb-leave-active { transition: opacity 0.22s ease; }
.fkfb-enter-active .fkfb-dialog,
.fkfb-leave-active .fkfb-dialog { transition: transform 0.26s cubic-bezier(0.2, 0.8, 0.2, 1); }
.fkfb-enter-from,
.fkfb-leave-to { opacity: 0; }
.fkfb-enter-from .fkfb-dialog,
.fkfb-leave-to .fkfb-dialog { transform: translateY(14px) scale(0.98); }

.fkfb-close {
  position: absolute;
  top: 14px;
  inset-inline-end: 14px;
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--text-muted);
  font-size: 16px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.fkfb-close:hover { background: var(--hover-bg); color: var(--text-primary); }

.fkfb-head { margin-bottom: 20px; padding-inline-end: 28px; }
.fkfb-badge {
  display: inline-grid;
  place-items: center;
  width: 40px;
  height: 40px;
  margin-bottom: 14px;
  border-radius: 12px;
  background: rgba(233, 30, 99, 0.12);
  color: var(--brand-text);
  font-size: 17px;
}
.fkfb-title { margin: 0 0 6px; font-size: 20px; font-weight: 700; letter-spacing: -0.01em; color: var(--text-primary); line-height: 1.3; }
.fkfb-subtitle { margin: 0; color: var(--text-secondary); font-size: 14px; }

.fkfb-field { margin: 0 0 16px; padding: 0; border: 0; min-width: 0; }
.fkfb-label { display: block; float: none; width: auto; margin-bottom: 7px; padding: 0; font-size: 13px; font-weight: 600; line-height: 1.4; color: var(--text-primary); }
.fkfb-req { color: var(--brand-text); }

.fkfb-types { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.fkfb-type { position: relative; margin: 0; cursor: pointer; }
.fkfb-type input { position: absolute; opacity: 0; pointer-events: none; }
.fkfb-type-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 11px 4px 10px;
  border: 1px solid var(--border);
  border-radius: 12px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text-secondary);
  text-align: center;
  transition: border-color 0.15s, background 0.15s, color 0.15s, box-shadow 0.15s;
}
.fkfb-type-inner .fa { font-size: 15px; }
.fkfb-type:hover .fkfb-type-inner { background: var(--hover-bg); }
.fkfb-type input:checked + .fkfb-type-inner {
  border-color: var(--brand);
  background: rgba(233, 30, 99, 0.09);
  color: var(--brand-text);
  box-shadow: 0 0 0 1px var(--brand) inset;
}
.fkfb-type input:focus-visible + .fkfb-type-inner { outline: 3px solid rgba(233, 30, 99, 0.4); outline-offset: 1px; }

.fkfb-input {
  display: block;
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--input-bg);
  color: var(--text-primary);
  font: inherit;
  font-size: 14px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.fkfb-input::placeholder { color: var(--text-muted); opacity: 0.8; }
.fkfb-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.18); }
.fkfb-input[aria-invalid="true"] { border-color: var(--danger-text); }
.fkfb-textarea { resize: vertical; min-height: 110px; }

.fkfb-meta { display: flex; justify-content: space-between; gap: 12px; }
.fkfb-count { margin-top: 5px; margin-inline-start: auto; font-size: 12px; color: var(--text-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
.fkfb-error { margin: 5px 0 0; font-size: 12.5px; color: var(--danger-text); }
.fkfb-error:empty { display: none; }

.fkfb-sender {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: -4px 0 16px;
  font-size: 12.5px;
  color: var(--text-muted);
  overflow-wrap: anywhere;
}

.fkfb-drop {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
  padding: 12px 14px;
  border: 1px dashed var(--border);
  border-radius: 12px;
  color: var(--text-secondary);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.fkfb-drop:hover,
.fkfb-drop.is-drag { border-color: var(--brand); background: rgba(233, 30, 99, 0.06); }
.fkfb-drop:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.18); }
.fkfb-drop-icon { font-size: 17px; }
.fkfb-drop strong { display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); }
.fkfb-drop small { display: block; font-size: 12px; color: var(--text-muted); }
.fkfb-file { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }

.fkfb-preview { display: flex; align-items: center; gap: 12px; padding: 8px; border: 1px solid var(--border); border-radius: 12px; }
.fkfb-preview img { width: 56px; height: 42px; object-fit: cover; border-radius: 6px; background: var(--hover-bg); }
.fkfb-preview-name { flex: 1; min-width: 0; font-size: 13px; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fkfb-preview-remove { width: 30px; height: 30px; display: grid; place-items: center; border: 0; border-radius: 8px; background: transparent; color: var(--text-muted); cursor: pointer; }
.fkfb-preview-remove:hover { background: rgba(220, 38, 38, 0.12); color: var(--danger-text); }

.fkfb-alert {
  margin: 0 0 14px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(220, 38, 38, 0.1);
  color: var(--danger-text);
  font-size: 13px;
}

.fkfb-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
.fkfb-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 20px;
  border-radius: 10px;
  border: 1px solid transparent;
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, opacity 0.15s;
}
.fkfb-btn--ghost { background: transparent; border-color: var(--border); color: var(--text-primary); }
.fkfb-btn--ghost:hover { background: var(--hover-bg); }
.fkfb-btn--primary { background: var(--brand); color: #fff; }
.fkfb-btn--primary:hover { background: var(--brand-hover); }
.fkfb-btn:focus-visible { outline: 3px solid rgba(233, 30, 99, 0.45); outline-offset: 2px; }
.fkfb-btn[disabled] { opacity: 0.7; cursor: progress; }
.fkfb-spinner { display: none; width: 15px; height: 15px; border: 2px solid rgba(255, 255, 255, 0.45); border-top-color: #fff; border-radius: 50%; animation: fkfb-spin 0.7s linear infinite; }
.fkfb-btn.is-loading .fkfb-spinner { display: inline-block; }
@keyframes fkfb-spin { to { transform: rotate(360deg); } }

.fkfb-success { padding: 18px 4px 6px; text-align: center; }
.fkfb-success:focus { outline: none; }
.fkfb-success-icon {
  display: inline-grid;
  place-items: center;
  width: 60px;
  height: 60px;
  margin-bottom: 16px;
  border-radius: 50%;
  background: rgba(34, 197, 94, 0.14);
  color: var(--success-text);
  font-size: 24px;
  animation: fkfb-pop 0.35s cubic-bezier(0.2, 0.8, 0.2, 1.3);
}
.fkfb-success .fkfb-subtitle { margin: 0 auto 22px; max-width: 340px; }
@keyframes fkfb-pop { from { transform: scale(0.6); opacity: 0; } to { transform: none; opacity: 1; } }

@media (max-width: 575px) {
  .fkfb-backdrop { align-items: flex-end; padding: 0; }
  .fkfb-dialog { max-width: none; max-height: 92vh; border-radius: 18px 18px 0 0; padding: 22px 18px 18px; }
  .fkfb-enter-from .fkfb-dialog,
  .fkfb-leave-to .fkfb-dialog { transform: translateY(40px); }
  .fkfb-types { grid-template-columns: repeat(2, 1fr); }
  .fkfb-type-inner { flex-direction: row; justify-content: center; padding: 10px 6px; }
  .fkfb-actions .fkfb-btn { flex: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .fkfb-enter-active,
  .fkfb-leave-active,
  .fkfb-enter-active .fkfb-dialog,
  .fkfb-leave-active .fkfb-dialog { transition: none; }
  .fkfb-success-icon,
  .fkfb-spinner { animation: none; }
}
</style>

<style>
/* Unscoped on purpose: locks page scroll while the modal is open. */
html.fkfb-lock,
html.fkfb-lock body { overflow: hidden; }
</style>
