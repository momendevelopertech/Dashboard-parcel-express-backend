<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;

class RuleController extends Controller
{
    public function index()
    {
        $rules = Rule::orderBy('name')->paginate(15);
        return sendResponse('Rules retrieved successfully.', $rules);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'condition_type'  => 'required|string',
            'condition_value' => 'required|string',
            'action_type'     => 'required|string',
            'action_payload'  => 'nullable|json',
            'status'          => ['sometimes', ValidationRule::in(['active', 'inactive'])],
        ]);

        if ($request->filled('action_payload')) {
            $data['action_payload'] = json_decode($request->input('action_payload'), true);
        }

        $rule = Rule::create($data);
        return sendResponse('Rule created successfully.', $rule, true, [], 201);
    }

    public function show(Rule $rule)
    {
        return sendResponse('Rule retrieved successfully.', $rule);
    }

    public function update(Request $request)
    {
        $rule = Rule::findOrFail($request->input("id"));
        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'condition_type'  => 'sometimes|string',
            'condition_value' => 'sometimes|string',
            'action_type'     => 'sometimes|string',
            'action_payload'  => 'nullable|json',
            'status'          => ['sometimes', ValidationRule::in(['active', 'inactive'])],
        ]);

        if ($request->filled('action_payload')) {
            $data['action_payload'] = json_decode($request->input('action_payload'), true);
        }

        $rule->update($data);
        return sendResponse('Rule updated successfully.', $rule);
    }

    public function destroy(Request $request)
    {
        $rule = Rule::findOrFail($request->input("id"));
        $rule->delete();
        return response()->noContent();
    }
}
