<x-app title="Winning Formation" wide>
    <x-page-header title="Winning Formation" description="Every seat, who holds it, and what it is responsible for. Teams come from this chart.">
        <x-slot:actions>
            @if ($canManage)<a href="{{ route('org.formation.create', ['parent' => $f->root()?->id]) }}" class="btn btn-primary">Add a seat</a>@endif
        </x-slot:actions>
    </x-page-header>
    <x-errors />
    <div x-data="seatSearch" class="mb-6 max-w-sm">
        <label for="seat-search" class="field-label">Find a seat or person</label>
        <input id="seat-search" type="search" class="input" @input="filter" placeholder="Title or name">
    </div>

    @php
        $card = function ($seat) use ($f, $leadership, $canManage) {
            return view('formation.card', compact('seat', 'f', 'leadership', 'canManage'))->render();
        };
        $render = function ($id) use (&$render, $f, $card) {
            $children = $f->childIds($id);
            $html = '<li class="tree-node">'.$card($f->seat($id));
            if ($children) {
                $html .= '<ul class="tree-children">';
                foreach ($children as $c) {
                    $html .= $render($c);
                }
                $html .= '</ul>';
            }

            return $html.'</li>';
        };
    @endphp
    <div class="relative overflow-x-auto pb-6">
        <ul class="tree">{!! $f->root() ? $render($f->root()->id) : '' !!}</ul>
    </div>

</x-app>
