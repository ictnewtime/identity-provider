<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use OwenIt\Auditing\Models\Audit;

class AuditController extends Controller
{
    /**
     * 1. Restituisce solo la struttura della pagina Vue
     */
    public function index()
    {
        return Inertia::render("Admin/Audits");
    }

    /**
     * 2. Restituisce i dati JSON paginati per il componente AuditTable (Axios)
     */
    public function all(Request $request)
    {
        // 1. Carichiamo la relazione polimorfica (non possiamo filtrare le colonne qui
        // perché User e Client hanno colonne diverse)
        $query = Audit::with("user");

        // 2. Ricerca
        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";

            // Usiamo una closure per raggruppare gli OR, evitando di rompere la query
            $query->where(function ($qBuilder) use ($searchTerm) {
                $qBuilder
                    ->where("ip_address", "like", $searchTerm)
                    ->orWhere("event", "like", $searchTerm)
                    ->orWhere("auditable_type", "like", $searchTerm)

                    // LA MAGIA POLIMORFICA:
                    ->orWhereHasMorph("user", [\App\Models\User::class, \Laravel\Passport\Client::class], function (
                        $q,
                        $type,
                    ) use ($searchTerm) {
                        // Se la riga appartiene a un umano, cerca per username
                        if ($type === \App\Models\User::class) {
                            $q->where("username", "like", $searchTerm);
                        }
                        // Se la riga appartiene a Passport, cerca per nome del client
                        elseif ($type === \Laravel\Passport\Client::class) {
                            $q->where("name", "like", $searchTerm);
                        }
                    });
            });
        }
        // Paginazione standard
        $perPage = $request->input("per_page", 15);
        return $query
            ->latest()
            ->paginate($perPage)
            ->through(function ($audit) {
                // Se la relazione 'user' esiste ed è un Client di Passport
                if ($audit->user instanceof \Laravel\Passport\Client) {
                    // Iniettiamo la proprietà username al volo per Vue
                    $audit->user->username = $audit->user->name;
                }
                return $audit;
            });
    }
}
