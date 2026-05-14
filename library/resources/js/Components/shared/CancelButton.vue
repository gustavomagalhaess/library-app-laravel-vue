<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Generic cancel button.
 *
 * Pass :href to render as an Inertia <Link> that navigates back to the
 * given URL; omit it to render as a <button> that emits `click` — used by
 * the modal-hosted forms.
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
    class="px-4 py-2 rounded text-sm text-gray-700 hover:bg-gray-100"
    @click="(e) => $emit('click', e)"
  >
    <slot />
  </component>
</template>
