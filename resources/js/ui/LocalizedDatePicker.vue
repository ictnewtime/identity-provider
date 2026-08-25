<script setup>
import { onMounted } from "vue";
import DatePicker from "primevue/datepicker";
import { usePrimeVue } from "primevue/config";
import { getActiveLanguage, trans } from "laravel-vue-i18n";

const model = defineModel();

const primevue = usePrimeVue();

/**
 * Il primo giorno della settimana per lingua: 0 e' domenica, 1 e' lunedi'.
 */
const FIRST_DAY_OF_WEEK = { it: 1, en: 0 };
const FIRST_DAY_OF_WEEK_FALLBACK = 1;

onMounted(() => {
    const firstDayOfWeek = FIRST_DAY_OF_WEEK[getActiveLanguage()] ?? FIRST_DAY_OF_WEEK_FALLBACK;

    primevue.config.locale = {
        firstDayOfWeek,
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
