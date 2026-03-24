<script setup>
import { computed, toRef, ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import Password from "primevue/password";
import Button from "primevue/button";
import Message from "primevue/message";
import Image from "primevue/image";
import FloatLabel from "primevue/floatlabel";
import { usePassword } from "@/Composables/usePassword";
import { Icon } from "@iconify/vue";
import InputGroup from "primevue/inputgroup";
import InputGroupAddon from "primevue/inputgroupaddon";

const props = defineProps({
    email: String,
    username: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});

const formItems = ref({
    password: {
        visible: false,
    },
});

const passwordRef = toRef(form, "password");
const confirmPasswordRef = toRef(form, "password_confirmation");

const { requirements, strength, strengthColorClass, strengthTextColorClass, strengthText, isValid, generatePassword } =
    usePassword(passwordRef, confirmPasswordRef);

const isFormValid = computed(() => isValid.value);

const handleGeneratePassword = () => {
    const newPwd = generatePassword();
    form.password = newPwd;
    form.password_confirmation = newPwd;
};

const togglePasswordVisibility = () => {
    formItems.value.password.visible = !formItems.value.password.visible;
};

const submit = () => {
    if (!isFormValid.value) return;
    form.post("/reset-password");
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-200 py-10">
        <div class="w-full sm:w-[30rem] p-8 bg-gray-50 border border-gray-500 rounded-xl shadow-lg">
            <div class="flex flex-col items-center mb-6 gap-2">
                <Image src="/images/logo.png" alt="Logo Aziendale" width="480" class="mb-2" />
                <p class="text-sm text-gray-600 text-center">
                    {{ $t("auth.new_password_body") }} <br />
                    <strong class="text-gray-700">{{ username }}</strong>
                </p>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4 w-full">
                <div class="flex flex-col gap-1">
                    <InputGroup>
                        <FloatLabel variant="on">
                            <Password
                                inputId="password"
                                name="password"
                                v-model="form.password"
                                :feedback="true"
                                fluid
                                :invalid="!!form.errors.password"
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
                                        <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                                            <div
                                                class="h-full transition-all duration-300"
                                                :class="strengthColorClass"
                                                :style="{ width: `${(strength / 5) * 100}%` }"
                                            ></div>
                                        </div>
                                        <small class="text-gray-700 mt-1 block font-medium">
                                            {{ $t("auth.password_strength") }}:
                                            <span class="font-bold" :class="strengthTextColorClass">{{
                                                strengthText
                                            }}</span>
                                        </small>
                                    </div>
                                </template>
                            </Password>
                            <label for="password" class="font-medium text-gray-700 z-10">
                                {{ $t("auth.password_label") }}
                            </label>
                        </FloatLabel>

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
                                style="padding-left: 0.1rem"
                            >
                                <template #icon>
                                    <Icon icon="mdi:dice-multiple-outline" width="24" height="24" />
                                </template>
                            </Button>
                        </InputGroupAddon>
                    </InputGroup>

                    <Message v-if="form.errors.password" severity="error" size="small" variant="simple">
                        {{ form.errors.password }}
                    </Message>
                </div>

                <div class="flex flex-col gap-1 mt-2">
                    <FloatLabel variant="on">
                        <Password
                            inputId="password_confirmation"
                            name="password_confirmation"
                            v-model="form.password_confirmation"
                            :feedback="false"
                            toggleMask
                            fluid
                        />
                        <label for="password_confirmation" class="font-medium text-gray-700 z-10">
                            {{ $t("auth.password_confirmation_label") }}
                        </label>
                    </FloatLabel>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-400 mt-2">
                    <h6 class="text-sm font-bold mb-2 text-gray-700">{{ $t("auth.password_requirements") }}</h6>
                    <ul class="text-sm flex flex-col gap-1">
                        <li :class="requirements.minLength ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.minLength ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_min_length") }}
                        </li>
                        <li :class="requirements.hasUpperCase ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.hasUpperCase ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_upper") }}
                        </li>
                        <li :class="requirements.hasLowerCase ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.hasLowerCase ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_lower") }}
                        </li>
                        <li :class="requirements.hasNumber ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.hasNumber ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_number") }}
                        </li>
                        <li :class="requirements.hasSpecialChar ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.hasSpecialChar ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_special") }}
                        </li>
                        <li
                            v-if="form.password_confirmation"
                            :class="
                                requirements.passwordsMatch ? 'text-green-600 font-medium' : 'text-red-600 font-medium'
                            "
                        >
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.passwordsMatch ? 'pi-check-circle' : 'pi-times-circle'"
                            ></i>
                            {{ $t("auth.req_match") }}
                        </li>
                    </ul>
                </div>

                <Button
                    type="submit"
                    :label="$t('auth.reset_password_btn')"
                    class="mt-4"
                    fluid
                    :loading="form.processing"
                    :disabled="!isFormValid"
                />
            </form>
        </div>
    </div>
</template>
