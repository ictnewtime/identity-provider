<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Passport\Client as PassportClient;

/**
 * La forma della risposta della lista audit (punto TCC11).
 *
 * Prima di questa classe il controller restituiva il modello `Audit` intero con dentro il
 * modello `user` intero: ogni colonna non nascosta usciva, e ogni colonna aggiunta domani
 * sarebbe uscita da sola. Qui i campi si scelgono una volta — punto 2 della checklist
 * perf/leak dell'organizzazione.
 *
 * L'elenco corrisponde a cio' che la tabella consuma davvero
 * (`resources/js/components/AuditTable.vue`): aggiungerne uno e' una decisione, non un effetto.
 */
class AuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "created_at" => $this->created_at,
            "event" => $this->event,
            "auditable_type" => $this->auditable_type,
            "auditable_id" => $this->auditable_id,
            "ip_address" => $this->ip_address,
            "user_agent" => $this->user_agent,
            "url" => $this->url,
            "old_values" => $this->old_values,
            "new_values" => $this->new_values,
            "user" => $this->attore(),
        ];
    }

    /**
     * L'attore dell'audit, ridotto al solo nome che la tabella mostra.
     *
     * La relazione e' polimorfa: per un utente il nome sta in `username`, per un client
     * Passport in `name`. Prima questa conversione avveniva nel controller, che assegnava
     * `username` al volo sul modello del client; ora sta qui, dove si decide la forma della
     * risposta, e non muta piu' un modello per farlo.
     */
    private function attore(): ?array
    {
        // `user` e' caricato in eager dal controller: qui non parte nessuna query, altrimenti
        // sarebbe un N+1 dentro una API Resource — punto 1 della stessa checklist.
        if (empty($this->user)) {
            return null;
        }

        return [
            "username" => $this->user instanceof PassportClient ? $this->user->name : $this->user->username,
        ];
    }
}
