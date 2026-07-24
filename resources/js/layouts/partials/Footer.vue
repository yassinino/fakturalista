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

      <!-- Center: links -->
      <nav class="fk-nav" aria-label="Footer links">
        <a class="fk-link" href="#">{{ $t('footer.privacy') }}</a>
        <a class="fk-link" href="#">{{ $t('footer.terms') }}</a>
        <a class="fk-link" href="#">{{ $t('footer.help') }}</a>
        <a class="fk-link" href="#">{{ $t('footer.changelog') }}</a>
      </nav>

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
  --fk-copy:     #9da6ba;
  --fk-link:     #8590a6;
  --fk-link-h:   #1a2036;
  --fk-ver-bg:   #f4f6fa;
  --fk-ver-bd:   #e4e8f0;
  --fk-ver-tx:   #8590a6;
  --fk-dot:      #22c55e;
  --fk-dot-glow: rgba(34, 197, 94, 0.2);
}

/* ── Dark mode ──────────────────────────────────────────────── */
:global(.dark-mode) .fk-footer {
  --fk-border:   rgba(255, 255, 255, 0.07);
  --fk-copy:     #3d4762;
  --fk-link:     #3d4762;
  --fk-link-h:   #8fa3c8;
  --fk-ver-bg:   rgba(255, 255, 255, 0.04);
  --fk-ver-bd:   rgba(255, 255, 255, 0.08);
  --fk-ver-tx:   #3d4762;
  --fk-dot:      #22c55e;
  --fk-dot-glow: rgba(34, 197, 94, 0.15);
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
  height: 46px;
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
  font-size: 12px;
  color: var(--fk-copy);
  white-space: nowrap;
  line-height: 1;
}

.fk-version {
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.02em;
  color: var(--fk-ver-tx);
  background: var(--fk-ver-bg);
  border: 1px solid var(--fk-ver-bd);
  border-radius: 100px;
  padding: 2px 7px;
  line-height: 1.5;
  white-space: nowrap;
}

/* ── Center nav ─────────────────────────────────────────────── */
.fk-nav {
  display: flex;
  align-items: center;
  gap: 20px;
}

.fk-link {
  font-size: 12px;
  color: var(--fk-link);
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.15s ease;
}
.fk-link:hover {
  color: var(--fk-link-h);
  text-decoration: none;
}

/* ── Status ─────────────────────────────────────────────────── */
.fk-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--fk-link);
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
@media (max-width: 991px) {
  .fk-inner {
    height: auto;
    padding: 12px 1rem;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px 16px;
  }
  .fk-left,
  .fk-status {
    flex-basis: 100%;
    justify-content: center;
  }
  .fk-left { display: flex; }
  .fk-nav  { gap: 14px; flex-wrap: wrap; justify-content: center; }
}

@media (max-width: 479px) {
  .fk-status { display: none; }
  .fk-nav    { gap: 10px; }
}
</style>
