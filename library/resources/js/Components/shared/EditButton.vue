<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  href: { type: String, default: null }, // when given, renders an Inertia link
});
const emit = defineEmits(['click']);
</script>

<template>
  <component
    :is="href ? Link : 'button'"
    :href="href || undefined"
    type="button"
    class="inline-flex items-center rounded-md border border-transparent
        bg-orange-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest
        text-white transition duration-150 ease-in-out
        hover:bg-orange-500 focus:outline-none focus:ring-2
        focus:ring-orange-500 focus:ring-offset-2
        active:bg-orange-700"
    @click="(e) => $emit('click', e)"
  >
    Edit
  </component>
</template>
