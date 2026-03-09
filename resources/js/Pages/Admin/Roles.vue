<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import RoleTable from "../../components/RoleTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    roles: Array,
});

// Creiamo un riferimento per agganciarci al componente figlio
const tableRef = ref(null);

// Quando clicchiamo il bottone nel padre, diciamo al figlio di aprire la modale
const handleNewRoleClick = () => {
    tableRef.value?.openCreateModal();
};
</script>

<template>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-surface-900">
                {{ $t("admin.roles.title") }}
            </h1>
        </div>
        <Button :label="$t('admin.roles.new_role')" icon="pi pi-plus" @click="handleNewRoleClick" />
    </div>

    <RoleTable ref="tableRef" :roles="roles" />
</template>
