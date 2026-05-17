<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import SuccessButton from "@/Components/SuccessButton.vue";

const props = defineProps({
    bookCount: { type: Number, required: true },
});
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white/40">
                Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto container sm:px-6 lg:px-8">
                <div class="max-w-64 rounded overflow-hidden shadow-lg">
                    <div class="px-6 py-4 text-white bg-gray-700 opacity-85">
                        <div class="font-bold text-xl mb-2 text-center">Total Books</div>
                        <p class="text-base text-center">
                            {{ bookCount }}
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
