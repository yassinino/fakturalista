/*
 * Main and demo navigation arrays
 *
 * 'to' attribute points to the route name, not the path url
 */

export default {
  main: [
    // ── Main ──────────────────────────────────────
    {
      i18nKey: "nav.dashboard",
      to: "backend-dashboard",
      icon: "si si-home",
    },
    {
      i18nKey: "nav.customers",
      to: "backend-customers",
      icon: "si si-people",
    },
    {
      i18nKey: "nav.invoices",
      to: "backend-invoices",
      icon: "si si-doc",
    },
    {
      i18nKey: "nav.quotes",
      to: "backend-quotes",
      icon: "si si-pencil",
    },
    {
      i18nKey: "nav.payments",
      to: "backend-payments",
      icon: "si si-wallet",
    },
    {
      i18nKey: "nav.reports",
      to: "backend-dashboard",
      icon: "si si-graph",
    },

    // ── Separator ─────────────────────────────────
    { heading: true, name: "" },

    // ── Tools ─────────────────────────────────────
    {
      i18nKey: "nav.items",
      to: "backend-items",
      icon: "si si-bag",
    },
    {
      i18nKey: "nav.templates",
      to: "backend-templates",
      icon: "si si-layers",
    },
    {
      i18nKey: "nav.settings",
      to: "backend-settings",
      icon: "si si-settings",
    },
  ],
};
