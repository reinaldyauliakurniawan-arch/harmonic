<x-app title="My Numbers">
    <x-page-header title="My Numbers" :description="'The metrics your seats own. This week starts '.\App\Services\Calendar::formatDate($week).'.'" />
    <x-errors />
    @if ($list->isEmpty())
        <x-empty heading="You own no numbers yet" text="Every seat should own at least one number. Ask your team leader to add a metric for your seat." />
    @else
        <form method="POST" action="{{ route('org.numbers.store') }}" class="max-w-xl" novalidate>
            @csrf
            <div class="rows">
                @foreach ($list as $m)
                    @php $v = $values[$m->id] ?? null; $l = $last[$m->id] ?? null; @endphp
                    <div class="py-3">
                        <x-field :name="'values['.$m->id.']'" :label="$m->name" inputmode="decimal" autocomplete="off"
                            :value="$v ? rtrim(rtrim(number_format($v->value, $m->decimals, '.', ''), '0'), '.') : ''"
                            :help="'Goal '.\App\Services\Metrics::goalLabel($m->operator, $m->goal_value, $m->goal_value_max, $m->unit, (int) $m->decimals, $currency).($l ? ', Last week '.\App\Services\Metrics::format($l->value, $m->unit, (int) $m->decimals, $currency) : '').', '.$m->teamSeat?->teamLabel()" />
                        @if ($v)<p class="mt-1"><x-status :value="$v->status" /></p>@endif
                    </div>
                @endforeach
            </div>
            <button class="btn btn-primary mt-4 w-full sm:w-auto">Save this week</button>
        </form>
    @endif
</x-app>
