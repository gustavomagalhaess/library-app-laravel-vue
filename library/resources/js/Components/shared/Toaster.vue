<script setup>
import { useToasts } from '@/composables/useToasts.js';

/**
 * Renders the toast queue in the top-right corner.
 *
 * Mounted once by the AuthenticatedLayout — never instantiated directly by
 * pages. To raise a toast, import {@see useToasts} and call
 * `toast.success(message)` / `toast.error(message)`.
 */
const { toasts, dismiss } = useToasts();

function variantClasses(type) {
  switch (type) {
    case 'success':
      return 'bg-green-50 border-green-200 text-green-800';
    case 'error':
      return 'bg-red-50 border-red-200 text-red-800';
    case 'info':
    default:
      return 'bg-blue-50 border-blue-200 text-blue-800';
  }
}
</script>

<template>
  <!-- Fixed, top-right stack. pointer-events: none on the wrapper so the
       toasts don't block clicks elsewhere on the page; the individual toasts
       re-enable pointer events on themselves. -->
  <div
    class="fixed top-4 right-4 z-[60] flex flex-col gap-2 w-80 max-w-[calc(100vw-2rem)] pointer-events-none"
    aria-live="polite"
    aria-atomic="true"
  >
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-x-4"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="t in toasts"
        :key="t.id"
        :class="[
          variantClasses(t.type),
          'pointer-events-auto rounded border shadow-sm px-4 py-2 text-sm flex items-start gap-2',
        ]"
        role="status"
      >
        <span class="flex-1">{{ t.message }}</span>
        <button
          type="button"
          class="text-current opacity-60 hover:opacity-100"
          aria-label="Dismiss"
          @click="dismiss(t.id)"
        >×</button>
      </div>
    </TransitionGroup>
  </div>
</template>
