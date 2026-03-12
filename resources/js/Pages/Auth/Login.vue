<script setup>
import { computed, onMounted } from "vue";
import { useForm } from "@inertiajs/vue3";

import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import Message from "primevue/message";
import Image from "primevue/image";

const form = useForm({
    username: "",
    password: "",
    provider_id: null,
    redirect_to: null,
});

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    form.provider_id = urlParams.get("provider_id");
    form.redirect_to = urlParams.get("redirect_to");
});

const isFormValid = computed(() => {
    return form.username && form.password && form.password.length > 2;
});

const submit = () => {
    if (!isFormValid.value) return;
    form.post("/v2/login");
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-surface-50">
        <div class="w-full sm:w-[26rem] p-8 bg-surface-0 border border-surface-200 rounded-xl shadow-lg">
            <div class="flex flex-col items-center mb-8 gap-4">
                <Image src="/images/logo.png" alt="Logo Aziendale" width="480" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4 w-full">
                <div class="flex flex-col gap-1">
                    <InputText
                        name="username"
                        v-model="form.username"
                        type="text"
                        placeholder="Username"
                        fluid
                        :invalid="!!form.errors.username"
                        :disabled="form.processing"
                    />
                    <Message v-if="form.errors.username" severity="error" size="small" variant="simple">
                        {{ form.errors.username }}
                    </Message>
                </div>

                <div class="flex flex-col gap-1">
                    <Password
                        name="password"
                        v-model="form.password"
                        placeholder="Password"
                        :feedback="false"
                        toggleMask
                        fluid
                        :invalid="!!form.errors.password"
                        :disabled="form.processing"
                    />
                    <Message v-if="form.errors.password" severity="error" size="small" variant="simple">
                        {{ form.errors.password }}
                    </Message>
                </div>

                <Message v-if="form.errors.login" severity="error" size="small" variant="simple">
                    {{ form.errors.login }}
                </Message>

                <Button
                    type="submit"
                    label="Login"
                    class="mt-2"
                    fluid
                    :loading="form.processing"
                    :disabled="!isFormValid"
                />
            </form>
        </div>
    </div>
</template>
