import { reactive } from 'vue';

/**
 * Module-level reactive toast store.
 *
 * `useToasts()` always returns the same singleton, so any component can push
 * a toast and the <Toaster /> mounted in the layout will render it.
 *
 *   const toast = useToasts();
 *   toast.success('Book added.');
 *   toast.error('Failed to delete book.');
 *
 * For queued-job workflows we also need persistent "loading" toasts that
 * later resolve to success/error/with-action. Those are created with
 * `toast.loading(message)` and updated via the returned `update()` /
 * `resolve()` helpers:
 *
 *   const t = toast.loading('Saving…');
 *   t.update({ message: 'Almost there…' });
 *   t.resolve('success', { message: 'Saved.' });
 *   // or with an action button (used by the prepared-download flow):
 *   t.resolve('success', { message: 'Ready.', action: { label: 'Download', href: url } });
 */

const state = reactive({
  toasts: [],
});

let nextId = 1;
const DEFAULT_TIMEOUT_MS = 4000;

function pushRaw(opts) {
  const id = nextId++;
  const toast = {
    id,
    type: opts.type ?? 'info',
    message: opts.message ?? '',
    // null = persistent; a number = auto-dismiss after that many ms.
    timeout: opts.timeout ?? DEFAULT_TIMEOUT_MS,
    loading: opts.loading ?? false,
    action: opts.action ?? null,
  };
  state.toasts.push(toast);

  if (toast.timeout && toast.timeout > 0) {
    setTimeout(() => dismiss(id), toast.timeout);
  }
  return id;
}

function dismiss(id) {
  const idx = state.toasts.findIndex((t) => t.id === id);
  if (idx !== -1) state.toasts.splice(idx, 1);
}

function update(id, patch) {
  const t = state.toasts.find((x) => x.id === id);
  if (!t) return;
  Object.assign(t, patch);
}

function loading(message) {
  // Persistent, non-dismissing toast with a spinner. The caller is expected
  // to resolve it via the returned handle.
  const id = pushRaw({ type: 'info', message, timeout: 0, loading: true });
  return {
    id,
    update: (patch) => update(id, patch),
    resolve: (type, opts = {}) => {
      // Flip to a terminal style; default to auto-dismiss unless the caller
      // attached an action (we want users to keep the "Download" link until
      // they click it).
      const hasAction = Boolean(opts.action);
      update(id, {
        type,
        message: opts.message ?? '',
        loading: false,
        action: opts.action ?? null,
      });
      const timeout = opts.timeout ?? (hasAction ? 0 : DEFAULT_TIMEOUT_MS);
      if (timeout > 0) {
        setTimeout(() => dismiss(id), timeout);
      }
    },
    dismiss: () => dismiss(id),
  };
}

export function useToasts() {
  return {
    toasts: state.toasts,
    success: (message, timeout) => pushRaw({ type: 'success', message, timeout }),
    error:   (message, timeout) => pushRaw({ type: 'error',   message, timeout }),
    info:    (message, timeout) => pushRaw({ type: 'info',    message, timeout }),
    loading,
    dismiss,
  };
}
