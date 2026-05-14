<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Generic "add new" button.
 *
 * Pass :href to render as an Inertia <Link> (legacy callers that navigate to
 * a dedicated create page); omit it to render as a <button> that emits
 * `click` — used by the list pages where create is hosted in a modal.
 */
defineProps({
  href: { type: String, default: null },
});
defineEmits(['click']);
</script>

<template>
  <component
    :is="href ? Link : 'button'"
    :href="href || undefined"
    type="button"
    class="inline-flex items-center rounded-md border border-transparent
        bg-green-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest
        text-white transition duration-150 ease-in-out
        hover:bg-green-500 focus:outline-none focus:ring-2
        focus:ring-green-500 focus:ring-offset-2
        active:bg-green-700"
    @click="(e) => $emit('click', e)"
  >
    <slot />
  </component>
</template>
