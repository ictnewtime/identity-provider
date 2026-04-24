<script setup>
import { ref, computed, watch } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import Button from "primevue/button";
import Dialog from "primevue/dialog";
import MultiSelect from "primevue/multiselect";
import InputText from "primevue/inputtext";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import { Icon } from "@iconify/vue";

const props = defineProps({
    visible: { type: Boolean, required: true },
    itemSelected: { type: Object, default: () => null },
});

const emit = defineEmits(["update:visible", "user-success", "user-error"]);
const toast = useToast();
const loading = ref(false);
const filterRoles = ref("");
let searchTimeout = null;
const pagination = ref({ data: [], total: 0, per_page: 50 });
const selectedRoles = ref([]);

const roleOptions = computed(() => {
    const rolesArray = pagination.value?.data || [];
    const mappedServerRoles = rolesArray.map((role) => {
        const providerName = role?.provider?.name || "";
        return {
            ...role,
            displayName: `${role.name} (${providerName})`,
        };
    });

    const allOptionsMap = new Map();
    selectedRoles.value.forEach((role) => {
        allOptionsMap.set(role.id, role);
    });

    mappedServerRoles.forEach((role) => {
        allOptionsMap.set(role.id, role);
    });

    return Array.from(allOptionsMap.values());
});

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadRoles(1);
    }, 500);
};

const loadRoles = () => {
    loading.value = true;

    window.axios
        .get("/admin/v1/roles", {
            params: {
                page: 1,
                per_page: pagination.value.per_page,
                q: filterRoles.value,
                show_deleted: false,
            },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            console.error(err);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.roles.toast.load_error"),
                life: 3000,
            });
            emit("user-error", err);
        })
        .finally(() => {
            loading.value = false;
        });
};

const addRolesToUserIds = (userIds) => {
    if (!userIds || userIds.length === 0 || selectedRoles.value.length === 0) {
        return;
    }
    loading.value = true;

    const formattedRoles = selectedRoles.value.map((role) => {
        return {
            role_id: role.id,
            provider_id: role.provider_id || role.provider?.id,
        };
    });

    window.axios
        .post("/admin/v1/provider-user-roles/bulk-add", {
            user_ids: userIds,
            roles: formattedRoles,
        })
        .then((res) => {
            selectedRoles.value = [];
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.users.toast.add_roles_success"),
                life: 3000,
            });
            emit("user-success", res.data);
        })
        .catch((err) => {
            console.error(err);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.toast.add_roles_error"),
                life: 3000,
            });
            emit("user-error", err);
        })
        .finally(() => {
            loading.value = false;
        });
};

watch(
    () => props.visible,
    (isVisible) => {
        if (isVisible) {
            filterRoles.value = "";
            loadRoles();
        }
    }
);
</script>

<template>
    <Dialog
        :visible="props.visible"
        @update:visible="$emit('update:visible', $event)"
        :header="$t('admin.users.roles.add_title')"
        :style="{ width: '600px' }"
        modal
        :draggable="false"
    >
        <div class="flex flex-col gap-2 mt-4">
            <IconField iconPosition="left">
                <InputIcon class="pi pi-search" />
                <InputText
                    id="search-role"
                    v-model="filterRoles"
                    @input="onFilterChange"
                    :placeholder="$t('admin.roles.search_placeholder')"
                    class="w-full"
            /></IconField>

            <div class="flex flex-col gap-2 mt-4">
                <label for="roles-multiselect" class="font-medium text-surface-900 dark:text-surface-0">
                    {{ $t("admin.roles.select_roles") }}
                </label>

                <MultiSelect
                    id="roles-multiselect"
                    v-model="selectedRoles"
                    :options="roleOptions"
                    optionLabel="displayName"
                    :filter="false"
                    :loading="loading"
                    :maxSelectedLabels="3"
                    :selectedItemsLabel="$t('admin.roles.items_selected')"
                    :disabled="roleOptions.length === 0 && !loading"
                    class="w-full"
                >
                    <template #empty>
                        <span v-if="loading">{{ $t("common.loading") }}</span>
                        <span v-else>{{ $t("common.no_records_found") }}</span>
                    </template>
                </MultiSelect>
            </div>
        </div>
        <template #footer>
            <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="$emit('update:visible', false)" />
            <Button
                :label="$t('admin.roles.btn_add_roles')"
                icon="pi pi-check"
                sclass="shadow-sm"
                @click="addRolesToUserIds(itemSelected.ids)"
                autofocus
            />
        </template>
    </Dialog>
</template>
