<script setup>
import { ref, watch, computed } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";
import { usePassword } from "@/Composables/usePassword";

import { Icon } from "@iconify/vue";
import InputGroup from "primevue/inputgroup";
import InputGroupAddon from "primevue/inputgroupaddon";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import DatePicker from "primevue/datepicker";

import ToggleSwitch from "primevue/toggleswitch";
import Button from "primevue/button";
import Message from "primevue/message";

const props = defineProps({
    userSelected: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const loading = ref(false);

const form = ref({
    id: null,
    username: "",
    email: "",
    name: "",
    surname: "",
    password: "",
    password_confirmation: "",
    password_expires_at: null,
    enabled: true,
});

const formItems = ref({
    password: {
        visible: false,
    },
});

const errors = ref({
    username: "",
    email: "",
    name: "",
    surname: "",
    password: "",
    password_confirmation: "",
    password_expires_at: "",
    form: "",
});

const isEditMode = computed(() => !!props.userSelected);

const pwdComputed = computed(() => form.value.password);
const confirmComputed = computed(() => form.value.password_confirmation);

const { requirements, strength, strengthColorClass, strengthTextColorClass, strengthText, generatePassword } =
    usePassword(pwdComputed, confirmComputed);

const handleGeneratePassword = () => {
    const newPwd = generatePassword();
    form.value.password = newPwd;
    form.value.password_confirmation = newPwd;
};

const togglePasswordVisibility = () => {
    formItems.value.password.visible = !formItems.value.password.visible;
};

const resetForm = () => {
    form.value = {
        id: null,
        username: "",
        email: "",
        name: "",
        surname: "",
        password: "",
        password_confirmation: "",
        password_expires_at: null,
        enabled: true,
    };
    resetErrors();
};

const resetErrors = () => {
    Object.keys(errors.value).forEach((key) => (errors.value[key] = ""));
};

const clearPasswords = () => {
    form.value.password = "";
    form.value.password_confirmation = "";
    errors.value.password = "";
    errors.value.password_confirmation = "";
};

const validateEmail = (email) => {
    const re = /\S+@\S+\.\S+/;
    return re.test(email);
};

const validate = () => {
    resetErrors();
    let isValid = true;

    if (!form.value.username) {
        errors.value.username = trans("admin.users.form.validate.username.mandatory");
        isValid = false;
    }
    if (!form.value.email || !validateEmail(form.value.email)) {
        errors.value.email = trans("admin.users.form.validate.email.mandatory");
        isValid = false;
    }
    if (!form.value.name) {
        errors.value.name = trans("admin.users.form.validate.name.mandatory");
        isValid = false;
    }
    if (!form.value.surname) {
        errors.value.surname = trans("admin.users.form.validate.surname.mandatory");
        isValid = false;
    }

    if (!isEditMode.value && !form.value.password) {
        errors.value.password = trans("admin.users.form.validate.password.mandatory");
        isValid = false;
    }

    if (form.value.password && form.value.password !== form.value.password_confirmation) {
        errors.value.password_confirmation = trans("admin.users.form.validate.password_confirmation.mandatory");
        isValid = false;
    }

    return isValid;
};

const fetchUser = async (id) => {
    loading.value = true;
    try {
        const res = await window.axios.get(`/admin/v1/users/${id}`);
        const data = res.data;
        const parsedExpiresAt = data.password_expires_at ? new Date(data.password_expires_at.replace(" ", "T")) : null;
        form.value = {
            id: data.id,
            username: data.username,
            email: data.email,
            name: data.name,
            surname: data.surname,
            enabled: data.enabled == 1,
            password: "",
            password_confirmation: "",
            password_expires_at: parsedExpiresAt,
        };
    } catch (err) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.users.toast.load_user_error"),
            life: 3000,
        });
        emit("item-error", err);
    } finally {
        loading.value = false;
    }
};

