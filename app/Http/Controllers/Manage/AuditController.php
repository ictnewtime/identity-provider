<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use OwenIt\Auditing\Models\Audit;
use Laravel\Passport\Client as PassportClient;

class AuditController extends Controller
{
    private const PER_PAGE_DEFAULT = 25;
    private const PER_PAGE_MAX = 200;

    public function index()
    {
        return Inertia::render("Admin/Audits");
    }

    public function all(Request $request)
    {
        $query = Audit::with("user");

        if ($request->filled("q")) {
            $searchTerm = "%" . $request->q . "%";

            $query->where(function ($qBuilder) use ($searchTerm) {
                $qBuilder
                    ->where("ip_address", "like", $searchTerm)
                    ->orWhere("event", "like", $searchTerm)
                    ->orWhere("auditable_type", "like", $searchTerm)

                    ->orWhereHasMorph("user", [User::class, PassportClient::class], function ($q, $type) use (
                        $searchTerm,
                    ) {
                        // Se la riga appartiene a web, cerca per username
                        if ($type === User::class) {
                            $q->where("username", "like", $searchTerm);
                        }
                        // Se la riga appartiene a Passport, cerca per nome del client
                        elseif ($type === PassportClient::class) {
                            $q->where("name", "like", $searchTerm);
                        }
                    });
            });
        }

        if ($request->filled("sort_by")) {
            $field = $request->sort_by;
            $direction = strtolower($request->sort_dir) === "desc" ? "desc" : "asc";
            $allowedSorts = ["created_at", "event", "auditable_type", "user.username", "ip_address"];

            if (in_array($field, $allowedSorts)) {
                if (str_starts_with($field, "user.")) {
                    $sortColumn = str_replace("user.", "users.", $field);

                    // `user` è una relazione POLIMORFA: la chiave è la coppia (user_id, user_type),
                    // e la tabella ha un indice proprio su quella coppia. Unire sul solo `user_id`
                    // attaccherebbe l'audit di un client Passport all'utente con lo stesso id.
                    //
                    // `leftJoin` e non `join`: `audits.user_id` è nullable, e una join interna
                    // farebbe sparire dalla lista gli audit di sistema — un registro di audit che
                    // nasconde righe a seconda dell'ordinamento.
                    $query
                        ->leftJoin("users", function ($join) {
                            $join->on("audits.user_id", "=", "users.id")->where("audits.user_type", "=", User::class);
                        })
                        ->select("audits.*")
                        ->orderBy($sortColumn, $direction);
                } else {
                    $query->orderBy("audits." . $field, $direction);
                }
            }
        } else {
            $query->orderBy("created_at", "desc");
        }

        $perPage = (int) $request->input("per_page", self::PER_PAGE_DEFAULT);
        $perPage = max(1, min($perPage, self::PER_PAGE_MAX));

        return AuditResource::collection($query->paginate($perPage));
    }
}
