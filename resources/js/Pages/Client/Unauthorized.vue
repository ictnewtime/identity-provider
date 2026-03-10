<script setup>
import { Head } from "@inertiajs/vue3";
import Card from "primevue/card";
</script>

<template>
    <Head title="Accesso Negato" />

    <div class="min-h-screen bg-surface-50 flex items-center justify-center p-4">
        <Card class="w-full max-w-md shadow-sm border border-surface-200">
            <template #title>
                <div class="flex flex-col items-center justify-center gap-3 text-red-600 mb-2 mt-4">
                    <i class="pi pi-shield text-5xl"></i>
                    <h1 class="text-2xl font-bold text-surface-900 m-0 text-center">Accesso Negato</h1>
                </div>
            </template>

            <template #content>
                <p class="text-surface-600 text-center leading-relaxed mb-4">
                    Non disponi delle autorizzazioni necessarie per accedere alla risorsa richiesta. Se ritieni si
                    tratti di un errore, contatta l'amministratore di sistema.
                </p>
            </template>
        </Card>
    </div>
</template>
