<script setup>
import { ref, computed, watch, toRaw } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import Button from "primevue/button";
import Dialog from "primevue/dialog";
import Select from "primevue/select";
import MultiSelect from "primevue/multiselect";
import InputText from "primevue/inputtext";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import { Icon } from "@iconify/vue";
import { result } from "lodash";

const props = defineProps({
    visible: { type: Boolean, required: true },
    itemSelected: { type: Object, default: () => null },
    isAllSelected: { type: Boolean, default: () => null },
    onlyUsersDeleted: { type: Boolean, default: () => null },
});

const emit = defineEmits(["update:visible", "user-success", "user-error"]);
const toast = useToast();
const loading = ref(false);
const filterUsers = ref("");
const filterRoles = ref("");
let searchTimeout = null;
const paginationUsers = ref({ data: [], total: 0, per_page: 100 });
const paginationRoles = ref({ data: [], total: 0, per_page: 100 });
const selectedRoles = ref([]);
const selectedUser = ref([]);

const userOptions = computed(() => {
    const usersArray = paginationUsers.value?.data || [];
    const mappedUsers = usersArray.map((user) => ({
        ...user,
        displayName: user.username,
    }));

    const allOptionsMap = new Map();
    // selectedUsers.value.forEach((user) => {
    //     allOptionsMap.set(user.id, user);
    // });

    mappedUsers.forEach((user) => {
        allOptionsMap.set(user.id, user);
    });

    return Array.from(allOptionsMap.values());
});

const roleOptions = computed(() => {
    const rolesArray = paginationRoles.value?.data || [];
    const mappedRoles = rolesArray.map((role) => {
        const providerName = role?.provider?.name || "";
        return {
            id: Number(role.id),
            displayName: `${role.name} (${providerName})`,
            provider_id: role.provider_id,
        };
    });

    const allOptionsMap = new Map();
    selectedRoles.value.forEach((role) => {
        if (!allOptionsMap.has(role.id)) {
            allOptionsMap.set(role.id, role);
        }
    });

    mappedRoles.forEach((role) => {
        allOptionsMap.set(role.id, role);
    });

    return Array.from(allOptionsMap.values());
});

let debounceTimeout = null;

const onFilterRolesChange = (event) => {
    if (debounceTimeout) {
        clearTimeout(debounceTimeout);
    }

    debounceTimeout = setTimeout(() => {
        filterRoles.value = event.value;
        loadRoles();
    }, 500);
};

const loadRoles = () => {
    loading.value = true;

    window.axios
        .get("/admin/v1/roles", {
            params: {
                page: 1,
                per_page: paginationRoles.value.per_page,
                q: filterRoles.value,
                show_deleted: false,
            },
        })
        .then((res) => {
            paginationRoles.value = res.data;
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

const onFilterUsersChange = (event) => {
    if (debounceTimeout) {
        clearTimeout(debounceTimeout);
    }

    debounceTimeout = setTimeout(() => {
        filterUsers.value = event.value;
        loadUsers();
    }, 500);
};

const onUserChange = (event) => {
    selectedUser.value = event.value;
    let userRoles = [];
    window.axios
        .get(`/admin/v1/users/${event.value.id}/roles`)
        .then((res) => {
            const newRoles = res.data.map((role) => ({
                id: Number(role.id),
                displayName: `${role.name} (${role.provider_name})`,
                provider_id: role.provider_id,
            }));
            const currentRoles = selectedRoles.value.map((role) => ({
                id: Number(role.id),
                displayName: role.displayName,
                provider_id: role.provider_id,
            }));
            const uniqueMap = new Map();
            currentRoles.forEach((role) => uniqueMap.set(role.id, role));
            newRoles.forEach((role) => uniqueMap.set(role.id, role));
            selectedRoles.value = Array.from(uniqueMap.values());
        })
        .catch((err) => {
            console.error(err);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.roles.toast.load_user_role_error"),
                life: 3000,
            });
            emit("user-error", err);
        });
};

const loadUsers = () => {
    loading.value = true;
    window.axios
        .get("/admin/v1/users", {
            params: {
                page: 1,
                per_page: 1000,
                q: filterUsers.value,
                show_deleted: false,
            },
        })
        .then((res) => {
            paginationUsers.value = res.data;
        })
        .catch((err) => {
            console.error(err);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.toast.load_error"),
                life: 3000,
            });
            emit("user-error", err);
        })
        .finally(() => {
            loading.value = false;
        });
};

const addRolesToUserIds = () => {
    const userIds = props.itemSelected.ids;
    const isAllSelected = props.isAllSelected;
    const onlyUsersDeleted = props.onlyUsersDeleted;
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
            is_all_selected: isAllSelected,
            only_user_deleted: onlyUsersDeleted,
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
            filterUsers.value = "";
            loadRoles();
            loadUsers();
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
        <div class="flex flex-col gap-2">
            <label for="roles-multiselect" class="font-medium text-surface-900 dark:text-surface-0">
                {{ $t("admin.users.roles.add_roles_from_user") }}
            </label>
            <Select
                id="user-select"
                v-model="selectedUser"
                :options="userOptions"
                optionLabel="username"
                :placeholder="$t('admin.roles.form.user_placeholder')"
                :loading="loading"
                :filter="true"
                @filter="onFilterUsersChange"
                fluid
                @change="onUserChange"
            />
        </div>

        <div class="flex flex-col gap-2">
            <label for="roles-multiselect" class="font-medium text-surface-900 dark:text-surface-0">
                {{ $t("admin.roles.select_roles") }}
            </label>

            <MultiSelect
                id="roles-multiselect"
                dataKey="id"
                v-model="selectedRoles"
                :options="roleOptions"
                optionLabel="displayName"
                :filter="true"
                @filter="onFilterRolesChange"
                :loading="loading"
                :maxSelectedLabels="3"
                :selectedItemsLabel="$t('admin.roles.items_selected')"
                :disabled="roleOptions.length === 0 && !loading"
                :placeholder="$t('admin.roles.search_placeholder')"
                class="w-full"
            >
                <template #empty>
                    <span v-if="loading">{{ $t("common.loading") }}</span>
                    <span v-else>{{ $t("common.no_records_found") }}</span>
                </template>
            </MultiSelect>
        </div>
        <template #footer>
            <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="$emit('update:visible', false)" />
            <Button
                :label="$t('admin.roles.btn_add_roles')"
                icon="pi pi-check"
                sclass="shadow-sm"
                @click="addRolesToUserIds()"
                autofocus
            />
        </template>
    </Dialog>
</template>
