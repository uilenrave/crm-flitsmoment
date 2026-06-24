<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $taskLists = TaskList::orderBy('sort_order')->orderBy('created_at')->get();

        // Selecteer lijst: via ?list=, of eerste beschikbare
        $selectedListId = $request->query('list');
        $selectedList   = $selectedListId
            ? $taskLists->firstWhere('id', $selectedListId)
            : $taskLists->first();

        $pendingTasks  = collect();
        $completedTasks = collect();

        if ($selectedList) {
            $pendingTasks = Task::with('subtasks.subtasks')
                ->where('task_list_id', $selectedList->id)
                ->topLevel()
                ->pending()
                ->orderByDesc('flagged')   // gevlagd altijd bovenaan
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get();

            $completedTasks = Task::with('subtasks')
                ->where('task_list_id', $selectedList->id)
                ->topLevel()
                ->completed()
                ->orderByDesc('completed_at')
                ->limit(20)
                ->get();
        }

        return view('tasks.index', compact('taskLists', 'selectedList', 'pendingTasks', 'completedTasks'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_list_id'   => ['required', 'integer'],
            'parent_task_id' => ['nullable', 'integer'],
            'title'          => ['required', 'string', 'max:500'],
            'notes'          => ['nullable', 'string', 'max:5000'],
            'due_date'       => ['nullable', 'date'],
        ]);

        // Verify task_list belongs to this account
        $list = TaskList::findOrFail($data['task_list_id']);

        // Verify parent task belongs to this account (if given)
        if (! empty($data['parent_task_id'])) {
            Task::findOrFail($data['parent_task_id']);
        }

        $task = Task::create([
            'task_list_id'   => $list->id,
            'parent_task_id' => $data['parent_task_id'] ?? null,
            'title'          => $data['title'],
            'notes'          => $data['notes'] ?? null,
            'due_date'       => $data['due_date'] ?? null,
            'sort_order'     => Task::where('task_list_id', $list->id)->max('sort_order') + 1,
        ]);

        return response()->json($task->load('subtasks')->append(['is_completed', 'due_status']));
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'title'    => ['sometimes', 'required', 'string', 'max:500'],
            'notes'    => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $task->update(array_filter($data, fn($v) => $v !== null || array_key_exists('notes', $data)));

        // Allow explicitly setting null for notes/due_date
        if ($request->has('notes'))    $task->update(['notes'    => $request->notes]);
        if ($request->has('due_date')) $task->update(['due_date' => $request->due_date]);

        return response()->json($task->fresh()->append(['is_completed', 'due_status']));
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete(); // cascade handles subtasks via DB

        return response()->json(['ok' => true]);
    }

    public function toggle(Task $task): JsonResponse
    {
        if ($task->completed_at) {
            $task->update(['completed_at' => null]);
        } else {
            $task->update(['completed_at' => now()]);

            // Also complete all subtasks
            $task->subtasks()->update(['completed_at' => now()]);
        }

        return response()->json([
            'completed'    => $task->fresh()->is_completed,
            'completed_at' => $task->fresh()->completed_at?->toIso8601String(),
        ]);
    }

    public function flag(Task $task): JsonResponse
    {
        $task->update(['flagged' => ! $task->flagged]);

        return response()->json(['flagged' => $task->fresh()->flagged]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items'            => ['required', 'array'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer'],
        ])['items'];

        $accountId = auth()->user()->account_id;

        foreach ($items as $item) {
            Task::where('id', $item['id'])
                ->where('account_id', $accountId) // explicit ownership check
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['ok' => true]);
    }
}
