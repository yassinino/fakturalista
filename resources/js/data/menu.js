/*
 * Main and demo navigation arrays
 *
 * 'to' attribute points to the route name, not the path url
 */

export default {
  main: [
    // {
    //   name: "Dashboard",
    //   to: "backend-dashboard",
    //   icon: "si si-speedometer",
    // },
    {
      i18nKey: "nav.customers",
      to: "backend-customers",
      icon: "si si-user",
    },
    {
      i18nKey: "nav.items",
      to: "backend-items",
      icon: "si si-bag",
    },
    // {
    //   name: "Quotes",
    //   to: "backend-quotes",
    //   icon: "si si-doc",
    // },
    {
      i18nKey: "nav.invoices",
      to: "backend-invoices",
      icon: "si si-note",
    },

    {
      i18nKey: "nav.reports",
      to: "backend-dashboard",
      icon: "si si-speedometer",
    },

  ],

 
};
