<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import ProviderTable from "../../components/ProviderTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    providers: Array,
});

// Creiamo un riferimento per agganciarci al componente figlio
const tableRef = ref(null);

// Quando clicchiamo il bottone nel padre, diciamo al figlio di aprire la modale
const handleNewProviderClick = () => {
    tableRef.value?.openCreateModal();
};
</script>

<template>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-surface-900">
                {{ $t("admin.providers.title") }}
            </h1>
        </div>
        <Button :label="$t('admin.providers.new_provider')" icon="pi pi-plus" @click="handleNewProviderClick" />
    </div>

    <ProviderTable ref="tableRef" :providers="providers" />
</template>
