<?php

namespace App\View\Components;

use Illuminate\View\Component;

/**
 * Server-rendered SVG line chart. $series: [['name' => 'Overall', 'values' => [float|null, ...]]],
 * aligned to $labels. $goal: a number, or [min, max] for a band. $status: per-point 'on'|'off'|null for the first series.
 */
class LineChart extends Component
{
    public array $paths = [];

    public array $points = [];

    public array $ticks = [];

    public array $xs = [];

    public ?array $goalLine = null;

    public ?array $goalBand = null;

    public float $min;

    public float $max;

    public int $w = 640;

    public int $h = 220;

    public int $padL = 44;

    public int $padB = 28;

    public function __construct(
        public array $labels,
        public array $series,
        public mixed $goal = null,
        public array $status = [],
        public string $summary = '',
        public ?float $floor = null,
        public ?float $ceiling = null,
    ) {
        $vals = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null)->values()->all();
        $goals = is_array($goal) ? $goal : ($goal !== null ? [$goal] : []);
        $all = array_merge($vals, $goals);
        $this->min = $floor ?? ($all ? min($all) : 0);
        $this->max = $ceiling ?? ($all ? max($all) : 1);
        if ($this->max == $this->min) {
            $this->max = $this->min + 1;
        }
        $n = max(count($labels), 1);
        $plotW = $this->w - $this->padL - 8;
        foreach ($labels as $i => $l) {
            $this->xs[$i] = $this->padL + ($n === 1 ? $plotW / 2 : $plotW * $i / ($n - 1));
        }
        foreach ($series as $si => $s) {
            $d = '';
            $pen = 'M';
            foreach ($s['values'] as $i => $v) {
                if ($v === null) {
                    $pen = 'M';

                    continue;
                }
                $d .= $pen.round($this->xs[$i] ?? 0, 1).' '.round($this->y($v), 1).' ';
                $pen = 'L';
                if ($si === 0) {
                    $this->points[] = ['x' => $this->xs[$i] ?? 0, 'y' => $this->y($v), 'status' => $status[$i] ?? null];
                }
            }
            $this->paths[] = ['d' => trim($d), 'name' => $s['name'], 'dash' => $si > 0];
        }
        if (is_array($goal) && count($goal) === 2) {
            $this->goalBand = [$this->y($goal[1]), $this->y($goal[0])];
        } elseif ($goal !== null) {
            $this->goalLine = [$this->y((float) $goal)];
        }
        foreach ([0, 0.5, 1] as $f) {
            $v = $this->min + ($this->max - $this->min) * $f;
            $this->ticks[] = ['y' => $this->y($v), 'label' => rtrim(rtrim(number_format($v, 2), '0'), '.')];
        }
    }

    public function y(float $v): float
    {
        $plotH = $this->h - $this->padB - 8;

        return 8 + $plotH * (1 - ($v - $this->min) / ($this->max - $this->min));
    }

    public function render()
    {
        return view('components.line-chart');
    }
}
