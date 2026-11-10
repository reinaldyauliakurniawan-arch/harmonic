<?php

namespace App\Services;

use App\Models\Belief;
use App\Models\Fundamental;
use Illuminate\Support\Facades\DB;

/** The Fundamentals: one draft, immutable published versions (PRD D-01 to D-04). */
class Fundamentals
{
    public const PARTS = [
        'beliefs' => 'Belief System', 'why_way' => 'The Why & Way', 'objective' => 'Long-Term Objective',
        'main_stage' => 'The Main Stage', 'snapshot_3y' => 'The 3-Year Snapshot', 'annual' => 'Annual Priority',
    ];

    public function __construct(private Access $access, private Tenant $tenant) {}

    public static function blank(int $year): array
    {
        return [
            'why' => '', 'way' => '',
            'objective' => ['statement' => '', 'target_year' => $year + 10],
            'main_stage' => ['target_market' => '', 'uniques' => ['', '', ''], 'proven_process' => [], 'guarantee' => ''],
            'snapshot_3y' => ['target_date' => '', 'revenue' => '', 'profit' => '', 'measurables' => [], 'picture' => []],
            'annual' => ['target_date' => '', 'revenue' => '', 'profit' => '', 'measurables' => [], 'goals' => []],
        ];
    }

    public function latest(): ?Fundamental
    {
        return Fundamental::where('status', 'published')->orderByDesc('version')->first();
    }

    public function draft(): Fundamental
    {
        $draft = Fundamental::where('status', 'draft')->first();
        if ($draft) {
            return $draft;
        }
        $latest = $this->latest();
        $draft = new Fundamental(['content' => $latest ? collect($latest->content)->except('beliefs')->all() : self::blank((int) now()->year)]);
        $draft->version = ($latest?->version ?? 0) + 1;
        $draft->status = 'draft';
        $draft->save();

        return $draft;
    }

