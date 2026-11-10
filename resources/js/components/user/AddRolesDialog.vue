<script setup>
import { ref, computed, watch } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import Button from "primevue/button";
import Dialog from "primevue/dialog";
import MultiSelect from "primevue/multiselect";

const props = defineProps({
    visible: { type: Boolean, required: true },
    // { users: [{ id, username, ... }] } — gli utenti selezionati nella tabella: i ruoli si copiano da loro
    itemSelected: { type: Object, default: () => null },
});

const emit = defineEmits(["update:visible", "user-success", "user-error"]);
const toast = useToast();
const loadingUsers = ref(false);
const loadingRoles = ref(false);
const loadingSourceRoles = ref(false);
const saving = ref(false);
const filterUsers = ref("");
const filterRoles = ref("");
let usersDebounce = null;
let rolesDebounce = null;
let sourceRolesRequest = 0;
const paginationUsers = ref({ data: [], total: 0, per_page: 100 });
const paginationRoles = ref({ data: [], total: 0, per_page: 100 });
// gli utenti destinatari: partono vuoti, li sceglie l'operatore
const selectedUsers = ref([]);
const selectedRoles = ref([]);

const toRoleOption = (role) => ({
    id: Number(role.id),
    displayName: `${role.name} (${role.provider_name ?? role?.provider?.name ?? ""})`,
    provider_id: role.provider_id,
});

const mergeById = (...lists) => {
    const map = new Map();
    lists.flat().forEach((item) => map.set(item.id, item));
    return Array.from(map.values());
};

// gli utenti selezionati restano fra le opzioni anche se la ricerca non li restituisce
const userOptions = computed(() => mergeById(selectedUsers.value, paginationUsers.value?.data || []));

const roleOptions = computed(() =>
    mergeById(selectedRoles.value, (paginationRoles.value?.data || []).map(toRoleOption))
);

const canSubmit = computed(
    () => selectedUsers.value.length > 0 && selectedRoles.value.length > 0 && !saving.value
);

const loadRoles = () => {
    loadingRoles.value = true;
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
            loadingRoles.value = false;
        });
};

const loadUsers = () => {
    loadingUsers.value = true;
    window.axios
        .get("/admin/v1/users", {
            params: {
                page: 1,
                per_page: paginationUsers.value.per_page,
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
            loadingUsers.value = false;
        });
};

/**
 * Precompila i ruoli degli utenti selezionati in tabella (gli utenti sorgente). Avviene una volta,
 * all'apertura: scegliere i destinatari non cambia i ruoli.
 */
const loadSourceRoles = (sourceUsers) => {
    const userIds = sourceUsers.map((user) => user.id);
    if (userIds.length === 0) {
        return;
    }

    // scarta la risposta di un'apertura precedente
    const requestId = ++sourceRolesRequest;
    loadingSourceRoles.value = true;
    window.axios
        .get("/admin/v1/users/roles", { params: { user_ids: userIds } })
        .then((res) => {
            if (requestId !== sourceRolesRequest) return;
            selectedRoles.value = res.data.map(toRoleOption);
        })
        .catch((err) => {
            if (requestId !== sourceRolesRequest) return;
            console.error(err);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.roles.users_roles_load_error"),
                life: 3000,
            });
            emit("user-error", err);
        })
        .finally(() => {
            if (requestId === sourceRolesRequest) loadingSourceRoles.value = false;
        });
};

const onFilterUsersChange = (event) => {
    clearTimeout(usersDebounce);
    usersDebounce = setTimeout(() => {
        filterUsers.value = event.value;
        loadUsers();
    }, 500);
};

const onFilterRolesChange = (event) => {
    clearTimeout(rolesDebounce);
    rolesDebounce = setTimeout(() => {
        filterRoles.value = event.value;
        loadRoles();
    }, 500);
};

const addRolesToUsers = () => {
    if (!canSubmit.value) {
        return;
    }
    saving.value = true;

    window.axios
        .post("/admin/v1/provider-user-roles/bulk-add", {
            user_ids: selectedUsers.value.map((user) => user.id),
            roles: selectedRoles.value.map((role) => ({ role_id: role.id, provider_id: role.provider_id })),
            is_all_selected: false,
            only_user_deleted: false,
        })
        .then((res) => {
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
            saving.value = false;
        });
};

watch(
    () => props.visible,
    (isVisible) => {
        if (!isVisible) return;
        filterRoles.value = "";
        filterUsers.value = "";
        selectedRoles.value = [];
        selectedUsers.value = [];
        loadRoles();
        loadUsers();
        loadSourceRoles(props.itemSelected?.users || []);
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
        <div class="flex flex-col gap-2 mb-4">
            <label for="roles-multiselect" class="font-medium text-surface-900 dark:text-surface-0">
                {{ $t("admin.users.roles.selected_roles") }}
            </label>
            <small class="text-surface-500">{{ $t("admin.users.roles.users_roles_hint") }}</small>

            <MultiSelect
                inputId="roles-multiselect"
                data-testid="add-roles-roles"
                dataKey="id"
                v-model="selectedRoles"
                :options="roleOptions"
                optionLabel="displayName"
                :filter="true"
                @filter="onFilterRolesChange"
                :loading="loadingRoles || loadingSourceRoles"
                :maxSelectedLabels="3"
                :selectedItemsLabel="$t('admin.roles.items_selected')"
                :placeholder="$t('admin.roles.search_placeholder')"
                fluid
            >
                <template #empty>
                    <span v-if="loadingRoles">{{ $t("common.loading") }}</span>
                    <span v-else>{{ $t("common.no_records_found") }}</span>
                </template>
            </MultiSelect>
        </div>

        <div class="flex flex-col gap-2">
            <label for="users-multiselect" class="font-medium text-surface-900 dark:text-surface-0">
                {{ $t("admin.users.roles.target_users") }}
            </label>
            <MultiSelect
                inputId="users-multiselect"
                data-testid="add-roles-users"
                dataKey="id"
                v-model="selectedUsers"
                :options="userOptions"
                optionLabel="username"
                :placeholder="$t('admin.roles.form.user_placeholder')"
                :loading="loadingUsers"
                :filter="true"
                @filter="onFilterUsersChange"
                :maxSelectedLabels="5"
                :selectedItemsLabel="$t('admin.users.roles.users_selected')"
                display="chip"
                fluid
            />
        </div>
        <template #footer>
            <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="$emit('update:visible', false)" />
            <Button
                data-testid="add-roles-submit"
                :label="$t('admin.users.roles.btn_submit')"
                icon="pi pi-check"
                class="shadow-sm"
                :loading="saving"
                :disabled="!canSubmit"
                @click="addRolesToUsers()"
            />
        </template>
    </Dialog>
</template>
