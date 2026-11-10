<?php

namespace App\View\Components;

use Illuminate\View\Component;

/** Renders a Lucide SVG from resources/icons at 1.5 px stroke. */
class Icon extends Component
{
    private static array $cache = [];

    public function __construct(public string $name, public int $size = 16, public ?string $label = null) {}

    public function render()
    {
        $inner = self::$cache[$this->name] ??= $this->inner();
        $a11y = $this->label ? 'role="img" aria-label="'.e($this->label).'"' : 'aria-hidden="true"';

        return fn (array $data) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$this->size.'" height="'.$this->size.'" viewBox="0 0 24 24" fill="'.($this->name === 'triangle' ? 'currentColor' : 'none').'" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false" '.$a11y.' '.$data['attributes']->toHtml().'>'.$inner.'</svg>';
    }

    private function inner(): string
    {
        $svg = @file_get_contents(resource_path('icons/'.basename($this->name).'.svg')) ?: '';

        return preg_match('/<svg[^>]*>(.*)<\/svg>/s', $svg, $m) ? trim($m[1]) : '';
    }
}
