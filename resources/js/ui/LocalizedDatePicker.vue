<script setup>
import { onMounted } from "vue";
import DatePicker from "primevue/datepicker";
import { usePrimeVue } from "primevue/config";
import { trans } from "laravel-vue-i18n";

const model = defineModel();

const primevue = usePrimeVue();

onMounted(() => {
    primevue.config.locale = {
        firstDayOfWeek: parseInt(trans("primevue.first_day_of_week") || 1),
        dayNames: trans("primevue.day_names").split(","),
        dayNamesShort: trans("primevue.day_names_short").split(","),
        dayNamesMin: trans("primevue.day_names_min").split(","),
        monthNames: trans("primevue.month_names").split(","),
        monthNamesShort: trans("primevue.month_names_short").split(","),
        today: trans("primevue.today"),
        clear: trans("primevue.clear"),
    };
});
</script>

<template>
    <DatePicker v-model="model" :dateFormat="$t('primevue.date_format')" v-bind="$attrs" />
</template>
