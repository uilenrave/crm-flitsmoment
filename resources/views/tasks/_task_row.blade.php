@php
    $isSubtask = isset($isSubtask) && $isSubtask;
    $dueStatus = $task->due_status;
    $dueLabelMap = [
        'overdue' => 'Te laat',
        'today'   => 'Vandaag',
        'future'  => $task->due_date?->translatedFormat('j M'),
    ];
@endphp

<div class="task-row {{ $task->is_completed ? 'task-done' : '' }} {{ $isSubtask ? 'subtask-row' : '' }}"
     id="task-row-{{ $task->id }}"
     data-task-id="{{ $task->id }}">

    {{-- Checkbox --}}
    <div class="task-check {{ $task->is_completed ? 'checked' : '' }}"
         onclick="Tasks.toggle({{ $task->id }}, this)"
         title="{{ $task->is_completed ? 'Markeer als open' : 'Markeer als afgerond' }}">
        @if($task->is_completed)
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        @endif
    </div>

    {{-- Content --}}
    <div style="flex:1;min-width:0;">
        {{-- Title (contenteditable) --}}
        <div class="task-title {{ $task->is_completed ? 'done' : '' }}"
             contenteditable="true"
             spellcheck="false"
             data-task-id="{{ $task->id }}"
             data-original="{{ $task->title }}"
             onblur="Tasks.saveTitle({{ $task->id }}, this)"
             onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
        >{{ $task->title }}</div>

        {{-- Meta row: due date + notes indicator --}}
        @if($task->due_date || $task->notes)
        <div style="display:flex;align-items:center;gap:.5rem;margin-top:.25rem;">
            @if($dueStatus)
            <span class="due-chip {{ $dueStatus }}">
                📅 {{ $dueLabelMap[$dueStatus] ?? '' }}
            </span>
            @endif
            @if($task->notes)
            <span style="font-size:.7rem;color:#94a3b8;" title="{{ $task->notes }}">📝</span>
            @endif
        </div>
        @endif

        {{-- Subtaken --}}
        @if(! $isSubtask && $task->subtasks->isNotEmpty())
        <div class="subtasks-wrap" style="margin-top:.4rem;">
            @foreach($task->subtasks as $subtask)
                @include('tasks._task_row', ['task' => $subtask, 'isSubtask' => true])
            @endforeach
        </div>
        @endif

        {{-- Quick-add subtaak --}}
        @if(! $isSubtask)
        <div class="subtask-add-wrap" style="display:none;margin-top:.35rem;padding-left:0;">
            <input type="text"
                   class="quick-add subtask-input"
                   placeholder="Subtaak toevoegen..."
                   data-parent-id="{{ $task->id }}"
                   data-list-id="{{ $task->task_list_id }}"
                   onkeydown="if(event.key==='Enter'){Tasks.addSubtask(this);}if(event.key==='Escape'){this.closest('.subtask-add-wrap').style.display='none';this.value='';}"
                   onblur="if(!this.value.trim()){this.closest('.subtask-add-wrap').style.display='none';}">
        </div>
        @endif
    </div>

    {{-- Vlag (altijd zichtbaar als gevlagd, anders op hover) --}}
    <button class="task-flag-btn {{ $task->flagged ? 'flagged' : '' }}"
            title="{{ $task->flagged ? 'Vlag verwijderen' : 'Markeer als belangrijk' }}"
            onclick="Tasks.flag({{ $task->id }}, this)">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $task->flagged ? '#ef4444' : 'none' }}" stroke="{{ $task->flagged ? '#ef4444' : 'currentColor' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
            <line x1="4" y1="22" x2="4" y2="15"/>
        </svg>
    </button>

    {{-- Actions (rechts, verschijnt op hover) --}}
    <div class="task-actions">
        @if(! $isSubtask)
        <button class="task-action-btn"
                title="Subtaak toevoegen"
                onclick="Tasks.showSubtaskInput({{ $task->id }})">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        @endif
        <button class="task-action-btn task-action-delete"
                title="Verwijderen"
                onclick="Tasks.remove({{ $task->id }})">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
</div>