const submit = async () => {
    if (!validate()) return;

    loading.value = true;

    const url = isEditMode.value ? `/admin/v1/users/${form.value.id}` : "/admin/v1/users";
    const method = isEditMode.value ? "put" : "post";

    const payload = {
        username: form.value.username,
        email: form.value.email,
        name: form.value.name,
        surname: form.value.surname,
        password_expires_at: form.value.password_expires_at,
        enabled: form.value.enabled,
    };

    if (form.value.password) {
        payload.password = form.value.password;
        payload.password_confirmation = form.value.password_confirmation;
    }

    try {
        await window.axios[method](url, payload);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: isEditMode.value
                ? trans("admin.users.toast.detail_updated")
                : trans("admin.users.toast.detail_created"),
            life: 3000,
        });
        emit("item-saved");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.users.toast.submit_error"),
            life: 3000,
        });
        emit("item-error", error);

        if (error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            Object.keys(backendErrors).forEach((key) => {
                if (errors.value[key] !== undefined) {
                    errors.value[key] = backendErrors[key][0];
                }
            });
        }

        if (error.response?.data?.message) {
            errors.value.form = error.response.data.message;
        }
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.userSelected,
    (newVal) => {
        if (newVal && newVal.id) {
            fetchUser(newVal.id);
        } else {
            resetForm();
        }
    },
    { immediate: true }
);
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6 w-full pt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label for="username" class="font-medium text-surface-900">{{
                    $t("admin.users.form.username_label")
                }}</label>
                <InputText id="username" v-model="form.username" :invalid="!!errors.username" fluid />
                <Message v-if="errors.username" severity="error" size="small" variant="simple">
                    {{ errors.username }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="email" class="font-medium text-surface-900">{{ $t("admin.users.form.email_label") }}</label>
                <InputText id="email" type="email" v-model="form.email" :invalid="!!errors.email" fluid />
                <Message v-if="errors.email" severity="error" size="small" variant="simple">
                    {{ errors.email }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="name" class="font-medium text-surface-900">{{ $t("admin.users.form.name_label") }}</label>
                <InputText id="name" v-model="form.name" :invalid="!!errors.name" fluid />
                <Message v-if="errors.name" severity="error" size="small" variant="simple">
                    {{ errors.name }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="surname" class="font-medium text-surface-900">{{
                    $t("admin.users.form.surname_label")
                }}</label>
                <InputText id="surname" v-model="form.surname" :invalid="!!errors.surname" fluid />
                <Message v-if="errors.surname" severity="error" size="small" variant="simple">
                    {{ errors.surname }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <label for="password" class="font-medium text-surface-900">{{
                            $t("admin.users.form.password_label")
                        }}</label>
                        <i
                            v-if="isEditMode"
                            class="pi pi-question-circle"
                            style="color: var(--p-yellow-500); cursor: pointer; font-size: 0.875rem"
                            v-tooltip.top="{ value: $t('admin.users.form.password_tip'), escape: true }"
                        ></i>
                        <i
                            v-if="isEditMode && form.password.length > 0"
                            class="pi pi-eraser"
                            style="cursor: pointer; font-size: 0.875rem"
                            v-tooltip.top="{ value: $t('admin.users.form.btn_clear'), escape: true }"
                            @click="clearPasswords"
                        ></i>
                    </div>
                </div>

                <InputGroup>
                    <Password
                        inputId="password"
                        name="password"
                        v-model="form.password"
                        text
                        :placeholder="$t('admin.users.form.password_placeholder')"
                        :invalid="!!errors.password"
                        :feedback="true"
                        fluid
                        :pt="{
                            pcInputText: {
                                root: {
                                    type: formItems.password.visible ? 'text' : 'password',
                                },
                            },
                        }"
                    >
                        <template #content>
                            <div class="w-full sm:w-[22rem] p-1">
                                <div class="mb-4">
                                    <div class="w-full bg-surface-200 h-1.5 rounded-full overflow-hidden">
                                        <div
                                            class="h-full transition-all duration-300"
                                            :class="strengthColorClass"
                                            :style="{ width: `${(strength / 5) * 100}%` }"
                                        ></div>
                                    </div>
                                    <small class="text-surface-700 mt-1 block font-medium">
                                        {{ $t("auth.password_strength") }}:
                                        <span class="font-bold" :class="strengthTextColorClass">{{
                                            strengthText
                                        }}</span>
                                    </small>
                                </div>

                                <h6 class="text-sm font-bold mb-2 text-surface-900">
                                    {{ $t("auth.password_requirements") }}
                                </h6>
                                <ul class="text-sm flex flex-col gap-1 m-0 p-0 list-none">
                                    <li
                                        :class="
                                            requirements.minLength ? 'text-green-600 font-medium' : 'text-surface-700'
                                        "
                                    >
                                        <i
                                            class="pi mr-2 text-xs"
                                            :class="requirements.minLength ? 'pi-check-circle' : 'pi-circle'"
                                        ></i>
                                        {{ $t("auth.req_min_length") }}
                                    </li>
                                    <li
                                        :class="
                                            requirements.hasUpperCase
                                                ? 'text-green-600 font-medium'
                                                : 'text-surface-700'
                                        "
                                    >
                                        <i
                                            class="pi mr-2 text-xs"
                                            :class="requirements.hasUpperCase ? 'pi-check-circle' : 'pi-circle'"
                                        ></i>
                                        {{ $t("auth.req_upper") }}
                                    </li>
                                    <li
                                        :class="
                                            requirements.hasLowerCase
                                                ? 'text-green-600 font-medium'
                                                : 'text-surface-700'
                                        "
                                    >
                                        <i
                                            class="pi mr-2 text-xs"
                                            :class="requirements.hasLowerCase ? 'pi-check-circle' : 'pi-circle'"
                                        ></i>
                                        {{ $t("auth.req_lower") }}
                                    </li>
                                    <li
                                        :class="
                                            requirements.hasNumber ? 'text-green-600 font-medium' : 'text-surface-700'
                                        "
                                    >
                                        <i
                                            class="pi mr-2 text-xs"
                                            :class="requirements.hasNumber ? 'pi-check-circle' : 'pi-circle'"
                                        ></i>
                                        {{ $t("auth.req_number") }}
                                    </li>
                                    <li
                                        :class="
                                            requirements.hasSpecialChar
                                                ? 'text-green-600 font-medium'
                                                : 'text-surface-700'
                                        "
                                    >
                                        <i
                                            class="pi mr-2 text-xs"
                                            :class="requirements.hasSpecialChar ? 'pi-check-circle' : 'pi-circle'"
                                        ></i>
                                        {{ $t("auth.req_special") }}
                                    </li>
                                </ul>
                            </div>
                        </template>
                    </Password>

                    <InputGroupAddon class="p-0 border-none">
                        <Button
                            type="button"
                            severity="secondary"
                            :icon="formItems.password.visible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                            v-tooltip.top="null"
                            @click="togglePasswordVisibility"
                        />
                    </InputGroupAddon>
                    <InputGroupAddon class="p-0 border-none">
                        <Button
                            type="button"
                            severity="secondary"
                            @click="handleGeneratePassword"
                            v-tooltip.top="$t('auth.generate_random_btn')"
                        >
                            <template #icon>
                                <Icon icon="mdi:dice-multiple-outline" width="24" height="24" />
                            </template>
                        </Button>
                    </InputGroupAddon>
                </InputGroup>

                <Message v-if="errors.password" severity="error" size="small" variant="simple">
                    {{ errors.password }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="password_confirmation" class="font-medium text-surface-900">
                    {{ $t("admin.users.form.password_confirmation_label") }}
                </label>

                <InputGroup>
                    <Password
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                        :invalid="!!errors.password_confirmation"
                        :feedback="false"
                        fluid
                        :pt="{
                            pcInputText: {
                                root: {
                                    type: formItems.password.visible ? 'text' : 'password',
                                },
                            },
                        }"
                    />
                    <InputGroupAddon class="p-0 border-none">
                        <Button
                            type="button"
                            severity="secondary"
                            :icon="formItems.password.visible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                            v-tooltip.top="null"
                            @click="togglePasswordVisibility"
                        />
                    </InputGroupAddon>
                </InputGroup>
                <Message v-if="errors.password_confirmation" severity="error" size="small" variant="simple">
                    {{ errors.password_confirmation }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="password_expires_at" class="font-medium text-surface-900">
                    {{ $t("admin.users.form.password_expires_at_label") }}
                </label>
                <InputGroup>
                    <DatePicker
                        id="password_expires_at"
                        v-model="form.password_expires_at"
                        :invalid="!!errors.password_expires_at"
                        :showTime="true"
                        :showIcon="true"
                        dateFormat="dd/mm/yy"
                    />
                </InputGroup>
                <Message v-if="errors.password_expires_at" severity="error" size="small" variant="simple">
                    {{ errors.password_expires_at }}
                </Message>
            </div>

            <div class="flex items-center gap-3 mt-2">
                <label for="enabled" class="font-medium text-surface-900">{{
                    $t("admin.users.form.enabled_label")
                }}</label>
                <ToggleSwitch id="enabled" v-model="form.enabled" />
            </div>
        </div>

        <Message v-if="errors.form" severity="error" size="small" variant="simple">
            {{ errors.form }}
        </Message>

        <div class="flex justify-end gap-3 mt-4 border-t border-surface-200 pt-4">
            <Button
                type="button"
                :label="$t('common.reset')"
                severity="secondary"
                text
                icon="pi pi-refresh"
                @click="resetForm"
                :disabled="loading"
            />
            <Button
                type="submit"
                :label="isEditMode ? $t('common.save_changes') : $t('admin.users.form.btn_create')"
                icon="pi pi-check"
                :loading="loading"
            />
        </div>
    </form>
</template>
