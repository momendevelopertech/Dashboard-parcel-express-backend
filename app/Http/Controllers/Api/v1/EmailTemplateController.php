<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $q = EmailTemplate::query();
        if ($s = request('query')) {
            $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($s) . '%']);
        }
        $templates = request()->has('query') ? $q->orderByDesc('id')->get()
            : $q->orderByDesc('id')->paginate( $perPage);
        return sendResponse("Email templates retrieved.", $templates);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:email_templates,name',
            'subject' => 'required|string',
            'body' => 'required|string',
        ]);
        $tpl = EmailTemplate::create($data);
        return sendResponse("Email template created.", $tpl, true, [], 201);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'subject' => 'sometimes|string',
            'body' => 'sometimes|string',
        ]);
        $emailTemplate = EmailTemplate::find($request->input("id"));
        if(!$emailTemplate) {
            return sendResponse("Email Template Not Found", 404, [], []);
        }
        $emailTemplate->update($data);
        return sendResponse("Email template updated.", $emailTemplate);
    }

    public function destroy(EmailTemplate $emailTemplate)
    {
        $emailTemplate->delete();
        return sendResponse("Email template deleted.", []);
    }

}
