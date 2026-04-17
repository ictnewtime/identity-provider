<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import SessionTable from "../../components/SessionTable.vue";

defineOptions({ layout: AdminLayout });

defineProps({
    sessions: Array,
});
</script>

<template>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-surface-900 tracking-tight">
                {{ $t("admin.sessions.title") }}
            </h1>
        </div>
    </div>

    <SessionTable ref="tableRef" :sessions="sessions" />
</template>
