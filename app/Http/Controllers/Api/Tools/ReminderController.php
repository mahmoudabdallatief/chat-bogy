<?php

namespace App\Http\Controllers\Api\Tools;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['device_id' => ['required', 'string', 'max:191']]);

        return response()->json([
            'success' => true,
            'data' => Reminder::where('device_id', $data['device_id'])
                ->orderBy('scheduled_at')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $reminder = Reminder::create($this->validatedData($request));

        return response()->json(['success' => true, 'data' => $reminder], 201);
    }

    public function show(Reminder $reminder)
    {
        return response()->json(['success' => true, 'data' => $reminder]);
    }

    public function update(Request $request, Reminder $reminder)
    {
        $reminder->update($this->validatedData($request, false));

        return response()->json(['success' => true, 'data' => $reminder->fresh()]);
    }

    public function destroy(Reminder $reminder)
    {
        $reminder->delete();

        return response()->json(['success' => true, 'message'=> 'reminder deleted successfully'], 200);
    }

    private function validatedData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'device_id' => [$creating ? 'required' : 'sometimes', 'string', 'max:191'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'scheduled_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'status' => ['sometimes', 'string', 'in:scheduled,cancelled,completed'],
            'payload' => ['nullable', 'array'],
        ]);
    }
}
