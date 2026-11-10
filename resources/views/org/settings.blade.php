<x-app width="reading" title="Settings">
    <x-page-header title="Settings" description="How your organization counts weeks and quarters, and the bar for the Personnel Evaluator." />
    <x-errors />
    <form method="POST" action="{{ route('org.settings.update') }}" class="box space-y-5" novalidate>
        @csrf @method('PUT')
        @if (session('confirm_quarter'))
            <div class="banner banner-brass" role="alert">
                <p>This changes how weeks are numbered. Past values stay on their dates.</p>
                <input type="hidden" name="confirmed" value="1">
                <button class="btn btn-primary mt-3">Change quarter start</button>
            </div>
        @endif
        <x-field name="name" label="Organization name" :value="$org->name" required />
        <x-field name="timezone" label="Timezone" type="select" :options="array_combine($zones, $zones)" :value="$org->timezone" />
        <x-field name="week_start_day" label="Week starts on" type="select" :options="[1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday']" :value="$org->week_start_day" />
        <x-field name="quarter_start_date" label="Current quarter starts on" type="date" :value="$org->quarter_start_date?->toDateString()" help="A quarter is 13 weeks. The next one starts the day after week 13 ends." required />
        <fieldset>
            <legend class="subheading mb-1">Personnel Evaluator bar</legend>
            <p class="help mb-3">A person meets the bar with no more than these ratings.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="evaluator_bar_max_mixed" label="Most “Sometimes” ratings" type="number" min="0" max="20" :value="$org->evaluator_bar_max_mixed" />
                <x-field name="evaluator_bar_max_minus" label="Most “Rarely” ratings" type="number" min="0" max="20" :value="$org->evaluator_bar_max_minus" />
            </div>
        </fieldset>
        <button class="btn {{ session('confirm_quarter') ? '' : 'btn-primary' }}">Save changes</button>
    </form>
</x-app>
