<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import UserTable from "../../components/UserTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    users: Array,
});

// Creiamo un riferimento per agganciarci al componente figlio
const tableRef = ref(null);

// Quando clicchiamo il bottone nel padre, diciamo al figlio di aprire la modale
const handleNewUserClick = () => {
    tableRef.value?.openCreateModal();
};
</script>

<template>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-surface-900">
                {{ $t("admin.users.title") }}
            </h1>
        </div>
        <Button :label="$t('admin.users.new_user')" icon="pi pi-plus" @click="handleNewUserClick" />
    </div>

    <UserTable ref="tableRef" :users="users" />
</template>
