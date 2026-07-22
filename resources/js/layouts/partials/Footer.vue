<script setup>
import { useTemplateStore } from "@/stores/template";
const store = useTemplateStore();
</script>

<template>
  <footer id="page-footer" class="fk-app-footer">
    <div class="fk-footer-inner">

      <!-- Left: brand + version badge -->
      <div class="fk-footer-left">
        <span class="fk-footer-copy">&copy; {{ store.app.copyright }} Fakturalista</span>
        <span class="fk-footer-version">v{{ store.app.version }}</span>
      </div>

      <!-- Center: utility links + status -->
      <nav class="fk-footer-nav" aria-label="Footer">
        <a class="fk-footer-link" href="#">{{ $t('footer.privacy') }}</a>
        <a class="fk-footer-link" href="#">{{ $t('footer.terms') }}</a>
        <a class="fk-footer-link" href="#">{{ $t('footer.help') }}</a>
        <a class="fk-footer-link" href="#">{{ $t('footer.changelog') }}</a>
        <span class="fk-footer-status" role="status" aria-live="polite">
          <span class="fk-status-dot" aria-hidden="true"></span>
          {{ $t('footer.status') }}
        </span>
      </nav>

      <!-- Right: tagline -->
      <p class="fk-footer-tagline">{{ $t('footer.tagline') }}</p>

    </div>
  </footer>
</template>

<style scoped>
/* ── Design tokens — light mode ──────────────────────────────── */
.fk-app-footer {
  --fk-border:        #e5e7eb;
  --fk-bg:            transparent;
  --fk-copy:          #9ca3af;
  --fk-link:          #6b7280;
  --fk-link-hover:    #111827;
  --fk-badge-bg:      #f3f4f6;
  --fk-badge-border:  #e5e7eb;
  --fk-badge-text:    #6b7280;
  --fk-status-dot:    #22c55e;
  --fk-status-glow:   rgba(34, 197, 94, 0.22);
  --fk-tagline:       #9ca3af;
}

/* Dark mode — OneUI adds .dark-mode to #page-container */
:global(.dark-mode) .fk-app-footer {
  --fk-border:        rgba(255, 255, 255, 0.07);
  --fk-bg:            transparent;
  --fk-copy:          #4b5563;
  --fk-link:          #4b5563;
  --fk-link-hover:    #d1d5db;
  --fk-badge-bg:      rgba(255, 255, 255, 0.05);
  --fk-badge-border:  rgba(255, 255, 255, 0.09);
  --fk-badge-text:    #4b5563;
  --fk-status-dot:    #22c55e;
  --fk-status-glow:   rgba(34, 197, 94, 0.18);
  --fk-tagline:       #374151;
}

/* ── Shell ───────────────────────────────────────────────────── */
.fk-app-footer {
  background: var(--fk-bg);
  border-top: 1px solid var(--fk-border);
  padding-inline: 1.5rem;
  /* Override OneUI's bg-body-light */
  background-color: transparent !important;
}

.fk-footer-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  height: 52px;
}

/* ── Left ────────────────────────────────────────────────────── */
.fk-footer-left {
  display: flex;
  align-items: center;
  gap: 9px;
  flex-shrink: 0;
}

.fk-footer-copy {
  font-size: 13px;
  font-weight: 400;
  color: var(--fk-copy);
  white-space: nowrap;
  line-height: 1;
}

.fk-footer-version {
  display: inline-flex;
  align-items: center;
  font-size: 11px;
  font-weight: 500;
  color: var(--fk-badge-text);
  background: var(--fk-badge-bg);
  border: 1px solid var(--fk-badge-border);
  border-radius: 100px;
  padding: 2px 8px;
  line-height: 1.45;
  letter-spacing: 0.02em;
  white-space: nowrap;
}

/* ── Nav ─────────────────────────────────────────────────────── */
.fk-footer-nav {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  justify-content: center;
}

.fk-footer-link {
  font-size: 13px;
  font-weight: 400;
  color: var(--fk-link);
  text-decoration: none;
  line-height: 1;
  white-space: nowrap;
  transition: color 0.15s ease;
}
.fk-footer-link:hover {
  color: var(--fk-link-hover);
  text-decoration: none;
}

/* ── Status ──────────────────────────────────────────────────── */
.fk-footer-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 400;
  color: var(--fk-link);
  line-height: 1;
  white-space: nowrap;
  user-select: none;
}

.fk-status-dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--fk-status-dot);
  box-shadow: 0 0 0 3px var(--fk-status-glow);
  flex-shrink: 0;
}

/* ── Tagline ─────────────────────────────────────────────────── */
.fk-footer-tagline {
  font-size: 13px;
  font-weight: 400;
  color: var(--fk-tagline);
  white-space: nowrap;
  margin: 0;
  flex-shrink: 0;
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 991px) {
  .fk-footer-inner {
    flex-wrap: wrap;
    height: auto;
    padding-block: 14px;
    gap: 10px 16px;
    justify-content: center;
    text-align: center;
  }
  .fk-footer-left,
  .fk-footer-tagline {
    width: 100%;
    justify-content: center;
  }
  .fk-footer-left {
    display: flex;
    justify-content: center;
  }
  .fk-footer-nav {
    gap: 12px 16px;
  }
}

@media (max-width: 479px) {
  .fk-footer-nav {
    flex-direction: column;
    gap: 10px;
    align-items: center;
  }
  .fk-footer-tagline {
    display: none;
  }
}
</style>
