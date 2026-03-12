<script setup>
import { ref, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Tag from "primevue/tag";
import { formatDate } from "../utils/data";

const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 15 });
const displayModal = ref(false);
const selectedAudit = ref(null);
let searchTimeout = null;

const loadAudits = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/audits", {
            params: { page: page, per_page: pagination.value.per_page, q: filter.value },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            console.error(err);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.audits.toast.load_error"),
                life: 3000,
            });
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadAudits(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadAudits(1);
    }, 500);
};

const viewDetails = (audit) => {
    selectedAudit.value = audit;
    displayModal.value = true;
};

const formatModelName = (modelPath) => {
    if (!modelPath) return trans("admin.audits.table.unknown");
    return modelPath.split("\\").pop();
};

const getEventSeverity = (event) => {
    switch (event) {
        case "created":
            return "success";
        case "updated":
            return "info";
        case "deleted":
            return "danger";
        case "restored":
            return "warning";
        default:
            return "secondary";
    }
};

onMounted(() => {
    loadAudits();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.audits.table.title") }}
                        </h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search text-surface-400" />
                            <InputText
                                v-model="filter"
                                :placeholder="$t('admin.audits.table.search_placeholder')"
                                @input="onFilterChange"
                                class="!rounded-lg"
                            />
                        </IconField>
                    </div>
                </template>

                <Column :header="$t('admin.audits.table.date')">
                    <template #body="slotProps">
                        <span class="text-surface-600 text-sm whitespace-nowrap font-medium">
                            {{ formatDate(slotProps.data.created_at) }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.audits.table.user')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.user" class="font-bold text-surface-900">
                            {{ slotProps.data.user.username }}
                        </span>
                        <span v-else class="text-surface-400 italic">{{ $t("admin.audits.table.system") }}</span>
                    </template>
                </Column>

                <Column :header="$t('admin.audits.table.action')">
                    <template #body="slotProps">
                        <Tag
                            :severity="getEventSeverity(slotProps.data.event)"
                            :value="slotProps.data.event.toUpperCase()"
                        />
                    </template>
                </Column>

                <Column :header="$t('admin.audits.table.entity')">
                    <template #body="slotProps">
                        <span class="text-sm font-semibold text-surface-700">
                            {{ formatModelName(slotProps.data.auditable_type) }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.audits.table.ip')">
                    <template #body="slotProps">
                        <span class="text-surface-500 font-mono text-sm">
                            {{ slotProps.data.ip_address }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.audits.table.details')" :exportable="false" style="min-width: 5rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-external-link"
                            text
                            rounded
                            severity="info"
                            class="hover:!bg-sky-50"
                            @click="viewDetails(slotProps.data)"
                            v-tooltip.top="$t('admin.audits.table.inspect')"
                        />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-8 text-surface-500">
                        <i class="pi pi-history text-4xl mb-4 text-surface-300"></i>
                        <p class="m-0">{{ $t("admin.audits.table.empty") }}</p>
                    </div>
                </template>
            </DataTable>

            <Paginator
                v-if="pagination.total > 0"
                :rows="pagination.per_page"
                :totalRecords="pagination.total"
                @page="onPage"
                class="mt-4 border-t border-surface-100 pt-4"
            />
        </div>

        <Dialog
            v-model:visible="displayModal"
            :header="$t('admin.audits.modal.title')"
            :style="{ width: '700px', maxWidth: '90vw' }"
            modal
            :draggable="false"
        >
            <div v-if="selectedAudit" class="pt-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        class="p-3 bg-red-50/50 text-red-900 rounded-xl border border-red-100 overflow-x-auto shadow-sm"
                    >
                        <h4
                            class="font-bold mb-2 text-sm border-b border-red-200/50 pb-2 text-red-700 flex items-center gap-2"
                        >
                            <i class="pi pi-minus-circle"></i> {{ $t("admin.audits.modal.old_values") }}
                        </h4>
                        <pre class="text-xs m-0 font-mono leading-relaxed">{{
                            selectedAudit.old_values && Object.keys(selectedAudit.old_values).length > 0
                                ? JSON.stringify(selectedAudit.old_values, null, 2)
                                : $t("admin.audits.modal.no_old_data")
                        }}</pre>
                    </div>

                    <div
                        class="p-3 bg-green-50/50 text-green-900 rounded-xl border border-green-100 overflow-x-auto shadow-sm"
                    >
                        <h4
                            class="font-bold mb-2 text-sm border-b border-green-200/50 pb-2 text-green-700 flex items-center gap-2"
                        >
                            <i class="pi pi-plus-circle"></i> {{ $t("admin.audits.modal.new_values") }}
                        </h4>
                        <pre class="text-xs m-0 font-mono leading-relaxed">{{
                            selectedAudit.new_values && Object.keys(selectedAudit.new_values).length > 0
                                ? JSON.stringify(selectedAudit.new_values, null, 2)
                                : $t("admin.audits.modal.no_new_data")
                        }}</pre>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-surface-100 text-xs text-surface-500 flex flex-col gap-2">
                    <div class="flex items-start gap-2">
                        <i class="pi pi-link mt-0.5 text-surface-400"></i>
                        <span
                            ><b class="text-surface-700">{{ $t("admin.audits.modal.url") }}:</b>
                            {{ selectedAudit.url }}</span
                        >
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="pi pi-desktop mt-0.5 text-surface-400"></i>
                        <span class="truncate max-w-full">
                            <b class="text-surface-700">{{ $t("admin.audits.modal.user_agent") }}:</b>
                            {{ selectedAudit.user_agent }}
                        </span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="pi pi-database mt-0.5 text-surface-400"></i>
                        <span>
                            <b class="text-surface-700">{{ $t("admin.audits.modal.entity_id") }}:</b>
                            <span class="font-mono">{{ selectedAudit.auditable_id }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <template #footer> </template>
        </Dialog>
    </div>
</template>
