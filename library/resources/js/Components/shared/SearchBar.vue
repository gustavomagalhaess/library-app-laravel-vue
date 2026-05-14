<script setup>
/**
 * Generic search bar. Emits `search` once the user has typed at least
 * `minLength` characters (default 3 per spec) and pauses typing.
 */
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search…' },
  minLength: { type: Number, default: 3 },
  debounce: { type: Number, default: 300 },
});
const emit = defineEmits(['update:modelValue', 'search']);

const inner = ref(props.modelValue);
let timer = null;

watch(() => props.modelValue, (v) => { inner.value = v; });

function onInput(e) {
  inner.value = e.target.value;
  emit('update:modelValue', inner.value);
  clearTimeout(timer);
  timer = setTimeout(() => {
    const term = (inner.value ?? '').trim();
    if (term.length === 0 || term.length >= props.minLength) {
      emit('search', term);
    }
  }, props.debounce);
}
</script>

<template>
  <form class="flex gap-2" @submit.prevent="submit">
    <input
      :value="inner"
      @input="onInput"
      type="search"
      :placeholder="placeholder"
      class="flex-1 rounded border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
    />
  </form>
  <p v-if="inner && inner.trim().length < minLength" class="text-xs text-gray-500 mt-1">
    Type at least {{ minLength }} characters to search.
  </p>
</template>
