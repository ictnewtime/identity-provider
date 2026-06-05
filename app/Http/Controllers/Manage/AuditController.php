<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use OwenIt\Auditing\Models\Audit;
use Laravel\Passport\Client as PassportClient;

class AuditController extends Controller
{
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
                    $query
                        ->join("users", "audits.user_id", "=", "users.id")
                        ->select("audits.*")
                        ->orderBy($sortColumn, $direction);
                } else {
                    $query->orderBy("audits." . $field, $direction);
                }
            }
        } else {
            $query->orderBy("created_at", "desc");
        }

        // Paginazione
        $perPage = $request->input("per_page", 25);
        return $query
            ->latest()
            ->paginate($perPage)
            ->through(function ($audit) {
                // Se la relazione 'user' esiste ed è un Client di Passport
                if ($audit->user instanceof PassportClient) {
                    // Iniettiamo la proprietà username al volo per Vue
                    $audit->user->username = $audit->user->name;
                }
                return $audit;
            });
    }
}
