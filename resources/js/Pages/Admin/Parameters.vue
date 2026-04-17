<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import ParameterTable from "../../components/ParameterTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    parameters: Array,
});

const tableRef = ref(null);

const handleNewParameterClick = () => {
    tableRef.value?.openCreateModal();
};
</script>

<template>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-surface-900 tracking-tight">
                {{ $t("admin.parameters.title") }}
            </h1>
        </div>
        <Button :label="$t('admin.parameters.new_parameter')" icon="pi pi-plus" @click="handleNewParameterClick" />
    </div>

    <ParameterTable ref="tableRef" :parameters="parameters" />
</template>
