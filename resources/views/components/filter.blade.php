@props(['name', 'label', 'options' => [], 'value' => null])
<label>{{ $label }}
    <select name="{{ $name }}" class="input input-sm">
        @foreach ($options as $k => $v)<option value="{{ $k }}" @selected((string) $value === (string) $k)>{{ $v }}</option>@endforeach
    </select>
</label>
