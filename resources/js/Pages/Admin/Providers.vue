<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import ProviderTable from "../../components/ProviderTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    providers: Array,
});

const tableRef = ref(null);

const handleNewProviderClick = () => {
    tableRef.value?.openCreateModal();
};
</script>

<template>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-surface-900 tracking-tight">
                {{ $t("admin.providers.title") }}
            </h1>
        </div>
        <Button :label="$t('admin.providers.new_provider')" icon="pi pi-plus" @click="handleNewProviderClick" />
    </div>

    <ProviderTable ref="tableRef" :providers="providers" />
</template>
