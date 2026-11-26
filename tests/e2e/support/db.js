import { execFileSync } from "node:child_process";

/**
 * Riporta il database allo stato iniziale: schema da zero, dati di partenza e utenti E2E.
 *
 * Funziona perche' Playwright e l'applicazione stanno nello STESSO container: lo script tocca il
 * file sqlite in /tmp che l'applicazione sta usando, e rifiuta qualunque altro database.
 * Le sessioni aperte restano valide solo se il loro utente esiste ancora: dopo un reset si rifa'
 * il login.
 */
export function resetDatabase() {
    execFileSync("e2e-reset-db.sh", { stdio: "inherit" });
}

/**
 * Inserisce nel database `count` utenti, `count` provider e `count` ruoli, tutti con il prefisso dato:
 *
 *     utenti     <prefix>_<n>          (email <prefix>_<n>@example.com)
 *     provider   <prefix>_p<n>         (dominio <prefix>-p<n>.example.com)
 *     ruoli      <prefix>_r<n>         tutti sull'ULTIMO provider, <prefix>_p<count>
 *
 * Serve ai test delle ricerche nei form: il backend restituisce al massimo 100 righe per pagina, e
 * il difetto stava proprio in chi veniva dopo. Crearli dai form richiederebbe minuti.
 * Prefisso e numero passano come variabili d'ambiente e non dentro il codice PHP, cosi' non
 * possono alterarlo.
 */
export function insertBulkData(prefix, count) {
    const code = `
        $prefix = getenv("E2E_BULK_PREFIX");
        $count = (int) getenv("E2E_BULK_COUNT");
        $now = now();
        $users = [];
        $providers = [];
        $roles = [];
        for ($i = 1; $i <= $count; $i++) {
            // utenti: created_at crescente, l'ultimo inserito e' l'ultimo nell'ordine predefinito.
            // provider e ruoli non hanno created_at: li ordina l'id, che cresce comunque.
            $at = $now->copy()->addSeconds($i);
            $users[] = [
                "username" => "{$prefix}_{$i}",
                "email" => "{$prefix}_{$i}@example.com",
                "name" => "Bulk",
                "surname" => "User {$i}",
                "enabled" => 1,
                "created_at" => $at,
            ];
            $providers[] = [
                "name" => "{$prefix}_p{$i}",
                "domain" => "{$prefix}-p{$i}.example.com",
                "url" => "https://{$prefix}-p{$i}.example.com",
                "logoutUrl" => "https://{$prefix}-p{$i}.example.com/logout",
                "secret_key" => str_repeat("k", 32),
            ];
        }
        $db = Illuminate\\Support\\Facades\\DB::class;
        foreach (["users" => $users, "providers" => $providers] as $table => $rows) {
            foreach (array_chunk($rows, 50) as $chunk) {
                $db::table($table)->insert($chunk);
            }
        }
        $lastProviderId = $db::table("providers")->where("name", "{$prefix}_p{$count}")->value("id");
        for ($i = 1; $i <= $count; $i++) {
            $roles[] = ["name" => "{$prefix}_r{$i}", "provider_id" => $lastProviderId];
        }
        foreach (array_chunk($roles, 50) as $chunk) {
            $db::table("roles")->insert($chunk);
        }
    `;
    execFileSync("php", ["artisan", "tinker", "--execute", code], {
        cwd: "/var/www",
        stdio: "inherit",
        env: { ...process.env, E2E_BULK_PREFIX: prefix, E2E_BULK_COUNT: String(count) },
    });
}
