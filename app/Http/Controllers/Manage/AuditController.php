<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditResource;
use App\Queries\Audit\AuditListQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function __construct(private readonly AuditListQuery $audits) {}

    public function index()
    {
        return Inertia::render("Admin/Audits");
    }

    public function all(Request $request)
    {
        return AuditResource::collection(
            $this->audits->paginate(
                $request->input("q"),
                $request->input("sort_by"),
                $request->input("sort_dir"),
                $request->input("per_page"),
            ),
        );
    }
}
