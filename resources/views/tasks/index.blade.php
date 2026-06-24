@extends('layouts.app')

@section('title', 'Taken')

@push('styles')
<style>
    /* ── Layout: override default .content padding for full-height split ── */
    .content { padding: 0 !important; overflow: hidden !important; display: flex !important; flex-direction: column; }
    .content-wrap { display: flex; overflow: hidden; flex: 1; height: calc(100vh - 56px); }
    @media (min-width: 769px) {
        .content-wrap { height: 100vh; }
    }

    /* ── Left panel ── */
    .task-sidebar {
        width: 220px;
        flex-shrink: 0;
        border-right: 1px solid #f1f5f9;
        background: #fafaf9;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        padding: 1.25rem .75rem 1.5rem;
    }
    @media (max-width: 600px) {
        .task-sidebar { width: 160px; }
    }

    .list-section-label {
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #94a3b8;
        padding: 0 .65rem;
        margin-bottom: .4rem;
    }

    .list-item {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .5rem .65rem;
        border-radius: .6rem;
        cursor: pointer;
        font-size: .8125rem;
        color: #475569;
        text-decoration: none;
        transition: background .12s;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }
    .list-item:hover { background: rgba(252,211,77,.1); color: #1e293b; }
    .list-item.active { background: rgba(252,211,77,.18); color: #1e293b; font-weight: 600; }
    .list-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    .list-title { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .list-badge {
        font-size: .65rem;
        background: #e2e8f0;
        color: #64748b;
        border-radius: 9999px;
        padding: .1rem .45rem;
        flex-shrink: 0;
        font-weight: 600;
    }
    .list-item.active .list-badge { background: rgba(252,211,77,.4); color: #92400e; }

    /* ── Right panel ── */
    .task-main {
        flex: 1;
        overflow-y: auto;
        padding: 2rem 2rem 4rem;
        min-width: 0;
    }
    @media (max-width: 600px) {
        .task-main { padding: 1.25rem 1rem 4rem; }
    }

    /* ── Task rows ── */
    .task-row {
        display: flex;
        align-items: flex-start;
        gap: .7rem;
        padding: .5rem .25rem;
        border-radius: .5rem;
        transition: background .1s;
        position: relative;
    }
    .task-row:hover { background: #fafaf9; }
    .task-row:hover .task-actions { opacity: 1; }

    .subtask-row {
        padding-left: 1.75rem;
        padding-top: .35rem;
        padding-bottom: .35rem;
    }

    /* Checkbox */
    .task-check {
        width: 17px;
        height: 17px;
        border-radius: 50%;
        border: 1.75px solid #d1d5db;
        flex-shrink: 0;
        cursor: pointer;
        transition: all .18s;
        margin-top: 2px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
    }
    .task-check:hover { border-color: #f59e0b; background: #fef3c7; }
    .task-check.checked { background: #f59e0b; border-color: #f59e0b; }

    /* Title */
    .task-title {
        font-size: .875rem;
        color: #1e293b;
        flex: 1;
        cursor: text;
        line-height: 1.5;
        outline: none;
        border-radius: .25rem;
        padding: 0 .2rem;
        min-width: 0;
        word-break: break-word;
    }
    .task-title:focus { background: #fffbeb; }
    .task-title.done {
        text-decoration: line-through;
        color: #94a3b8;
    }

    /* Due chip */
    .due-chip {
        font-size: .68rem;
        font-weight: 600;
        padding: .15rem .5rem;
        border-radius: 9999px;
        flex-shrink: 0;
    }
    .due-chip.future  { background: #dcfce7; color: #16a34a; }
    .due-chip.today   { background: #fef3c7; color: #d97706; }
    .due-chip.overdue { background: #fee2e2; color: #dc2626; }

    /* Vlag knop */
    .task-flag-btn {
        width: 22px;
        height: 22px;
        border-radius: .35rem;
        border: none;
        background: none;
        cursor: pointer;
        color: #d1d5db;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        flex-shrink: 0;
        opacity: 0;
        transition: opacity .15s, color .15s;
        margin-top: 1px;
    }
    .task-row:hover .task-flag-btn { opacity: 1; }
    .task-flag-btn.flagged { opacity: 1 !important; color: #ef4444; }
    .task-flag-btn:hover { color: #ef4444; }

    /* Gevlagde rij subtiele highlight */
    .task-row:has(.task-flag-btn.flagged) { background: #fff8f8; border-radius: .5rem; }
    .task-row:has(.task-flag-btn.flagged):hover { background: #fff0f0; }

    /* Actions */
    .task-actions {
        display: flex;
        align-items: center;
        gap: .25rem;
        flex-shrink: 0;
        opacity: 0;
        transition: opacity .15s;
    }
    .task-row:hover .task-actions { opacity: 1; }
    .task-action-btn {
        width: 22px;
        height: 22px;
        border-radius: .35rem;
        border: none;
        background: none;
        cursor: pointer;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        transition: background .12s, color .12s;
    }
    .task-action-btn:hover { background: #f1f5f9; color: #1e293b; }
    .task-action-delete:hover { background: #fee2e2; color: #dc2626; }

    /* Quick-add input */
    .quick-add {
        width: 100%;
        border: none;
        border-bottom: 1.5px dashed #e2e8f0;
        background: transparent;
        font-size: .875rem;
        padding: .5rem .25rem;
        outline: none;
        color: #1e293b;
        font-family: inherit;
    }
    .quick-add::placeholder { color: #b0bac6; }
    .quick-add:focus { border-bottom-color: #f59e0b; }
    .subtask-input { font-size: .8rem; }

    /* Divider tussen pending en completed */
    .completed-divider {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin: 1.5rem 0 .75rem;
        cursor: pointer;
        user-select: none;
    }
    .completed-divider-line { flex: 1; height: 1px; background: #f1f5f9; }
    .completed-divider-label { font-size: .75rem; color: #94a3b8; font-weight: 600; white-space: nowrap; }
    .completed-divider-chevron { color: #94a3b8; transition: transform .2s; }
    .completed-divider-chevron.open { transform: rotate(90deg); }

    /* Empty state */
    .empty-state { text-align: center; padding: 4rem 2rem; color: #94a3b8; }
    .empty-state p { font-size: .875rem; margin-top: .75rem; }

    /* Nieuw-lijst formulier */
    .new-list-form {
        display: none;
        margin-top: .5rem;
        padding: .5rem .65rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .6rem;
    }
    .new-list-form.open { display: block; }
    .new-list-input {
        width: 100%;
        border: none;
        border-bottom: 1.5px solid #e2e8f0;
        background: transparent;
        font-size: .8125rem;
        padding: .3rem 0;
        outline: none;
        color: #1e293b;
        font-family: inherit;
        margin-bottom: .5rem;
    }
    .new-list-input:focus { border-bottom-color: #f59e0b; }

    /* List header edit */
    .list-header-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e293b;
        outline: none;
        border-radius: .3rem;
        padding: .1rem .3rem;
        cursor: text;
    }
    .list-header-title:focus { background: #fffbeb; }
</style>
@endpush

@section('content')
<div class="content-wrap">

    {{-- ── LEFT: Lijst sidebar ── --}}
    <div class="task-sidebar">
        <div class="list-section-label">Lijsten</div>

        @forelse($taskLists as $list)
        <a href="{{ route('tasks.index', ['list' => $list->id]) }}"
           class="list-item {{ $selectedList?->id == $list->id ? 'active' : '' }}">
            <span class="list-dot" style="background:{{ $list->color }}"></span>
            <span class="list-title">{{ $list->title }}</span>
            @if($list->pending_count > 0)
            <span class="list-badge">{{ $list->pending_count }}</span>
            @endif
        </a>
        @empty
        <p style="font-size:.75rem;color:#94a3b8;padding:.5rem .65rem;">Nog geen lijsten</p>
        @endforelse

        {{-- Nieuwe lijst --}}
        <div style="margin-top:.75rem;">
            <button onclick="document.getElementById('new-list-form').classList.toggle('open');document.getElementById('new-list-title').focus();"
                    class="list-item" style="color:#94a3b8;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Nieuwe lijst</span>
            </button>
            <form id="new-list-form" class="new-list-form" method="POST" action="{{ route('task-lists.store') }}">
                @csrf
                <input id="new-list-title" type="text" name="title" class="new-list-input" placeholder="Naam..." required maxlength="100"
                       onkeydown="if(event.key==='Escape'){this.closest('form').classList.remove('open');}">
                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                    <label style="font-size:.7rem;color:#64748b;">Kleur:</label>
                    @foreach(['#64748b','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ef4444','#f97316','#ec4899'] as $c)
                    <label style="cursor:pointer;">
                        <input type="radio" name="color" value="{{ $c }}" style="display:none;" {{ $loop->first ? 'checked' : '' }} onchange="document.getElementById('color-preview').style.background='{{ $c }}'">
                        <span style="display:block;width:14px;height:14px;border-radius:50%;background:{{ $c }};border:2px solid transparent;transition:border-color .1s;"
                              onclick="document.querySelectorAll('.color-opt').forEach(e=>e.style.borderColor='transparent');this.style.borderColor='#1e293b';"
                              class="color-opt"></span>
                    </label>
                    @endforeach
                </div>
                <button type="submit" style="width:100%;background:#f59e0b;color:#fff;border:none;border-radius:.4rem;padding:.4rem;font-size:.8rem;font-weight:600;cursor:pointer;">Opslaan</button>
            </form>
        </div>
    </div>

    {{-- ── RIGHT: Taken ── --}}
    <div class="task-main" id="task-main">

        @if(! $selectedList)
        {{-- Lege staat: geen lijsten --}}
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                <rect x="9" y="3" width="6" height="4" rx="2"/>
                <line x1="9" y1="12" x2="15" y2="12"/>
                <line x1="9" y1="16" x2="13" y2="16"/>
            </svg>
            <p>Maak een lijst aan om te beginnen.</p>
        </div>

        @else

        {{-- List header --}}
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:2rem;">
            <span class="list-dot" style="background:{{ $selectedList->color }};width:12px;height:12px;"></span>
            <span class="list-header-title"
                  contenteditable="true"
                  data-list-id="{{ $selectedList->id }}"
                  data-original="{{ $selectedList->title }}"
                  onblur="Tasks.saveListTitle({{ $selectedList->id }}, this)"
                  onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">{{ $selectedList->title }}</span>
            <span style="font-size:.8rem;color:#94a3b8;margin-left:.25rem;">{{ $pendingTasks->count() }} {{ $pendingTasks->count() === 1 ? 'taak' : 'taken' }}</span>
            <div style="margin-left:auto;">
                <button onclick="crmConfirm('Lijst \'{{ addslashes($selectedList->title) }}\' en alle taken verwijderen?', () => document.getElementById('delete-list-form').submit())"
                        style="font-size:.75rem;color:#ef4444;background:none;border:1px solid #fee2e2;border-radius:.4rem;padding:.3rem .65rem;cursor:pointer;transition:background .12s;"
                        onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">
                    Lijst verwijderen
                </button>
                <form id="delete-list-form" method="POST" action="{{ route('task-lists.destroy', $selectedList) }}" style="display:none;">
                    @csrf @method('DELETE')
                </form>
            </div>
        </div>

        {{-- Openstaande taken --}}
        <div id="pending-tasks">
            @forelse($pendingTasks as $task)
                @include('tasks._task_row', ['task' => $task])
            @empty
            <div id="empty-pending" style="text-align:center;padding:2.5rem 1rem;color:#94a3b8;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" style="margin:0 auto .75rem;display:block;" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <p style="font-size:.875rem;">Geen open taken — voeg er een toe!</p>
            </div>
            @endforelse
        </div>

        {{-- Quick-add --}}
        <div style="margin-top:.75rem;padding:.25rem;">
            <input type="text"
                   id="quick-add-main"
                   class="quick-add"
                   placeholder="+ Taak toevoegen... (Enter)"
                   data-list-id="{{ $selectedList->id }}"
                   onkeydown="if(event.key==='Enter'){Tasks.addTask(this);}">
        </div>

        {{-- Afgeronde taken --}}
        @if($completedTasks->count() > 0)
        <div style="margin-top:1.5rem;">
            <div class="completed-divider" onclick="Tasks.toggleCompleted(this)">
                <div class="completed-divider-line"></div>
                <span class="completed-divider-label">Afgerond ({{ $completedTasks->count() }})</span>
                <svg class="completed-divider-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
                <div class="completed-divider-line"></div>
            </div>
            <div id="completed-tasks" style="display:none;">
                @foreach($completedTasks as $task)
                    @include('tasks._task_row', ['task' => $task])
                @endforeach
            </div>
        </div>
        @endif

        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
const Tasks = {
    csrf: document.querySelector('meta[name="csrf-token"]')?.content,

    async fetch(url, method = 'GET', body = null) {
        const opts = {
            method,
            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
        };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const r = await fetch(url, opts);
        if (!r.ok) throw new Error(await r.text());
        return r.json();
    },

    // ── Toggle afgerond ─────────────────────────────────────────────────────
    async toggle(taskId, checkEl) {
        const row      = document.getElementById('task-row-' + taskId);
        const titleEl  = row.querySelector('.task-title');
        const isNowDone = !checkEl.classList.contains('checked');

        // Optimistische UI
        checkEl.classList.toggle('checked', isNowDone);
        checkEl.innerHTML = isNowDone
            ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
            : '';
        titleEl.classList.toggle('done', isNowDone);

        try {
            await this.fetch('/taken/' + taskId + '/toggle', 'POST');
            if (isNowDone) {
                // Verplaats naar afgerond sectie na animatie
                setTimeout(() => {
                    const completedWrap = document.getElementById('completed-tasks');
                    if (completedWrap) {
                        completedWrap.prepend(row);
                    } else {
                        row.remove();
                    }
                    this._checkEmpty();
                }, 350);
            } else {
                // Verplaats terug naar openstaand
                const pending = document.getElementById('pending-tasks');
                if (pending) {
                    pending.prepend(row);
                    this._checkEmpty();
                }
            }
        } catch(e) {
            // Terug draaien bij fout
            checkEl.classList.toggle('checked', !isNowDone);
            titleEl.classList.toggle('done', !isNowDone);
        }
    },

    // ── Titel opslaan ────────────────────────────────────────────────────────
    async saveTitle(taskId, el) {
        const title = el.innerText.trim();
        if (!title) { el.innerText = el.dataset.original; return; }
        if (title === el.dataset.original) return;
        try {
            await this.fetch('/taken/' + taskId, 'PATCH', { title });
            el.dataset.original = title;
        } catch(e) {
            el.innerText = el.dataset.original;
        }
    },

    // ── Lijst-titel opslaan ──────────────────────────────────────────────────
    async saveListTitle(listId, el) {
        const title = el.innerText.trim();
        if (!title) { el.innerText = el.dataset.original; return; }
        if (title === el.dataset.original) return;
        try {
            await this.fetch('/takenlijsten/' + listId, 'PATCH', { title });
            el.dataset.original = title;
        } catch(e) {
            el.innerText = el.dataset.original;
        }
    },

    // ── Vlag togglen ────────────────────────────────────────────────────────
    async flag(taskId, btn) {
        try {
            const data = await this.fetch('/taken/' + taskId + '/flag', 'POST');
            btn.classList.toggle('flagged', data.flagged);
            btn.title = data.flagged ? 'Vlag verwijderen' : 'Markeer als belangrijk';
            const svg = btn.querySelector('svg');
            svg.setAttribute('fill', data.flagged ? '#ef4444' : 'none');
            svg.setAttribute('stroke', data.flagged ? '#ef4444' : 'currentColor');

            const row = document.getElementById('task-row-' + taskId);
            const pending = document.getElementById('pending-tasks');
            if (data.flagged) {
                // Zet bovenaan
                pending.prepend(row);
            }
        } catch(e) {
            console.error('Flag fout:', e);
        }
    },

    // ── Verwijderen ──────────────────────────────────────────────────────────
    remove(taskId) {
        crmConfirm('Taak verwijderen?', async () => {
            try {
                await this.fetch('/taken/' + taskId, 'DELETE');
                document.getElementById('task-row-' + taskId)?.remove();
                this._checkEmpty();
            } catch(e) {
                console.error('Kon taak niet verwijderen:', e);
            }
        });
    },

    // ── Taak toevoegen (quick-add) ───────────────────────────────────────────
    async addTask(input) {
        const title  = input.value.trim();
        const listId = input.dataset.listId;
        if (!title || !listId) return;
        input.value = '';

        try {
            const task = await this.fetch('/taken', 'POST', { task_list_id: parseInt(listId), title });
            this._prependTask(task);
            this._checkEmpty();
        } catch(e) {
            input.value = title;
            alert('Kon taak niet opslaan.');
        }
    },

    // ── Subtaak toevoegen ────────────────────────────────────────────────────
    async addSubtask(input) {
        const title    = input.value.trim();
        const parentId = parseInt(input.dataset.parentId);
        const listId   = parseInt(input.dataset.listId);
        if (!title) return;
        input.value = '';
        input.closest('.subtask-add-wrap').style.display = 'none';

        try {
            const task = await this.fetch('/taken', 'POST', {
                task_list_id: listId,
                parent_task_id: parentId,
                title,
            });
            // Voeg subtaak-row in in de parent
            const parentRow = document.getElementById('task-row-' + parentId);
            let wrap = parentRow.querySelector('.subtasks-wrap');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.className = 'subtasks-wrap';
                wrap.style.marginTop = '.4rem';
                parentRow.querySelector('.task-title').insertAdjacentElement('afterend', wrap);
            }
            wrap.insertAdjacentHTML('beforeend', this._subtaskHtml(task));
        } catch(e) {
            alert('Kon subtaak niet opslaan.');
        }
    },

    showSubtaskInput(taskId) {
        const row  = document.getElementById('task-row-' + taskId);
        const wrap = row.querySelector('.subtask-add-wrap');
        wrap.style.display = 'block';
        wrap.querySelector('input').focus();
    },

    toggleCompleted(divider) {
        const wrap    = document.getElementById('completed-tasks');
        const chevron = divider.querySelector('.completed-divider-chevron');
        const isOpen  = wrap.style.display !== 'none';
        wrap.style.display = isOpen ? 'none' : 'block';
        chevron.classList.toggle('open', !isOpen);
    },

    // ── Helpers ──────────────────────────────────────────────────────────────
    _checkEmpty() {
        const pending  = document.getElementById('pending-tasks');
        const emptyEl  = document.getElementById('empty-pending');
        if (!pending) return;
        const hasTasks = pending.querySelectorAll('.task-row').length > 0;
        if (emptyEl) {
            emptyEl.style.display = hasTasks ? 'none' : 'block';
        } else if (!hasTasks) {
            pending.insertAdjacentHTML('afterbegin',
                '<div id="empty-pending" style="text-align:center;padding:2.5rem 1rem;color:#94a3b8;"><p style="font-size:.875rem;">Geen open taken — voeg er een toe!</p></div>'
            );
        }
    },

    _prependTask(task) {
        const pending = document.getElementById('pending-tasks');
        document.getElementById('empty-pending')?.remove();
        const dueChip = task.due_status ? `<span class="due-chip ${task.due_status}">📅 ${task.due_date ?? ''}</span>` : '';
        const html = `
        <div class="task-row" id="task-row-${task.id}" data-task-id="${task.id}">
            <div class="task-check" onclick="Tasks.toggle(${task.id}, this)" title="Markeer als afgerond">
            </div>
            <div style="flex:1;min-width:0;">
                <div class="task-title"
                     contenteditable="true" spellcheck="false"
                     data-task-id="${task.id}"
                     data-original="${this._esc(task.title)}"
                     onblur="Tasks.saveTitle(${task.id}, this)"
                     onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
                >${this._esc(task.title)}</div>
                ${dueChip ? `<div style="margin-top:.25rem;">${dueChip}</div>` : ''}
                <div class="subtasks-wrap" style="margin-top:.4rem;"></div>
                <div class="subtask-add-wrap" style="display:none;margin-top:.35rem;">
                    <input type="text" class="quick-add subtask-input" placeholder="Subtaak toevoegen..."
                           data-parent-id="${task.id}" data-list-id="${task.task_list_id}"
                           onkeydown="if(event.key==='Enter'){Tasks.addSubtask(this);}if(event.key==='Escape'){this.closest('.subtask-add-wrap').style.display='none';this.value='';}"
                           onblur="if(!this.value.trim()){this.closest('.subtask-add-wrap').style.display='none';}">
                </div>
            </div>
            <button class="task-flag-btn" title="Markeer als belangrijk" onclick="Tasks.flag(${task.id}, this)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            </button>
            <div class="task-actions">
                <button class="task-action-btn" title="Subtaak toevoegen" onclick="Tasks.showSubtaskInput(${task.id})">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
                <button class="task-action-btn task-action-delete" title="Verwijderen" onclick="Tasks.remove(${task.id})">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>`;
        pending.insertAdjacentHTML('afterbegin', html);
    },

    _subtaskHtml(task) {
        return `
        <div class="task-row subtask-row" id="task-row-${task.id}" data-task-id="${task.id}">
            <div class="task-check" onclick="Tasks.toggle(${task.id}, this)" title="Markeer als afgerond"></div>
            <div style="flex:1;min-width:0;">
                <div class="task-title"
                     contenteditable="true" spellcheck="false"
                     data-task-id="${task.id}"
                     data-original="${this._esc(task.title)}"
                     onblur="Tasks.saveTitle(${task.id}, this)"
                     onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
                >${this._esc(task.title)}</div>
            </div>
            <div class="task-actions">
                <button class="task-action-btn task-action-delete" title="Verwijderen" onclick="Tasks.remove(${task.id})">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>`;
    },

    _esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    },
};
</script>
@endpush
