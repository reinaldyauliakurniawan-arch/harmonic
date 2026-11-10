<x-app :title="$event->title" wide>
    <x-page-header :title="$event->title" :description="\App\Services\Calendar::formatDate($cal->local($event->starts_at)).', '.$cal->local($event->starts_at)->format('H:i')" />
    <x-errors />
    @if (! $meeting)
        @if ($canStart)
            <form method="POST" action="{{ route('org.meetings.start', $event) }}">@csrf<button class="btn btn-primary">Start meeting</button></form>
            <p class="help mt-2">Starting marks every team member present. You can change that in the meeting.</p>
        @else
            <p class="text-ink-secondary">The team leader starts this meeting. If the leader's seat is vacant, any team member can.</p>
        @endif
    @elseif (! $meeting->isLive())
        @include('meetings.recap')
    @else
        @php $seg = $meeting->segment; @endphp
        <div x-data="meetingLive" data-state="{{ route('org.meetings.state', $meeting) }}" data-segment="{{ $seg }}"
             data-started="{{ $meeting->segment_started_at->timestamp }}" data-minutes="{{ $minutes[$seg] }}" data-now="{{ now()->timestamp }}">
            <nav class="mb-6 flex flex-wrap gap-x-5 border-b border-line" aria-label="Segments">
                @foreach ($segments as $i => $name)
                    <span class="tab {{ $i < $seg ? 'text-ink-secondary' : '' }}" @if ($i === $seg) aria-current="step" aria-selected="true" @endif>{{ $name }} <span class="text-caption text-ink-muted">{{ $minutes[$i] }}m</span></span>
                @endforeach
            </nav>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2 class="heading">{{ $segments[$seg] }}</h2>
                <p class="heading tabular-nums" :class="overClass" x-text="clock" aria-hidden="true"></p>
                <p class="sr-only" aria-live="polite">Now: {{ $segments[$seg] }}</p>
                @if ($facilitator)
                    <div class="flex gap-2">
                        @if ($seg > 0)<form method="POST" action="{{ route('org.meetings.segment', $meeting) }}">@csrf<input type="hidden" name="segment" value="{{ $seg - 1 }}"><button class="btn">Back</button></form>@endif
                        @if ($seg < 6)<form method="POST" action="{{ route('org.meetings.segment', $meeting) }}">@csrf<input type="hidden" name="segment" value="{{ $seg + 1 }}"><button class="btn btn-primary">Next: {{ $segments[$seg + 1] }}</button></form>
                        @else<x-confirm :action="route('org.meetings.end', $meeting)" message="End the meeting? Problem ranks reset." label="End meeting" btn="btn btn-primary" />@endif
                    </div>
                @endif
            </div>

            @includeIf('meetings.segment-'.$seg)

            <section class="mt-10 max-w-3xl border-t border-line pt-6">
                <label for="meeting-notes" class="subheading">Meeting notes</label>
                @if ($participant)
                    <div x-data="autosave" data-url="{{ route('org.meetings.notes', $meeting) }}" data-field="notes">
                        <textarea id="meeting-notes" class="input mt-2" rows="4" maxlength="10000" @blur="save" placeholder="Decisions and anything worth keeping. Saves when you leave the field.">{{ $meeting->notes }}</textarea>
                        <p class="help" aria-live="polite"><span class="saved" x-show="saved" x-cloak><x-icon name="check" :size="14" />Saved</span><span x-show="failed" x-cloak class="error">Couldn't save. Check your connection and try again.</span></p>
                    </div>
                @else
                    <p class="mt-2 whitespace-pre-line text-small">{{ $meeting->notes ?: 'No notes yet.' }}</p>
                @endif
            </section>

            <details class="mt-8">
                <summary class="cursor-pointer label">Attendance ({{ collect($attendance)->filter()->count() }} present)</summary>
                <div class="rows mt-2 max-w-md">
                    @foreach ($members as $id => $m)
                        <div class="flex min-h-11 items-center justify-between">
                            <span>{{ $m->name }}</span>
                            @if ($participant)
                                <form method="POST" action="{{ route('org.meetings.attendance', $meeting) }}">@csrf<input type="hidden" name="member_id" value="{{ $id }}"><button class="btn btn-sm">{{ ($attendance[$id] ?? false) ? 'Present' : 'Absent' }}</button></form>
                            @else
                                <span class="text-small">{{ ($attendance[$id] ?? false) ? 'Present' : 'Absent' }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
    @endif
</x-app>
