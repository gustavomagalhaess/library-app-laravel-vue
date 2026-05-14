import { reactive } from 'vue';

/**
 * Module-level reactive toast store.
 *
 * `useToasts()` always returns the same singleton, so any component can push
 * a toast and the &lt;Toaster /&gt; mounted in the layout will render it.
 *
 *   const toast = useToasts();
 *   toast.success('Book added.');
 *   toast.error('Failed to delete book.');
 */

const state = reactive({
  toasts: [],
});

let nextId = 1;

/** How long each toast stays on screen before fading out. */
const DEFAULT_TIMEOUT_MS = 4000;

function push(type, message, timeout = DEFAULT_TIMEOUT_MS) {
  if (!message) return null;
  const id = nextId++;
  state.toasts.push({ id, type, message });
  if (timeout > 0) {
    setTimeout(() => dismiss(id), timeout);
  }
  return id;
}

function dismiss(id) {
  const idx = state.toasts.findIndex((t) => t.id === id);
  if (idx !== -1) state.toasts.splice(idx, 1);
}

export function useToasts() {
  return {
    toasts: state.toasts,
    success: (message, timeout) => push('success', message, timeout),
    error:   (message, timeout) => push('error',   message, timeout),
    info:    (message, timeout) => push('info',    message, timeout),
    dismiss,
  };
}