    /** Turns the edit form's flat fields into the content JSON. */
    public static function fromForm(array $in): array
    {
        $lines = fn ($s) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $s)), 'strlen'));
        $pairs = fn ($s, $a, $b) => array_map(function ($l) use ($a, $b) {
            [$x, $y] = array_pad(array_map('trim', explode(':', $l, 2)), 2, '');

            return [$a => $x, $b => $y];
        }, $lines($s));

        return [
            'why' => trim((string) ($in['why'] ?? '')),
            'way' => trim((string) ($in['way'] ?? '')),
            'objective' => ['statement' => trim((string) ($in['objective_statement'] ?? '')), 'target_year' => (int) ($in['objective_year'] ?? 0) ?: null],
            'main_stage' => [
                'target_market' => trim((string) ($in['target_market'] ?? '')),
                'uniques' => array_map(fn ($u) => trim((string) $u), array_pad(array_slice((array) ($in['uniques'] ?? []), 0, 3), 3, '')),
                'proven_process' => $pairs($in['proven_process'] ?? '', 'title', 'description'),
                'guarantee' => trim((string) ($in['guarantee'] ?? '')),
            ],
            'snapshot_3y' => [
                'target_date' => $in['snapshot_date'] ?? '', 'revenue' => trim((string) ($in['snapshot_revenue'] ?? '')), 'profit' => trim((string) ($in['snapshot_profit'] ?? '')),
                'measurables' => $pairs($in['snapshot_measurables'] ?? '', 'label', 'value'), 'picture' => $lines($in['picture'] ?? ''),
            ],
            'annual' => [
                'target_date' => $in['annual_date'] ?? '', 'revenue' => trim((string) ($in['annual_revenue'] ?? '')), 'profit' => trim((string) ($in['annual_profit'] ?? '')),
                'measurables' => $pairs($in['annual_measurables'] ?? '', 'label', 'value'), 'goals' => $lines($in['goals'] ?? ''),
            ],
        ];
    }

    /** @return array<string, string[]> part key => missing items, worded as what to add or fix */
    public function problems(array $c, $beliefs): array
    {
        $len = fn ($s) => mb_strlen((string) $s);
        $out = [];
        $n = $beliefs->count();
        if ($n < 3) {
            $out['beliefs'][] = 'Add '.(3 - $n).' more '.(3 - $n === 1 ? 'belief' : 'beliefs').'.';
        }
        if ($n > 7) {
            $out['beliefs'][] = 'Archive '.($n - 7).' '.($n - 7 === 1 ? 'belief' : 'beliefs').'. The limit is 7.';
        }
        foreach ($beliefs as $b) {
            if ($len($b->description) > 400) {
                $out['beliefs'][] = 'Shorten the description of '.$b->name.' to 400 characters.';
            }
        }
        foreach (['why' => 'The Why', 'way' => 'The Way'] as $k => $label) {
            if ($len($c[$k] ?? '') < 10 || $len($c[$k] ?? '') > 300) {
                $out['why_way'][] = $label.' needs 10 to 300 characters.';
            }
        }
        $o = $c['objective'] ?? [];
        if ($len($o['statement'] ?? '') < 10 || $len($o['statement'] ?? '') > 200) {
            $out['objective'][] = 'The objective needs 10 to 200 characters.';
        }
        $year = (int) ($o['target_year'] ?? 0);
        $now = (int) now()->year;
        if ($year < $now + 5 || $year > $now + 30) {
            $out['objective'][] = 'Set a target year between '.($now + 5).' and '.($now + 30).'.';
        }
        $ms = $c['main_stage'] ?? [];
        if (! $len($ms['target_market'] ?? '') || $len($ms['target_market'] ?? '') > 1000) {
            $out['main_stage'][] = 'Describe the target market in up to 1,000 characters.';
        }
        $uniques = array_filter($ms['uniques'] ?? [], fn ($u) => trim($u) !== '');
        if (count($uniques) < 3) {
            $out['main_stage'][] = 'Add '.(3 - count($uniques)).' more '.(3 - count($uniques) === 1 ? 'unique' : 'uniques').'.';
        }
        foreach ($uniques as $u) {
            if ($len($u) > 120) {
                $out['main_stage'][] = 'Each unique fits in 120 characters.';
                break;
            }
        }
        $steps = count($ms['proven_process'] ?? []);
        if ($steps < 3 || $steps > 7) {
            $out['main_stage'][] = $steps < 3 ? 'Add '.(3 - $steps).' more proven process '.(3 - $steps === 1 ? 'step' : 'steps').'.' : 'Keep the proven process to 7 steps.';
        }
        if ($len($ms['guarantee'] ?? '') > 300) {
            $out['main_stage'][] = 'Keep the guarantee to 300 characters.';
        }
        $s = $c['snapshot_3y'] ?? [];
        if (count($s['measurables'] ?? []) > 5) {
            $out['snapshot_3y'][] = 'Keep it to 5 measurables.';
        }
        $pic = count($s['picture'] ?? []);
        if ($pic < 3 || $pic > 15) {
            $out['snapshot_3y'][] = $pic < 3 ? 'Add '.(3 - $pic).' more picture '.(3 - $pic === 1 ? 'line' : 'lines').'.' : 'Keep the picture to 15 lines.';
        }
        foreach ($s['picture'] ?? [] as $l) {
            if ($len($l) > 200) {
                $out['snapshot_3y'][] = 'Each picture line fits in 200 characters.';
                break;
            }
        }
        $a = $c['annual'] ?? [];
        if (count($a['measurables'] ?? []) > 5) {
            $out['annual'][] = 'Keep it to 5 measurables.';
        }
        $g = count($a['goals'] ?? []);
        if ($g < 3 || $g > 7) {
            $out['annual'][] = $g < 3 ? 'Add '.(3 - $g).' more '.(3 - $g === 1 ? 'goal' : 'goals').'.' : 'Keep it to 7 goals.';
        }
        foreach ($a['goals'] ?? [] as $l) {
            if ($len($l) > 200) {
                $out['annual'][] = 'Each goal fits in 200 characters.';
                break;
            }
        }

        return $out;
    }

    public function activeBeliefs()
    {
        return Belief::whereNull('archived_at')->orderBy('sort_order')->orderBy('id')->get();
    }

    /** D-02: publish the draft as version N+1, with a snapshot of the beliefs. */
    public function publish(Fundamental $draft): Fundamental
    {
        return DB::transaction(function () use ($draft) {
            $content = $draft->content;
            $content['beliefs'] = $this->activeBeliefs()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'description' => (string) $b->description])->values()->all();
            $draft->content = $content;
            $draft->version = ((int) Fundamental::where('status', 'published')->max('version')) + 1;
            $draft->status = 'published';
            $draft->published_by = $this->access->id();
            $draft->published_at = now();
            $draft->save();

            return $draft;
        });
    }
}
