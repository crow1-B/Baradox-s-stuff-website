{{-- The project picker, shared by the add bar and the edit dialog. Sitewide is first and
     is the default; an empty value is what the controller stores as null. --}}
<select class="fu-input fu-select" id="{{ $id }}" name="project_id" @if ($label ?? null) aria-label="{{ $label }}" @endif>
    <option value="">Sitewide</option>
    @foreach ($pickerOrder as $status)
        @php $inStatus = $projects->where('status', $status); @endphp
        @if ($inStatus->isNotEmpty())
            <optgroup label="{{ $labels[$status] }}">
                @foreach ($inStatus as $project)
                    <option value="{{ $project->id }}" @selected((string) ($selected ?? '') === (string) $project->id)>{{ $project->title }}</option>
                @endforeach
            </optgroup>
        @endif
    @endforeach
</select>
