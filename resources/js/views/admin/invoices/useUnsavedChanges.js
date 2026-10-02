import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { onBeforeRouteLeave, useRouter } from 'vue-router';

/**
 * Unsaved-change protection for Create Invoice / Create Quote.
 *
 *   const leave = useUnsavedChanges(() => documentFingerprint(state, defaultTax()));
 *
 * - dirty: the fingerprint differs from the one taken when the form opened
 *   (see documentFingerprint.mjs - automatic defaults never count);
 * - browser refresh / tab close / typed URL: the standard `beforeunload`
 *   prompt (the browser shows its own wording - it can't be customised);
 * - in-app navigation (vue-router): blocked and a confirmation modal shown
 *   instead; "Quitter sans enregistrer" then continues to the same place;
 * - markSaved(): call after a SUCCESSFUL save, before redirecting - protection
 *   is dropped so the redirect goes through silently. A failed save leaves
 *   the form dirty and protected.
 */
export function useUnsavedChanges(fingerprint) {
  const router = useRouter();
  const baseline = fingerprint();
  const saved = ref(false);
  const allowLeave = ref(false);
  const pendingRoute = ref(null);

  const isDirty = computed(() => !saved.value && fingerprint() !== baseline);

  function onBeforeUnload(event) {
    if (!isDirty.value || allowLeave.value) return;
    event.preventDefault();
    event.returnValue = ''; // required by some browsers to show the prompt
    return '';
  }

  onMounted(() => window.addEventListener('beforeunload', onBeforeUnload));
  onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload));

  onBeforeRouteLeave((to) => {
    if (!isDirty.value || allowLeave.value) return true;
    pendingRoute.value = to;
    return false;
  });

  function stay() {
    pendingRoute.value = null;
  }

  function leave() {
    const target = pendingRoute.value;
    pendingRoute.value = null;
    allowLeave.value = true;
    if (target) router.push(target.fullPath);
  }

  function markSaved() {
    saved.value = true;
  }

  return {
    isDirty,
    showLeaveConfirm: computed(() => pendingRoute.value !== null),
    stay,
    leave,
    markSaved,
    onBeforeUnload, // exposed for tests
  };
}
