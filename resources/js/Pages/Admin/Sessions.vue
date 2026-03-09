<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import SessionTable from "../../components/SessionTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    sessions: Array,
});

// Creiamo un riferimento per agganciarci al componente figlio
const tableRef = ref(null);

// Quando clicchiamo il bottone nel padre, diciamo al figlio di aprire la modale
</script>

<template>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-surface-900">
                {{ $t("admin.sessions.title") }}
            </h1>
        </div>
    </div>

    <SessionTable ref="tableRef" :sessions="sessions" />
</template>
