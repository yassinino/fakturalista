<template>
  <!-- Trigger: the "…" button -->
  <div ref="triggerRef" class="ram-trigger" @click.stop="toggle">
    <button
      type="button"
      class="dt-action-btn"
      :aria-expanded="String(open)"
      aria-haspopup="true"
    >
      <i class="fa fa-ellipsis-v"></i>
    </button>
  </div>

  <!-- Menu teleported to <body> so overflow:hidden on parent blocks can't clip it -->
  <Teleport to="body">
    <div
      v-if="open"
      ref="menuRef"
      class="ram-menu"
      :style="menuStyle"
      @click="close"
    >
      <slot />
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';

const open       = ref(false);
const triggerRef = ref(null);
const menuRef    = ref(null);
const coords     = ref({ top: 0, left: 0 });

const menuStyle = computed(() => ({
  position: 'fixed',
  top:  coords.value.top  + 'px',
  left: coords.value.left + 'px',
  zIndex: 9999,
}));

async function toggle() {
  if (open.value) { close(); return; }
  open.value = true;
  await nextTick();
  reposition();
}

function close() { open.value = false; }

function reposition() {
  if (!triggerRef.value || !menuRef.value) return;
  const tr  = triggerRef.value.getBoundingClientRect();
  const mr  = menuRef.value.getBoundingClientRect();
  const vw  = window.innerWidth;
  const vh  = window.innerHeight;
  const PAD = 8;

  // Align right edge of menu to right edge of trigger; open below
  let left = tr.right - mr.width;
  let top  = tr.bottom + 4;

  // Flip up when near viewport bottom
  if (top + mr.height > vh - PAD) top = tr.top - mr.height - 4;

  // Clamp to viewport
  if (left < PAD) left = PAD;
  if (left + mr.width > vw - PAD) left = vw - mr.width - PAD;
  if (top  < PAD) top  = PAD;

  coords.value = { top, left };
}

function onOutsideClick(e) {
  if (!open.value) return;
  if (triggerRef.value?.contains(e.target)) return;
  close();
}

function onKeydown(e) { if (e.key === 'Escape') close(); }
function onScroll()   { if (open.value) close(); }

onMounted(() => {
  document.addEventListener('click',   onOutsideClick, { capture: true });
  document.addEventListener('keydown', onKeydown);
  window.addEventListener('scroll',    onScroll, { passive: true, capture: true });
});

onBeforeUnmount(() => {
  document.removeEventListener('click',   onOutsideClick, { capture: true });
  document.removeEventListener('keydown', onKeydown);
  window.removeEventListener('scroll',    onScroll, { capture: true });
});
</script>

<style scoped>
.ram-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* ── Menu container ─────────────────────────────────────────── */
.ram-menu {
  background: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.09);
  border-radius: 8px;
  box-shadow:
    0 4px 24px rgba(0, 0, 0, 0.11),
    0 1px 4px  rgba(0, 0, 0, 0.06);
  padding: 4px;
  min-width: 176px;
}

/* ── Items (slot content from parent) ───────────────────────── */
:deep(.dropdown-item) {
  display: flex !important;
  align-items: center;
  padding: 8px 12px !important;
  font-size: 13px !important;
  font-weight: 500;
  color: #374151 !important;
  border-radius: 5px;
  cursor: pointer;
  white-space: nowrap;
  text-decoration: none !important;
  transition: background 0.11s, color 0.11s;
  font-family: inherit;
  background: transparent !important;
  border: none;
  width: 100%;
  box-sizing: border-box;
  line-height: 1.4;
}

:deep(.dropdown-item:hover),
:deep(.dropdown-item:focus) {
  background: #f1f5f9 !important;
  color: #111827 !important;
  outline: none;
}

/* Danger items — must beat Bootstrap's !important on .text-danger */
:deep(.dropdown-item.text-danger) {
  color: #dc2626 !important;
}

:deep(.dropdown-item.text-danger:hover),
:deep(.dropdown-item.text-danger:focus) {
  background: rgba(220, 38, 38, 0.07) !important;
  color: #b91c1c !important;
}

/* Muted (no email / no phone) */
:deep(.dropdown-item.text-muted) {
  color: #9ca3af !important;
  cursor: default !important;
}

:deep(.dropdown-item.text-muted:hover) {
  background: transparent !important;
  color: #9ca3af !important;
}

/* Disabled state */
:deep(.dropdown-item.disabled),
:deep(.dropdown-item:disabled) {
  opacity: 0.45;
  cursor: not-allowed !important;
  pointer-events: none;
}

/* ── Divider between normal / destructive groups ────────────── */
:deep(.dropdown-divider) {
  border: none !important;
  border-top: 1px solid #f0f2f5 !important;
  margin: 4px 0 !important;
}

/* ── Dark mode ──────────────────────────────────────────────── */
@media (prefers-color-scheme: dark) {
  .ram-menu {
    background: #1e293b;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow:
      0 4px 24px rgba(0, 0, 0, 0.45),
      0 1px 4px  rgba(0, 0, 0, 0.35);
  }

  :deep(.dropdown-item) {
    color: #cbd5e1 !important;
  }

  :deep(.dropdown-item:hover),
  :deep(.dropdown-item:focus) {
    background: rgba(255, 255, 255, 0.07) !important;
    color: #f1f5f9 !important;
  }

  :deep(.dropdown-item.text-danger) {
    color: #f87171 !important;
  }

  :deep(.dropdown-item.text-danger:hover),
  :deep(.dropdown-item.text-danger:focus) {
    background: rgba(239, 68, 68, 0.13) !important;
    color: #fca5a5 !important;
  }

  :deep(.dropdown-item.text-muted) {
    color: #64748b !important;
  }

  :deep(.dropdown-divider) {
    border-top-color: rgba(255, 255, 255, 0.07) !important;
  }
}
</style>
