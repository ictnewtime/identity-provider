<?php

namespace App\Http\Controllers\Manage;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Parameter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ParametersController extends Controller
{
    public function all(Request $request)
    {
        $show_deleted = $request->boolean("show_deleted");
        $query = Parameter::query();

        $searchTerm = $request->input("searchTerm");

        if ($searchTerm) {
            $query->where("key", "like", "%" . $searchTerm . "%");
        }

        if ($show_deleted) {
            $query->onlyTrashed();
        }

        $perPage = $request->input("per_page", 50);
        return $query->paginate($perPage);
    }

    public function create(Request $request)
    {
        $data = $request->only("key", "value", "type");
        $existingParameter = Parameter::where("key", $data["key"])->first();
        if ($existingParameter) {
            return response()->json(["message" => __("parameter.error.alredy_exists")], 422);
        }

        try {
            $parameter = Parameter::create($data);

            return response()->json($parameter, 201);
        } catch (QueryException $e) {
            return response()->json(["message" => __("parameter.error.creating")], 500);
        }
    }

    public function find($id)
    {
        $parameter = Parameter::find($id);
        if (empty($parameter)) {
            return response()->json(["message" => __("parameter.error.not_found")], 404);
        }
        return response()->json($parameter);
    }

    public function update(Request $request, $id)
    {
        $data = $request->only("key", "value", "type");
        $parameter = Parameter::find($id);

        if (empty($parameter)) {
            return response()->json(["message" => __("parameter.error.not_found")], 404);
        }

        try {
            $parameter->update($data);

            return response()->json($parameter, 200);
        } catch (QueryException $e) {
            return response()->json(["message" => __("parameter.error.updating")], 500);
        }
    }

    public function delete(int $id)
    {
        $parameter = Parameter::find($id);

        if (empty($parameter)) {
            return response()->json(
                [
                    "message" => __("parameter.error.not_found"),
                ],
                404,
            );
        }

        try {
            $parameter->delete();
        } catch (QueryException $e) {
            return response()->json(["message" => $e], 500);
        }

        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $parameter = Parameter::withTrashed()->find($id);
        if (empty($parameter)) {
            return response()->json(["message" => __("parameter.error.not_found")], 404);
        }
        try {
            $parameter->restore();
        } catch (\Exception $e) {
            Log::error("Error on restoring parameter: " . $e);
            return response()->json(["message" => __("parameter.error.restoring")], 500);
        }
        return response()->json($parameter, 200);
    }
}
