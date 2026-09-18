<script setup>
import { useTemplateStore } from "@/stores/template";
const store = useTemplateStore();
</script>

<template>
  <footer id="page-footer" class="fk-footer">
    <div class="fk-inner">

      <!-- Left: copyright + version -->
      <div class="fk-left">
        <span class="fk-copy">&copy; {{ store.app.copyright }} Fakturalista</span>
        <span class="fk-version">v{{ store.app.version }}</span>
      </div>

      <!-- Right: status -->
      <span class="fk-status" role="status" aria-live="polite">
        <span class="fk-dot" aria-hidden="true"></span>
        {{ $t('footer.status') }}
      </span>

    </div>
  </footer>
</template>

<style scoped>
/* ── Light theme ────────────────────────────────────────────── */
.fk-footer {
  --fk-border:   #eaecf0;
  --fk-copy:     #a8b0c0;
  --fk-ver-bg:   #f4f6fa;
  --fk-ver-bd:   #e4e8f0;
  --fk-ver-tx:   #a8b0c0;
  --fk-dot:      #22c55e;
  --fk-dot-glow: rgba(34, 197, 94, 0.2);
  --fk-status:   #a8b0c0;
}

/* ── Dark mode ──────────────────────────────────────────────── */
:global(.dark-mode) .fk-footer {
  --fk-border:   rgba(255, 255, 255, 0.07);
  --fk-copy:     #4e5b72;
  --fk-ver-bg:   rgba(255, 255, 255, 0.04);
  --fk-ver-bd:   rgba(255, 255, 255, 0.08);
  --fk-ver-tx:   #4e5b72;
  --fk-dot:      #22c55e;
  --fk-dot-glow: rgba(34, 197, 94, 0.12);
  --fk-status:   #4e5b72;
}

/* ── Shell ──────────────────────────────────────────────────── */
.fk-footer {
  border-top: 1px solid var(--fk-border);
  background-color: transparent !important;
}

.fk-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  height: 36px;
  padding: 0 1.5rem;
}

/* ── Left ───────────────────────────────────────────────────── */
.fk-left {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.fk-copy {
  font-size: 11.5px;
  color: var(--fk-copy);
  white-space: nowrap;
  line-height: 1;
}

.fk-version {
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.02em;
  color: var(--fk-ver-tx);
  background: var(--fk-ver-bg);
  border: 1px solid var(--fk-ver-bd);
  border-radius: 100px;
  padding: 2px 6px;
  line-height: 1.5;
  white-space: nowrap;
}

/* ── Status ─────────────────────────────────────────────────── */
.fk-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  color: var(--fk-status);
  white-space: nowrap;
  flex-shrink: 0;
  user-select: none;
}

.fk-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--fk-dot);
  box-shadow: 0 0 0 3px var(--fk-dot-glow);
  flex-shrink: 0;
}

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 575px) {
  .fk-status { display: none; }
  .fk-inner  { justify-content: center; }
}
</style>
