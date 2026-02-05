<template>
    <Toast position="top-center" />
</template>

<script>
import { EventBus } from "../event-bus";
import Toast from "primevue/toast";

export default {
    components: { Toast },
    mounted() {
        // Ascoltiamo l'EventBus
        EventBus.$on("newNotification", (notification) => {
            this.showNotification(notification);
        });
    },
    methods: {
        showNotification(data) {
            // Mappiamo i tuoi tipi ('SUCCESS', 'ERROR') ai tipi di PrimeVue
            const severityMap = {
                SUCCESS: "success",
                ERROR: "error",
                INFO: "info",
            };

            // Invece di gestire la coda a mano, chiamiamo il service.
            // PrimeVue accoda i messaggi automaticamente se ne arrivano più di uno.
            this.$toast.add({
                severity: severityMap[data.type] || "info",
                summary: data.type, // Titolo (opzionale)
                detail: data.message, // Messaggio
                life: 3000, // Durata (come i tuoi 3000ms)
            });
        },
    },
};
</script>
