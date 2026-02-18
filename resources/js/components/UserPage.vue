<template>
    <div class="web-user-container p-4">
        <div class="mb-4">
            <h1 class="text-3xl font-bold">Gestione Utenti</h1>
            <p class="text-gray-600">Crea nuovi account e gestisci quelli esistenti</p>
        </div>

        <div class="grid">
            <div class="col-12 mb-4">
                <UserForm @user-created="refreshTable" />
            </div>

            <div class="col-12">
                <UserTable ref="userTable" />
            </div>
        </div>
    </div>
</template>

<script>
import UserForm from "./UserForm.vue";
import UserTable from "./UserTable.vue";

export default {
    name: "UserPage",
    components: {
        // UserForm,
        UserTable,
    },
    methods: {
        refreshTable() {
            // Verifichiamo che la ref esista e chiamiamo loadUsers
            if (this.$refs.userTable) {
                this.$refs.userTable.loadUsers();
            }
        },
    },
};
</script>

<style scoped>
.user-page-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Se usi il sistema di grid di PrimeVue (PrimeFlex), assicurati che sia caricato. 
   Altrimenti puoi usare classi Bootstrap standard come 'row' e 'col-12' */
</style>
