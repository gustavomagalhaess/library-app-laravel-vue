<script setup>
import { useToasts } from '@/composables/useToasts.js';

/**
 * Renders the toast queue in the top-right corner.
 *
 * Toasts are pushed by `useToasts()`. Each toast may:
 *  - be a transient success/error/info (auto-dismissed),
 *  - be a persistent "loading" toast with a spinner (used by queued-job UX),
 *  - carry an action link (used by the prepared-download flow to surface
 *    the signed URL once the job resolves).
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
        <!-- Spinner for "loading" toasts. Pure CSS so we don't pull in an icon lib. -->
        <svg
          v-if="t.loading"
          class="size-4 mt-0.5 animate-spin shrink-0"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12" cy="12" r="10"
            stroke="currentColor"
            stroke-width="4"
          />
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
          />
        </svg>

        <div class="flex-1 min-w-0">
          <p>{{ t.message }}</p>
          <!-- Optional action link — used by the prepared-download flow.
               We deliberately keep the toast around until the user clicks
               the action; that's why the loading composable suppresses the
               auto-dismiss timeout when `action` is present. -->
          <a
            v-if="t.action?.href"
            :href="t.action.href"
            class="mt-1 inline-flex items-center text-xs font-semibold underline underline-offset-2 hover:opacity-80"
            @click="dismiss(t.id)"
          >
            {{ t.action.label || 'Open' }}
          </a>
        </div>

        <button
          v-if="!t.loading"
          type="button"
          class="text-current opacity-60 hover:opacity-100"
          aria-label="Dismiss"
          @click="dismiss(t.id)"
        >×</button>
      </div>
    </TransitionGroup>
  </div>
</template>
