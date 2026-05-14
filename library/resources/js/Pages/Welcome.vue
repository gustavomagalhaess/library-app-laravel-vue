<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

function handleImageError() {
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="Welcome" />
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <div
            class="relative flex min-h-screen flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white"
        >
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <header
                    class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3"
                >
                    <div class="flex lg:col-start-2 lg:justify-center">
                        <svg
                            class="h-12 w-auto text-white lg:h-20 lg:text-[#FF2D20]"
                            version="1.1"
                            id="Layer_1"
                            xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                            viewBox="0 0 392.598 392.598"
                            xml:space="preserve"
                        >
                            <path style="fill:#194F82;" d="M381.737,178.36c-12.024,0-21.786-9.826-21.786-21.786c0-6.012-4.848-10.925-10.925-10.925h-24.824
                                l-95.289-90.246c-1.164-17.002-15.321-30.513-32.582-30.513c-17.778,0-32.323,14.287-32.711,32l-95.095,88.695H43.636
                                c-6.012,0-10.925,4.848-10.925,10.925c0,12.024-9.826,21.786-21.786,21.786C4.913,178.295,0,183.143,0,189.22v134.853
                                c0,6.012,4.848,10.925,10.925,10.925c12.024,0,21.786,9.826,21.786,21.786c0,6.012,4.848,10.925,10.925,10.925h305.325
                                c6.012,0,10.925-4.849,10.925-10.925c0-12.024,9.826-21.786,21.786-21.786c6.012,0,10.925-4.848,10.925-10.925V189.22
                                C392.598,183.208,387.749,178.36,381.737,178.36z M196.331,46.675c6.012,0,10.925,4.848,10.925,10.925s-4.848,10.925-10.925,10.925
                                s-10.925-4.848-10.925-10.925S190.319,46.675,196.331,46.675z M171.766,79.127c6.012,6.853,14.739,11.184,24.566,11.184
                                c10.15,0,19.265-4.655,25.212-11.895l70.917,67.168H100.525L171.766,79.127z M370.812,314.505
                                c-15.321,3.943-27.345,15.968-31.289,31.289H53.204c-3.943-15.321-16.097-27.345-31.354-31.289V198.723
                                c15.321-3.943,27.345-15.968,31.289-31.289h286.319c3.943,15.321,15.968,27.345,31.289,31.289v115.782H370.812z"/>
                            <path style="fill:#FFC10D;" d="M67.62,324.008c-5.689-9.891-13.964-18.166-23.984-23.984v-86.885
                                                           c9.891-5.689,18.166-13.964,23.984-23.984h257.487c5.689,9.891,13.964,18.166,23.984,23.984v86.949
                                                           c-9.891,5.689-18.166,13.964-23.984,23.984H67.62V324.008z"/>
                            <g>
                                <path style="fill:#194F82;" d="M196.008,249.147c-0.388-11.442-6.271-14.998-19.329-14.933h-16.937v44.8h9.956v-14.287h6.853
                                    l9.891,14.287h12.283l-11.313-15.968C193.228,260.913,196.008,256.323,196.008,249.147z M177.067,256.065h-7.24v-13.253h7.499
                                    c5.042,0.129,8.663,0.711,8.663,6.335C185.794,254.578,183.208,256,177.067,256.065z"/>
                                                            <path style="fill:#194F82;" d="M290.457,249.147c-0.388-11.442-6.271-14.998-19.329-14.933H254.19v44.8h9.956v-14.287h6.853
                                    l9.891,14.287h12.283l-11.313-15.968C287.612,260.913,290.457,256.323,290.457,249.147z M271.451,256.065h-7.24v-13.253h7.499
                                    c5.042,0.129,8.663,0.711,8.663,6.335C280.242,254.578,277.592,256,271.451,256.065z"/>
                            </g>
                            <path style="fill:#56ACE0;" d="M196.331,68.525c6.012,0,10.925-4.848,10.925-10.925c0-6.012-4.848-10.925-10.925-10.925
                                                           s-10.925,4.848-10.925,10.925S190.319,68.525,196.331,68.525z"/>
                            <g>
                                <polygon style="fill:#194F82;" points="68.137,234.214 58.182,234.214 58.182,279.014 87.208,279.014 87.208,270.158 68.137,270.158 	"/>
                                    <rect x="94.578" y="234.214" style="fill:#194F82;" width="9.956" height="44.8"/>
                                    <path style="fill:#194F82;" d="M142.287,255.741c4.913-2.069,6.723-5.495,6.594-9.632c0-2.909-1.034-5.495-3.038-7.822
            c-3.232-2.844-7.564-4.202-12.8-4.008h-17.455v44.8h19.653c6.206,0,10.537-1.422,12.994-4.202c2.133-2.392,3.232-5.172,3.232-8.275
            c0-2.844-0.84-5.172-2.457-7.111C147.265,257.616,145.131,256.388,142.287,255.741z M125.608,242.683h4.784
            c2.909,0,5.042,0.323,6.271,0.905c1.228,0.646,1.875,1.939,1.875,3.943s-0.711,3.232-2.004,3.879
            c-1.293,0.646-3.426,0.905-6.206,0.905h-4.719L125.608,242.683L125.608,242.683z M138.925,269.511
            c-1.487,0.711-3.556,1.099-6.4,1.099h-6.853v-10.343h5.883c3.297,0,5.818,0.323,7.24,1.034c1.552,0.711,2.263,2.004,2.263,4.073
            C141.059,267.442,140.412,268.8,138.925,269.511z"/>
                                <path style="fill:#194F82;" d="M219.992,234.214l-19.394,44.8h10.667l4.202-9.632h18.747l4.137,9.632h10.667l-19.394-44.8H219.992z
         M219.216,260.59l5.624-13.059l5.56,13.059H219.216z"/>
                                <polygon style="fill:#194F82;" points="313.988,250.893 304.291,234.214 293.495,234.214 309.01,261.301 309.01,279.014
        318.966,279.014 318.966,261.301 334.481,234.214 323.685,234.214 	"/>
                            </g>
                        </svg>
                    </div>
                    <nav v-if="canLogin" class="-mx-3 flex flex-1 justify-end">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                        >
                            Dashboard
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Log in
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Register
                            </Link>
                        </template>
                    </nav>
                </header>

                <main class="mt-6">
                    <div class="grid gap-6 lg:grid-cols-1 lg:gap-8">
                        <div
                            id="docs-card"
                            class="flex flex-col items-start gap-6 overflow-hidden rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] md:row-span-3 lg:p-10 lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]"
                        >
                            <div
                                class="relative flex items-center gap-6 lg:items-end"
                            >
                                <div
                                    id="docs-card-content"
                                    class="flex items-start gap-6 lg:flex-col"
                                >

                                    <div class="pt-3 sm:pt-5 lg:pt-0">
                                        <h2
                                            class="text-xl font-semibold text-black dark:text-white"
                                        >
                                            Library Application
                                        </h2>

                                        <p class="mt-4 text-sm/relaxed">
                                            This is a library application intended to keep track of a book collection. It
                                            is coded in Laravel/Vue Framework. It should be able to add books, edit books,
                                            list all books, and upload|download books in this library. This application is
                                            extensible, not only for books but also for other types of media, such as movies
                                            or music. These last two features will be implemented in a future versions of
                                            the application.
                                        </p>
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>
                </main>

                <footer
                    class="py-16 text-center text-sm text-black dark:text-white/70"
                >
                    Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})
                </footer>
            </div>
        </div>
    </div>
</template>
