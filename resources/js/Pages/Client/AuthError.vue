<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import Card from "primevue/card";
import Button from "primevue/button";

// Pagina d'arrivo quando l'accesso non si completa per un guasto di configurazione. Ci rimandano
// l'IdP e i pacchetti client al posto del login, che riporterebbe l'utente nel loop.
const props = defineProps({
    reason: { type: String, default: null },
    providerId: { type: String, default: null },
    occurredAt: { type: String, default: null },
});

const occurredAtLabel = computed(() => (props.occurredAt ? new Date(props.occurredAt).toLocaleString() : null));

// Uscire dalla sessione dell'IdP e' cio' che sblocca chi e' rimasto con un token vecchio: il logout
// SSO la chiude e riporta all'applicazione, che chiede un login pulito.
const retryUrl = computed(() =>
    props.providerId ? `/sso/logout?provider_id=${encodeURIComponent(props.providerId)}` : "/sso/logout",
);
</script>

<template>
    <Head :title="$t('client.auth_error.title')" />

    <div class="min-h-screen bg-surface-50 flex items-center justify-center p-4">
        <Card class="w-full max-w-md shadow-sm border border-surface-200">
            <template #title>
                <div class="flex flex-col items-center justify-center gap-3 text-orange-600 mb-2 mt-4">
                    <i class="pi pi-exclamation-triangle text-5xl"></i>
                    <h1 class="text-2xl font-bold text-surface-900 m-0 text-center">
                        {{ $t("client.auth_error.title") }}
                    </h1>
                </div>
            </template>

            <template #content>
                <p class="text-surface-600 text-center leading-relaxed mb-4">
                    {{ $t("client.auth_error.message") }}
                </p>
                <p class="text-surface-600 text-center leading-relaxed mb-4">
                    {{ $t("client.auth_error.admin") }}
                </p>

                <dl class="bg-surface-100 rounded p-3 text-sm text-surface-700 mb-4">
                    <div v-if="reason" class="flex justify-between gap-2">
                        <dt class="font-semibold">{{ $t("client.auth_error.code") }}</dt>
                        <dd class="m-0 font-mono">{{ reason }}</dd>
                    </div>
                    <div v-if="occurredAtLabel" class="flex justify-between gap-2">
                        <dt class="font-semibold">{{ $t("client.auth_error.time") }}</dt>
                        <dd class="m-0">{{ occurredAtLabel }}</dd>
                    </div>
                </dl>

                <div class="flex justify-center">
                    <a :href="retryUrl">
                        <Button :label="$t('client.auth_error.retry')" icon="pi pi-sign-out" severity="secondary" />
                    </a>
                </div>
            </template>
        </Card>
    </div>
</template>
