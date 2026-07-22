import { createRouter, createWebHistory } from "vue-router";
import { useTemplateStore } from "@/stores/template.js";

import NProgress from "nprogress/nprogress.js";

// Main layouts
import LayoutBackend from "@/layouts/variations/BackendStarter.vue";
import LayoutSimple from "@/layouts/variations/Simple.vue";
import LayoutFront from "@/layouts/variations/Front.vue";

// Frontend: Landing
const Landing = () => import("@/views/starter/LandingView.vue");

const AuthSignIn     = () => import("@/views/SignIn.vue");
const AuthSignOut    = () => import("@/views/SignOut.vue");
const ForgotPassword = () => import("@/views/ForgotPassword.vue");
const ResetPassword  = () => import("@/views/ResetPassword.vue");
const Onboarding     = () => import("@/views/admin/Onboarding.vue");

// Backend: Dashboard




// Backend: customer
const Customers = () => import("@/views/admin/customers/index.vue");
const CreateCustomer = () => import("@/views/admin/customers/create.vue");
const EditCustomer = () => import("@/views/admin/customers/edit.vue");
const ShowCustomer = () => import("@/views/admin/customers/show.vue");

// Backend: quote
const Quotes = () => import("@/views/admin/quotes/index.vue");
const CreateQuote = () => import("@/views/admin/quotes/create.vue");
const EditQuote = () => import("@/views/admin/quotes/edit.vue");

// Backend: invoice
const Invoices = () => import("@/views/admin/invoices/index.vue");
const CreateInvoice = () => import("@/views/admin/invoices/create.vue");
const EditInvoice = () => import("@/views/admin/invoices/edit.vue");

// Backend: items
const Items = () => import("@/views/admin/items/index.vue");
const CreateItem = () => import("@/views/admin/items/create.vue");
const EditItem = () => import("@/views/admin/items/edit.vue");
const Profile = () => import("@/views/admin/profile.vue");
const Settings = () => import("@/views/admin/settings.vue");
const Templates = () => import("@/views/admin/templates.vue");
const Dashboard = () => import("@/views/admin/dashboard.vue");
const Subscription = () => import("@/views/admin/subscription.vue");
const Payments = () => import("@/views/admin/payments.vue");

// Set all routes
const routes = [
  {
    path: "/",
    component: LayoutFront,
    children: [
      {
        path: "",
        name: "landing",
        component: Landing,
      },
    ],
  },
  {
    path: "/admin",
    redirect: "/dashboard",
    component: LayoutBackend,
    children: [
      {
        path: "dashboard",
        name: "backend-dashboard",
        component: Dashboard,
      },
      {
        path: "customers",
        name: "backend-pages-generic-profile",
        component: Customers,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "customers",
        name: "auth-signout",
        component: Customers,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "customers",
        name: "backend-customers",
        component: Customers,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "customers/new",
        name: "backend-create-customer",
        component: CreateCustomer,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "customers/:id",
        name: "backend-show-customer",
        component: ShowCustomer,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "customers/edit/:id",
        name: "backend-edit-customer",
        component: EditCustomer,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "quotes",
        name: "backend-quotes",
        component: Quotes,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "quotes/new",
        name: "backend-create-quote",
        component: CreateQuote,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "quotes/edit/:id",
        name: "backend-edit-quote",
        component: EditQuote,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "invoices",
        name: "backend-invoices",
        component: Invoices,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "invoices/new",
        name: "backend-create-invoice",
        component: CreateInvoice,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "invoices/edit/:id",
        name: "backend-edit-invoice",
        component: EditInvoice,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "items",
        name: "backend-items",
        component: Items,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "items/new",
        name: "backend-create-item",
        component: CreateItem,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "items/edit/:id",
        name: "backend-edit-item",
        component: EditItem,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "profile",
        name: "backend-profile",
        component: Profile,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "settings",
        name: "backend-settings",
        component: Settings,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "templates",
        name: "backend-templates",
        component: Templates,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "subscription",
        name: "backend-subscription",
        component: Subscription,
        meta: {
          requiresAuth: true
        }
      },
      {
        path: "payments",
        name: "backend-payments",
        component: Payments,
        meta: {
          requiresAuth: true
        }
      },
    ],
  },
    /*
  |
  |--------------------------------------------------------------------------
  | Auth Routes
  |--------------------------------------------------------------------------
  |
  */
  {
    path: "/admin",
    component: LayoutSimple,
    children: [
      {
        path: "login",
        name: "auth-signin",
        component: AuthSignIn,
      },
      {
        path: "logout",
        name: "auth-signout",
        component: AuthSignOut,
      },
      {
        path: "forgot-password",
        name: "auth-forgot-password",
        component: ForgotPassword,
      },
      {
        path: "reset-password",
        name: "auth-reset-password",
        component: ResetPassword,
      },
      {
        path: "onboarding",
        name: "onboarding",
        component: Onboarding,
      },
    ],
  },
];

// Create Router
const router = createRouter({
  history: createWebHistory(),
  linkActiveClass: "active",
  linkExactActiveClass: "active",
  scrollBehavior() {
    return { left: 0, top: 0 };
  },
  routes,
});


// NProgress
/*eslint-disable no-unused-vars*/
NProgress.configure({ showSpinner: false });

router.beforeResolve((to, from, next) => {
  NProgress.start();
  next();
});

router.afterEach((to, from) => {
  NProgress.done();
});
/*eslint-enable no-unused-vars*/

// Auth + billing navigation guard
const AUTH_ROUTES  = ['auth-signin', 'auth-signout', 'auth-forgot-password', 'auth-reset-password'];
const BYPASS_NAMES = [...AUTH_ROUTES, 'onboarding', 'backend-subscription', 'landing'];

router.beforeEach((to, from, next) => {
  const store = useTemplateStore();
  const isLoggedIn = !!store.app.accessToken;

  // Unauthenticated → send to login (except public routes)
  if (!isLoggedIn && !AUTH_ROUTES.includes(to.name) && to.name !== 'landing') {
    return next({ name: 'auth-signin' });
  }

  if (isLoggedIn) {
    // Redirect authenticated users away from login page
    if (to.name === 'auth-signin') {
      return next({ name: 'backend-dashboard' });
    }

    // Onboarding not done → lock to onboarding page
    if (!store.billing.onboardingCompleted && to.name !== 'onboarding' && !AUTH_ROUTES.includes(to.name)) {
      return next({ name: 'onboarding' });
    }

    // Trial expired or canceled → lock to subscription page (allow onboarding through)
    const lockedStatuses = ['expired', 'canceled'];
    if (
      store.billing.onboardingCompleted &&
      lockedStatuses.includes(store.billing.subscriptionStatus) &&
      !BYPASS_NAMES.includes(to.name)
    ) {
      return next({ name: 'backend-subscription' });
    }
  }

  next();
});

export default router;
