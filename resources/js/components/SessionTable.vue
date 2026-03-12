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
import { formatDate } from "../utils/data";

const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayDeleteModal = ref(false);
const sessionToDelete = ref(null);
let searchTimeout = null;

const loadSessions = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/sessions", {
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
                detail: trans("admin.sessions.toast.load_error"),
                life: 3000,
            });
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadSessions(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadSessions(1);
    }, 500);
};

const confirmDelete = (session) => {
    sessionToDelete.value = session;
    displayDeleteModal.value = true;
};

const deleteSession = () => {
    if (!sessionToDelete.value) return;

    window.axios
        .delete(`/admin/v1/sessions/${sessionToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.sessions.toast.delete_success"),
                life: 3000,
            });
            // Aspettiamo 1,5 secondi per far leggere il toast, poi aggiorniamo la tabella
            // (e ci facciamo buttare fuori se era la nostra sessione)
            setTimeout(() => {
                loadSessions();
                sessionToDelete.value = null;
            }, 800);
            // faccio passare 3 secondi prima di ricaricare la pagina
            setTimeout(() => {
                window.location.reload();
            }, 4000);
        })
        .catch((error) => {
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.sessions.toast.delete_error"),
                life: 3000,
            });
        });
};

onMounted(() => {
    loadSessions();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.sessions.table.title") }}
                        </h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search text-surface-400" />
                            <InputText
                                v-model="filter"
                                :placeholder="$t('admin.sessions.table.search_placeholder')"
                                @input="onFilterChange"
                                class="!rounded-lg"
                            />
                        </IconField>
                    </div>
                </template>

                <Column :header="$t('common.id')">
                    <template #body="slotProps">
                        <span
                            class="inline-block max-w-[80px] sm:max-w-[120px] truncate text-surface-400 text-sm font-mono cursor-help"
                            v-tooltip.top="slotProps.data.id"
                        >
                            {{ slotProps.data.id }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.sessions.table.username')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.user" class="text-surface-900 font-bold">
                            {{ slotProps.data.user.username }}
                        </span>
                        <span v-else class="text-surface-400 italic">
                            {{ $t("admin.sessions.table.unknown_user") }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.sessions.table.provider')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.provider" class="text-surface-700 font-medium">
                            {{ slotProps.data.provider.name }}
                        </span>
                        <span v-else class="text-surface-400 italic">
                            {{ $t("admin.sessions.table.no_provider") }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.sessions.table.ip')">
                    <template #body="slotProps">
                        <span class="text-surface-600 font-mono text-sm">
                            {{ slotProps.data.ip_address }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.sessions.table.user_agent')">
                    <template #body="slotProps">
                        <span
                            class="inline-block max-w-[80px] sm:max-w-[120px] md:max-w-[150px] lg:max-w-[200px] truncate text-surface-500 text-sm cursor-help"
                            v-tooltip.top="slotProps.data.user_agent"
                        >
                            {{ slotProps.data.user_agent }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('admin.sessions.table.last_modified')">
                    <template #body="slotProps">
                        <span class="text-surface-500 text-sm whitespace-nowrap">
                            {{ formatDate(slotProps.data.updated_at) }}
                        </span>
                    </template>
                </Column>

                <Column :header="$t('common.actions')" :exportable="false" style="min-width: 5rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-power-off"
                            text
                            rounded
                            severity="danger"
                            class="hover:!bg-red-50"
                            @click="confirmDelete(slotProps.data)"
                            v-tooltip.top="$t('admin.sessions.table.terminate')"
                        />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-8 text-surface-500">
                        <i class="pi pi-sitemap text-4xl mb-4 text-surface-300"></i>
                        <p class="m-0">{{ $t("admin.sessions.table.empty") }}</p>
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
            v-model:visible="displayDeleteModal"
            :header="$t('admin.sessions.delete.title')"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <div class="flex items-center gap-4 pt-2">
                <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
                <span v-if="sessionToDelete" class="text-surface-700">
                    {{ $t("admin.sessions.delete.prompt") }}
                    <b class="text-surface-900">{{
                        sessionToDelete.user?.username || $t("admin.sessions.delete.this_user")
                    }}</b>
                    <span v-if="sessionToDelete.provider">
                        {{ $t("admin.sessions.delete.on_domain") }}
                        <b class="text-surface-900">{{ sessionToDelete.provider.domain }}</b
                        >?
                    </span>
                    <span v-else>?</span>
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button
                    :label="$t('admin.sessions.delete.btn_terminate')"
                    icon="pi pi-power-off"
                    severity="danger"
                    @click="deleteSession"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
