@php
    $done = $update->is_done;
    // Grouped view: the project is the group header, so the row doesn't repeat it.
    // Flat view: the row is all there is, so it carries the link / the orphan marker.
    $showProject = $showProject ?? false;
@endphp

<li class="fu-row" data-fu-row data-done="{{ $done ? '1' : '0' }}">
    {{-- Its own little form: the checkbox IS the control, and future-updates.js submits it
         on change. `is_done` is only sent when checked, which is exactly the semantics. --}}
    <form method="POST" action="{{ route('future-updates.done', $update) }}" class="fu-row__check" data-fu-done-form>
        @csrf
        @method('PATCH')
        <input type="checkbox"
               class="fu-check"
               name="is_done"
               value="1"
               data-fu-done-check
               @checked($done)
               aria-label="{{ $done ? 'Mark as still to do' : 'Mark done' }}">
    </form>

    <div class="fu-row__body">
        <p class="fu-row__text" dir="auto">{{ $update->content }}</p>

        @if ($showProject)
            <p class="fu-row__meta">
                @if ($update->project)
                    <a class="fu-link" href="{{ route('projects') }}?tab={{ $update->project->status }}&highlight={{ $update->project->id }}">
                        <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>{{ $update->project->title }}
                    </a>
                @elseif ($update->isOrphaned())
                    <span class="fu-orphan">
                        <i class="fa-solid fa-link-slash" aria-hidden="true"></i>was: {{ $update->orphaned_project_title }}
                    </span>
                @else
                    <span class="fu-faint"><i class="fa-solid fa-globe" aria-hidden="true"></i>Sitewide</span>
                @endif
            </p>
        @endif
    </div>

    <div class="fu-row__actions">
        <button type="button" class="fu-act" data-fu-open="edit" data-update="{{ $update->id }}" title="Edit" aria-label="Edit this update">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
        </button>
        <button type="button" class="fu-act fu-act--danger" data-fu-open="delete" data-update="{{ $update->id }}" title="Delete" aria-label="Delete this update">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
        </button>
    </div>
</li>
