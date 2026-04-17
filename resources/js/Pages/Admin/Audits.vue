<script setup>
import { ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AuditTable from "../../components/AuditTable.vue";

defineOptions({ layout: AdminLayout });

defineProps({
    audits: Array,
});

const tableRef = ref(null);
</script>

<template>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-surface-900 tracking-tight">
                {{ $t("admin.audits.title") }}
            </h1>
        </div>
    </div>

    <AuditTable ref="tableRef" :audits="audits" />
</template>
