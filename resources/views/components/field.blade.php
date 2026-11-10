@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'options' => [], 'placeholder' => null, 'id' => null])
@php
    $key = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $id = $id ?? 'f-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    // The same field name can appear twice on a page (loops, two forms); each label must point at its own input.
    $seen = request()->attributes->get('field.ids', []);
    $n = $seen[$id] = ($seen[$id] ?? 0) + 1;
    request()->attributes->set('field.ids', $seen);
    $id = $n > 1 ? $id.'-'.$n : $id;
    $err = $errors->first($key);
    $val = old($key, $value);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" class="input" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-e" @endif placeholder="{{ $placeholder }}" {{ $attributes->except('class') }}>{{ $val }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="input" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-e" @endif {{ $attributes->except('class') }}>
            @foreach ($options as $k => $v)
                <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>
    @elseif ($type === 'password')
        <div class="relative" x-data="reveal">
            <input id="{{ $id }}" name="{{ $name }}" type="password" value="" class="input pr-16" placeholder="{{ $placeholder }}" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-e" @endif {{ $attributes->except('class') }}>
            <button type="button" class="btn btn-ghost btn-sm absolute right-1 top-1/2 -translate-y-1/2" @click="toggle" :aria-pressed="shown" aria-controls="{{ $id }}" x-text="label">Show</button>
        </div>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val }}" class="input" placeholder="{{ $placeholder }}" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-e" @endif {{ $attributes->except('class') }}>
    @endif
    @if ($err)<p id="{{ $id }}-e" class="error">{{ $err }}</p>@elseif ($help)<p class="help">{{ $help }}</p>@endif
</div>
