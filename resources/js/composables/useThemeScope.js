import { computed } from "vue";
import { useTemplateStore } from "@/stores/template";

/**
 * Content teleported to <body> (menus, modals) leaves #page-container, the
 * element that carries the app's one theme class (.dark-mode, BaseLayout.vue).
 * Wrap it in <div class="theme-scope" :class="themeScope"> to give it the same
 * class - and with it the --dark-* tokens and every `.dark-mode .x` rule.
 * .theme-scope is display: contents, so the wrapper itself never paints.
 */
export function useThemeScope() {
  const store = useTemplateStore();
  return computed(() => ({ "dark-mode": !!store.settings.darkMode }));
}
