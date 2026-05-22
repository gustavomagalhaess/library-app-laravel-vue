<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SuccessButton from '@/Components/SuccessButton.vue';
import DangerButton from "@/Components/DangerButton.vue";

/**
 * The Book create / edit form, designed to live inside a modal on the
 * Books index page.
 *
 * The form is purely presentational:
 *   - `@submit` emits a ready-to-send FormData payload (and a meta object)
 *     for the parent page to POST.
 *   - `@cancel` closes the modal.
 *   - Validation errors come back in via the `:errors` prop after the
 *     parent's axios call resolves with a 422.
 */
const props = defineProps({
  mode: { type: String, default: 'create' }, // 'create' | 'edit'
  book: { type: Object, default: null },
  // Server-side validation errors keyed by field name, e.g. { title: '…' }.
  errors: { type: Object, default: () => ({}) },
  // Indicates the parent's axios call is in flight — disables the submit
  // button to prevent double-clicks.
  processing: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'cancel']);

const isEdit = computed(() => props.mode === 'edit');

// Each row in the chosen-authors list is either an existing { id, name }
// or a placeholder { id: null, name: 'Newly typed' }.
// Authors live on the shared media row (the pivot is media_authors).
const initialAuthors = (props.book?.media?.authors ?? []).map((a) => ({ id: a.id, name: a.name }));
const initialClassifications = (props.book?.media?.classifications ?? []).map((c) => c.id);

const form = reactive({
  // title / publication_year live on the shared media row.
  title: props.book?.media?.title ?? '',
  publication_year: props.book?.media?.publication_year ?? new Date().getFullYear(),
  pages: props.book?.pages ?? 0,
  file: null,
  // Internal: we hold the chosen authors as objects, then split them into
  // ids[]/new[] right before submit.
  _selected: initialAuthors,
  _classificationIds: initialClassifications,
});

// --- Classifications -------------------------------------------------------
const allClassifications = ref([]);
const loadingClassifications = ref(true);

onMounted(async () => {
  try {
    const { data } = await axios.get(route('classifications.index'));
    allClassifications.value = data?.data ?? [];
  } finally {
    loadingClassifications.value = false;
  }
});

function isClassificationSelected(id) {
  return form._classificationIds.includes(id);
}

function toggleClassification(id) {
  const idx = form._classificationIds.indexOf(id);
  if (idx === -1) {
    form._classificationIds.push(id);
  } else {
    form._classificationIds.splice(idx, 1);
  }
}

// --- Author auto-complete --------------------------------------------------
const queryText = ref('');
const suggestions = ref([]);
let typeaheadTimer = null;

function onTypeahead() {
  clearTimeout(typeaheadTimer);
  if (queryText.value.trim().length < 3) {
    suggestions.value = [];
    return;
  }

  typeaheadTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('authors.search'), { params: { q: queryText.value } });
      const chosen = new Set(form._selected.filter(a => a.id).map(a => a.id));
      suggestions.value = (data?.data ?? []).filter(a => !chosen.has(a.id));
    } catch (e) {
      suggestions.value = [];
    }
  }, 250);
}

function pickSuggestion(author) {
  form._selected.push({ id: author.id, name: author.name });
  queryText.value = '';
  suggestions.value = [];
}

function addNewAuthor() {
  const name = queryText.value.trim();
  if (!name) return;
  // Avoid duplicates
  if (form._selected.some(a => !a.id && a.name.toLowerCase() === name.toLowerCase())) return;
  form._selected.push({ id: null, name });
  queryText.value = '';
  suggestions.value = [];
}

function removeAuthor(idx) {
  form._selected.splice(idx, 1);
}

// --- Submit ----------------------------------------------------------------
function submit() {
  // Pack the chosen authors into authors[ids][] / authors[new][] and ship
  // everything as multipart/form-data so the file upload rides along.
  const data = new FormData();
  data.append('title', form.title ?? '');
  data.append('publication_year', String(form.publication_year ?? ''));
  data.append('pages', String(form.pages ?? ''));
  if (form.file) {
    data.append('file', form.file);
  }
  form._selected
    .filter(a => a.id)
    .forEach((a, i) => data.append(`authors[ids][${i}]`, String(a.id)));
  form._selected
    .filter(a => !a.id)
    .forEach((a, i) => data.append(`authors[new][${i}]`, a.name));

  form._classificationIds
    .forEach((id, i) => data.append(`classifications[ids][${i}]`, String(id)));

  emit('submit', data);
}

