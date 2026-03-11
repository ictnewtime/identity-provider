<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import UserTable from "../../components/UserTable.vue";
import Button from "primevue/button";

defineOptions({ layout: AdminLayout });

defineProps({
    users: Array,
});

const tableRef = ref(null);

const handleNewUserClick = () => {
    tableRef.value?.openCreateModal();
};
</script>

<template>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-surface-900 tracking-tight">
                {{ $t("admin.users.title") }}
            </h1>
        </div>
        <Button :label="$t('admin.users.new_user')" icon="pi pi-plus" @click="handleNewUserClick" class="shadow-sm" />
    </div>

    <UserTable ref="tableRef" :users="users" />
</template>
