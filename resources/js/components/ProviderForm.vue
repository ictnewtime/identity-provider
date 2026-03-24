<script setup>
import { ref, watch, computed } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n"; // Import obbligatorio per usare le traduzioni nel setup

import { Icon } from "@iconify/vue";
import InputGroup from "primevue/inputgroup";
import InputGroupAddon from "primevue/inputgroupaddon";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import Message from "primevue/message";

const props = defineProps({
    selectedProvider: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const loading = ref(false);

const form = ref({
    id: null,
    name: "",
    url: "",
    domain: "",
    secret_key: "",
    logoutUrl: "",
    protocol: "",
});

const errors = ref({
    name: "",
    url: "",
    domain: "",
    secret_key: "",
    logoutUrl: "",
    protocol: "",
});

const formItems = ref({
    secret_key: {
        visible: false,
    },
});

const isEditMode = computed(() => !!props.selectedProvider);
const showsecret_keyTip = ref(false);

const resetForm = () => {
    form.value = {
        name: "",
        url: "",
        domain: "",
        secret_key: "",
        logoutUrl: "",
        protocol: "",
    };
    resetErrors();
};

const resetErrors = () => {
    errors.value.name = "";
    errors.value.url = "";
    errors.value.domain = "";
    errors.value.secret_key = "";
    errors.value.logoutUrl = "";
    errors.value.protocol = "";
};

const validate = () => {
    resetErrors();
    let isValid = true;

    if (!form.value.name) {
        errors.value.name = trans("admin.providers.form.validate.name.mandatory");
        isValid = false;
    }
    if (!form.value.url) {
        errors.value.url = trans("admin.providers.form.validate.url.mandatory");
        isValid = false;
    }
    if (!form.value.domain) {
        errors.value.domain = trans("admin.providers.form.validate.domain.mandatory");
        isValid = false;
    }

    if (!isEditMode.value && !form.value.secret_key) {
        errors.value.secret_key = trans("admin.providers.form.validate.secret_key.mandatory");
        isValid = false;
    }

    return isValid;
};

const submit = async () => {
    if (!validate()) return;

    loading.value = true;

    const baseUrl = "/admin/v1/providers";
    const url = isEditMode.value ? `${baseUrl}/${form.value.id}` : baseUrl;
    const method = isEditMode.value ? "put" : "post";

    const payload = {
        name: form.value.name,
        url: form.value.url,
        domain: form.value.domain,
        logoutUrl: form.value.logoutUrl,
        protocol: form.value.protocol,
        secret_key: form.value.secret_key,
    };

    try {
        await window.axios[method](url, payload);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: isEditMode.value
                ? trans("admin.providers.toast.detail_updated")
                : trans("admin.providers.toast.detail_created"),
            life: 3000,
        });
        emit("item-saved");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.providers.toast.submit_error"),
            life: 3000,
        });
        emit("item-error", error);

        if (error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            if (backendErrors.name) errors.value.name = backendErrors.name[0];
            if (backendErrors.url) errors.value.url = backendErrors.url[0];
            if (backendErrors.domain) errors.value.domain = backendErrors.domain[0];
            if (backendErrors.secret_key) errors.value.secret_key = backendErrors.secret_key[0];
            if (backendErrors.logoutUrl) errors.value.logoutUrl = backendErrors.logoutUrl[0];
            if (backendErrors.protocol) errors.value.protocol = backendErrors.protocol[0];
        }
    } finally {
        loading.value = false;
    }
};

const fetchProvider = async (id) => {
    loading.value = true;
    try {
        const res = await window.axios.get(`/admin/v1/providers/${id}`);
        const data = res.data.provider;

        form.value = {
            id: data.id,
            name: data.name,
            url: data.url,
            domain: data.domain,
            secret_key: data.secret_key,
            logoutUrl: data.logoutUrl,
            protocol: data.protocol,
        };
    } catch (err) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.providers.toast.load_error"),
            life: 3000,
        });
        emit("item-error", err);
    } finally {
        loading.value = false;
    }
};

const toggleSignatureVisibility = () => {
    formItems.value.secret_key.visible = !formItems.value.secret_key.visible;
};