function onFileChange(e) {
  form.file = e.target.files[0] ?? null;
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-5">
    <div>
      <InputLabel value="Title"/>
      <TextInput
        v-model="form.title"
        class="mt-1 block w-full"
      />
      <InputError :message="errors.title" class="mt-1"/>
    </div>

    <div>
      <InputLabel value="Publication year"/>
      <TextInput
        v-model.number="form.publication_year"
        type="number"
        class="mt-1 block w-full"
      />
      <InputError :message="errors.publication_year" class="mt-1"/>
    </div>

    <div>
      <InputLabel value="Authors"/>

      <ul v-if="form._selected.length" class="flex flex-wrap gap-2 mb-2">
        <li
          v-for="(a, idx) in form._selected"
          :key="(a.id ?? 'new-') + a.name"
          class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-sm text-indigo-700"
        >
          {{ a.name }}
          <span v-if="!a.id" class="text-[10px] uppercase tracking-wide text-indigo-500">new</span>
          <button type="button" class="text-indigo-400 hover:text-red-600" @click="removeAuthor(idx)">×</button>
        </li>
      </ul>

      <div class="relative">
        <TextInput
          v-model="queryText"
          @input="onTypeahead"
          @keydown.enter.prevent="addNewAuthor"
          type="text"
          placeholder="Type to search (3+ chars) or add a new author"
          class="block w-full"
        />
        <ul
          v-if="suggestions.length"
          class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded shadow max-h-56 overflow-auto"
        >
          <li
            v-for="s in suggestions"
            :key="s.id"
            class="px-3 py-2 text-sm cursor-pointer hover:bg-indigo-50"
            @click="pickSuggestion(s)"
          >{{ s.name }}</li>
        </ul>
      </div>
      <button
        v-if="queryText.trim().length >= 3 && !suggestions.length"
        type="button"
        class="mt-2 text-sm text-indigo-600 hover:underline"
        @click="addNewAuthor"
      >+ Add "{{ queryText.trim() }}" as a new author</button>

      <InputError :message="errors.authors" class="mt-1"/>
    </div>

    <div>
      <InputLabel value="Pages"/>
      <TextInput
        v-model.number="form.pages"
        type="number"
        class="mt-1 block w-full"
      />
    </div>

    <div>
      <InputLabel value="Classification"/>
      <div v-if="loadingClassifications" class="mt-1 text-sm text-gray-400">Loading…</div>
      <div v-else class="mt-1 flex flex-wrap gap-2">
        <button
          v-for="c in allClassifications"
          :key="c.id"
          type="button"
          class="px-3 py-1 rounded-full text-sm border transition-colors"
          :class="isClassificationSelected(c.id)
            ? 'bg-indigo-600 border-indigo-600 text-white'
            : 'bg-white border-gray-300 text-gray-700 hover:border-indigo-400'"
          @click="toggleClassification(c.id)"
        >{{ c.code }} — {{ c.name }}</button>
      </div>
      <InputError :message="errors.classifications" class="mt-1"/>
    </div>

    <div>
      <InputLabel>
        File (PDF) <span v-if="isEdit" class="text-xs text-gray-400">— leave empty to keep current file</span>
      </InputLabel>
      <input
        type="file"
        accept="application/pdf"
        @change="onFileChange"
        class="mt-1 block w-full text-sm"
      />
      <InputError :message="errors.file" class="mt-1"/>
    </div>

    <div class="flex justify-end gap-2">
      <DangerButton type="button" @click="emit('cancel')">
          Cancel
      </DangerButton>
      <SuccessButton
        type="submit"
        :disabled="processing"
      >
          {{ isEdit ? 'Save changes' : 'Add book' }}
      </SuccessButton>
    </div>
  </form>
</template>
