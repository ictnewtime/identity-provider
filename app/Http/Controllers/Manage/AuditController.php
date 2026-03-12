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
        // Carichiamo l'audit e l'utente collegato
        $query = Audit::with("user:id,username");

        // Ricerca
        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";
            $query
                ->where("ip_address", "like", $searchTerm)
                ->orWhere("event", "like", $searchTerm)
                ->orWhere("auditable_type", "like", $searchTerm)
                ->orWhereHas("user", function ($q) use ($searchTerm) {
                    $q->where("username", "like", $searchTerm);
                });
        }

        // Paginazione standard
        $perPage = $request->input("per_page", 15);
        return $query->latest()->paginate($perPage);
    }
}