watch(
    () => props.selectedProvider,
    (newVal) => {
        if (newVal && newVal.id) {
            fetchProvider(newVal.id);
            resetErrors();
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

const parseUrlAndFill = () => {
    if (!form.value.url) return;

    let rawUrl = form.value.url.trim();

    if (!rawUrl.startsWith("http://") && !rawUrl.startsWith("https://")) {
        rawUrl = "http://" + rawUrl;
    }

    try {
        const parsed = new URL(rawUrl);
        form.value.protocol = parsed.protocol.replace(":", "");
        form.value.domain = parsed.hostname;
        form.value.logoutUrl = `${parsed.protocol}//${parsed.host}/logout`;
    } catch (error) {
        toast.add({
            severity: "warn",
            summary: trans("common.warning"),
            detail: trans("admin.providers.toast.invalid_url"),
            life: 3000,
        });
        emit("item-error", error);
    }
};

const generateSecret = () => {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+~|}{[]:;?><,./-=";
    let secret = "";
    const randomValues = new Uint32Array(32);
    window.crypto.getRandomValues(randomValues);

    for (let i = 0; i < 32; i++) {
        secret += chars[randomValues[i] % chars.length];
    }
    form.value.secret_key = secret;
};
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6 w-full pt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label for="name" class="font-medium text-surface-900">{{
                    $t("admin.providers.form.name_label")
                }}</label>
                <InputText
                    id="name"
                    v-model="form.name"
                    :invalid="!!errors.name"
                    :placeholder="$t('admin.providers.form.name_placeholder')"
                    fluid
                />
                <Message v-if="errors.name" severity="error" size="small" variant="simple">
                    {{ errors.name }}
                </Message>
            </div>

            <div class="flex flex-col gap-1 md:col-span-2">
                <label for="url" class="font-medium text-surface-900">
                    {{ $t("admin.providers.form.url_label") }}
                    <i
                        class="pi pi-question-circle"
                        style="color: var(--p-yellow-500); cursor: pointer; font-size: 0.875rem"
                        v-tooltip.top="{
                            value: $t('admin.providers.form.url_tooltip'),
                            escape: true,
                        }"
                    ></i>
                </label>
                <InputGroup class="flex">
                    <InputText
                        id="url"
                        v-model="form.url"
                        class="flex-grow"
                        :placeholder="$t('admin.providers.form.url_placeholder')"
                    />
                    <Button
                        icon="pi pi-sparkles"
                        severity="secondary"
                        @click="parseUrlAndFill"
                        v-tooltip.top="$t('admin.providers.form.url_btn_tooltip')"
                    />
                </InputGroup>
                <Message v-if="errors.url" severity="error" size="small" variant="simple">
                    {{ errors.url }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="domain" class="font-medium text-surface-900">{{
                    $t("admin.providers.form.domain_label")
                }}</label>
                <InputText
                    id="domain"
                    v-model="form.domain"
                    :invalid="!!errors.domain"
                    :placeholder="$t('admin.providers.form.domain_placeholder')"
                    fluid
                />
                <Message v-if="errors.domain" severity="error" size="small" variant="simple">
                    {{ errors.domain }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="protocol" class="font-medium text-surface-900">{{
                    $t("admin.providers.form.protocol_label")
                }}</label>
                <InputText
                    id="protocol"
                    v-model="form.protocol"
                    :placeholder="$t('admin.providers.form.protocol_placeholder')"
                    fluid
                />
            </div>

            <div class="flex flex-col gap-1 md:col-span-2">
                <label for="logoutUrl" class="font-medium text-surface-900"
                    >{{ $t("admin.providers.form.logout_url_label") }}
                    <i
                        class="pi pi-question-circle"
                        style="color: var(--p-yellow-500); cursor: pointer; font-size: 0.875rem"
                        v-tooltip.top="{
                            value: $t('admin.providers.form.logout_url_hint'),
                            escape: true,
                        }"
                    ></i>
                </label>
                <InputText
                    id="logoutUrl"
                    v-model="form.logoutUrl"
                    :invalid="!!errors.logoutUrl"
                    :placeholder="(form.protocol || 'https') + '://' + (form.domain || 'dominio') + '/logout'"
                    fluid
                />
                <Message v-if="errors.logoutUrl" severity="error" size="small" variant="simple">
                    {{ errors.logoutUrl }}
                </Message>
            </div>

            <div class="flex flex-col gap-1 md:col-span-2">
                <div class="flex items-center gap-2">
                    <label for="secret_key" class="font-medium text-surface-900">{{
                        $t("admin.providers.form.secret_key_label")
                    }}</label>
                    <i
                        v-if="isEditMode"
                        class="pi pi-question-circle"
                        style="color: var(--p-yellow-500); cursor: pointer; font-size: 0.875rem"
                        v-tooltip.top="{
                            value: $t('admin.providers.form.secret_key_tooltip'),
                            escape: true,
                        }"
                    ></i>
                </div>

                <Message v-if="isEditMode && showsecret_keyTip" severity="warn" size="small" variant="simple">
                    {{ $t("admin.providers.form.secret_key_tooltip") }}
                </Message>

                <InputGroup class="flex">
                    <Password
                        id="secret_key"
                        v-model="form.secret_key"
                        :invalid="!!errors.secret_key"
                        :placeholder="$t('admin.providers.form.secret_key_placeholder')"
                        :feedback="false"
                        fluid
                        class="flex-grow"
                        :pt="{
                            pcInputText: {
                                root: {
                                    type: formItems.secret_key.visible ? 'text' : 'password',
                                },
                            },
                        }"
                    />
                    <InputGroupAddon class="p-0 border-none">
                        <Button
                            type="button"
                            severity="secondary"
                            :icon="formItems.secret_key.visible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                            v-tooltip.top="null"
                            @click="toggleSignatureVisibility"
                        />
                    </InputGroupAddon>
                    <InputGroupAddon>
                        <Button
                            type="button"
                            severity="secondary"
                            @click="generateSecret"
                            v-tooltip.top="$t('admin.providers.form.secret_key_btn_tooltip')"
                        >
                            <template #icon>
                                <Icon icon="mdi:dice-multiple-outline" width="24" height="24" />
                            </template>
                        </Button>
                    </InputGroupAddon>
                </InputGroup>

                <Message v-if="errors.secret_key" severity="error" size="small" variant="simple">
                    {{ errors.secret_key }}
                </Message>
            </div>
        </div>

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
                :label="isEditMode ? $t('common.save_changes') : $t('admin.providers.form.btn_create')"
                icon="pi pi-check"
                :loading="loading"
            />
        </div>
    </form>
</template>
