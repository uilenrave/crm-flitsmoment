<?php

namespace App\Http\Controllers;

use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskListController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $list = TaskList::create([
            'title'      => $data['title'],
            'color'      => $data['color'] ?? '#64748b',
            'user_id'    => auth()->id(),
            'sort_order' => TaskList::max('sort_order') + 1,
        ]);

        return redirect()->route('tasks.index', ['list' => $list->id]);
    }

    public function update(Request $request, TaskList $taskList): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $taskList->update([
            'title' => $data['title'],
            'color' => $data['color'] ?? $taskList->color,
        ]);

        return redirect()->route('tasks.index', ['list' => $taskList->id]);
    }

    public function destroy(TaskList $taskList): RedirectResponse
    {
        $taskList->delete();

        return redirect()->route('tasks.index');
    }
}
