<script setup>
/**
 * Minimal authenticated layout for the Library app.
 * Replaces Breeze's default layout, which links to a `profile.edit` route
 * we don't expose (Fortify handles auth, no profile controller is shipped).
 */
import { ref, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Toaster from '@/Components/shared/Toaster.vue';
import { useToasts } from '@/composables/useToasts.js';

const showMobile = ref(false);
const page = usePage();
const toast = useToasts();

const user = computed(() => page.props.auth?.user ?? null);
const permissions = computed(() => page.props.auth?.permissions ?? []);

const can = (perm) => Array.isArray(permissions.value)
  ? permissions.value.includes(perm)
  : !!permissions.value?.includes?.(perm);

// Server-side flash messages still work for traditional Inertia navigations
// (e.g. profile/logout). They're forwarded into the toast queue so we have a
// single notification mechanism — no more competing banners.
watch(
  () => page.props.flash?.status,
  (msg) => { if (msg) toast.success(msg); },
  { immediate: true },
);
watch(
  () => page.props.flash?.error,
  (msg) => { if (msg) toast.error(msg); },
  { immediate: true },
);

function logout() {
  router.post(route('logout'));
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <nav class="bg-white border-b border-gray-200">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex">
            <Link :href="route('dashboard')" class="flex items-center font-semibold text-gray-800">
              📚 Library
            </Link>

            <div class="hidden sm:flex sm:items-center sm:ml-8 space-x-4">
              <Link
                :href="route('dashboard')"
                class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600"
              >Dashboard</Link>

              <Link
                v-if="can('books.view')"
                :href="route('media.index', { type: 'book' })"
                class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600"
              >Books</Link>

              <Link
                v-if="can('authors.view')"
                :href="route('authors.index')"
                class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600"
              >Authors</Link>
            </div>
          </div>

          <div class="hidden sm:flex sm:items-center">
            <span v-if="user" class="text-sm text-gray-600 mr-4">
              {{ user.name }}
            </span>
            <button
              type="button"
              class="text-sm text-gray-600 hover:text-red-600"
              @click="logout"
            >Log out</button>
          </div>

          <div class="flex items-center sm:hidden">
            <button @click="showMobile = !showMobile" class="p-2 text-gray-500 hover:text-gray-700">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <div v-show="showMobile" class="sm:hidden border-t border-gray-200 bg-white">
        <div class="py-2 space-y-1 px-4">
          <Link :href="route('dashboard')" class="block py-2 text-sm text-gray-700">Dashboard</Link>
          <Link v-if="can('books.view')" :href="route('media.index', { type: 'book' })" class="block py-2 text-sm text-gray-700">Books</Link>
          <Link v-if="can('authors.view')" :href="route('authors.index')" class="block py-2 text-sm text-gray-700">Authors</Link>
          <button type="button" class="block py-2 text-sm text-red-600" @click="logout">Log out</button>
        </div>
      </div>
    </nav>

    <header v-if="$slots.header" class="bg-white shadow">
      <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <slot name="header" />
      </div>
    </header>

    <main>
      <slot />
    </main>

    <!-- Single global toast queue for both API responses (pushed from pages
         via useToasts) and Inertia flash messages (forwarded by the watchers
         above). -->
    <Toaster />
  </div>
</template>
