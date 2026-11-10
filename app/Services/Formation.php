<?php

namespace App\Services;

use App\Models\Seat;
use Illuminate\Support\Collection;

/**
 * The seat tree of one organization and everything derived from it: teams, leadership,
 * ancestry. Built from plain seat rows so Handover can simulate a change before it happens.
 */
class Formation
{
    /** @var array<int, Seat> */
    public array $seats = [];

    /** @var array<int, int[]> */
    private array $children = [];

    public function __construct(iterable $seats)
    {
        foreach ($seats as $seat) {
            $this->seats[$seat->id] = $seat;
        }
        uasort($this->seats, fn ($a, $b) => [$a->sort_order, $a->id] <=> [$b->sort_order, $b->id]);
        foreach ($this->seats as $seat) {
            if ($seat->parent_id) {
                $this->children[$seat->parent_id][] = $seat->id;
            }
        }
    }

    public static function load(): self
    {
        return new self(Seat::with('holder.user')->get());
    }

    /** A copy with some seats' attributes replaced, for "what if" checks. */
    public function with(array $changes): self
    {
        $seats = [];
        foreach ($this->seats as $id => $seat) {
            $copy = clone $seat;
            foreach ($changes[$id] ?? [] as $k => $v) {
                $copy->{$k} = $v;
            }
            if (! ($changes[$id]['__deleted'] ?? false)) {
                $seats[] = $copy;
            }
        }

        return new self($seats);
    }

    public function root(): ?Seat
    {
        foreach ($this->seats as $seat) {
            if (! $seat->parent_id) {
                return $seat;
            }
        }

        return null;
    }

    public function seat(?int $id): ?Seat
    {
        return $id ? ($this->seats[$id] ?? null) : null;
    }

    /** @return int[] */
    public function childIds(int $seatId): array
    {
        return $this->children[$seatId] ?? [];
    }

    /** @return int[] nearest first */
    public function ancestorIds(int $seatId): array
    {
        $ids = [];
        $seat = $this->seat($seatId);
        while ($seat && $seat->parent_id && ! in_array($seat->parent_id, $ids, true)) {
            $ids[] = $seat->parent_id;
            $seat = $this->seat($seat->parent_id);
        }

        return $ids;
    }

    /** @return int[] */
    public function descendantIds(int $seatId): array
    {
        $out = [];
        $stack = $this->childIds($seatId);
        while ($stack) {
            $id = array_pop($stack);
            $out[] = $id;
            array_push($stack, ...$this->childIds($id));
        }

        return $out;
    }

    public function isTeam(?int $seatId): bool
    {
        return $seatId && ! empty($this->children[$seatId]);
    }

    /** @return Seat[] head seats of every derived team, in tree order */
    public function teams(): array
    {
        $out = [];
        $walk = function ($id) use (&$walk, &$out) {
            if ($this->isTeam($id)) {
                $out[] = $this->seats[$id];
            }
            foreach ($this->childIds($id) as $c) {
                $walk($c);
            }
        };
        if ($root = $this->root()) {
            $walk($root->id);
        }

        return $out;
    }

    /** @return int[] seat ids in a team: head plus direct children */
    public function teamSeatIds(int $teamSeatId): array
    {
        return $this->isTeam($teamSeatId) ? [$teamSeatId, ...$this->childIds($teamSeatId)] : [];
    }

    /** @return int[] membership ids in a team */
    public function teamMemberIds(int $teamSeatId): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($id) => $this->seats[$id]->holder_id, $this->teamSeatIds($teamSeatId)))));
    }

    /** @return int[] seat ids held by a member */
    public function seatIdsHeldBy(?int $memberId): array
    {
        if (! $memberId) {
            return [];
        }

        return array_keys(array_filter($this->seats, fn ($s) => $s->holder_id === $memberId));
    }

    /** @return int[] team ids (head seat ids) a member belongs to */
    public function teamIdsOf(?int $memberId): array
    {
        $ids = [];
        foreach ($this->seatIdsHeldBy($memberId) as $seatId) {
            if ($this->isTeam($seatId)) {
                $ids[] = $seatId;
            }
            $parent = $this->seats[$seatId]->parent_id;
            if ($parent && $this->isTeam($parent)) {
                $ids[] = $parent;
            }
        }

        return array_values(array_unique($ids));
    }

    public function leads(?int $memberId, ?int $teamSeatId): bool
    {
        return $memberId && $this->isTeam($teamSeatId) && $this->seats[$teamSeatId]->holder_id === $memberId;
    }

    public function inTeam(?int $memberId, ?int $teamSeatId): bool
    {
        return $memberId && $teamSeatId && in_array($memberId, $this->teamMemberIds($teamSeatId), true);
    }

    /** True when the member holds any ancestor seat of $seatId. */
    public function above(?int $memberId, ?int $seatId): bool
    {
        if (! $memberId || ! $seatId) {
            return false;
        }
        $held = $this->seatIdsHeldBy($memberId);

        return (bool) array_intersect($held, $this->ancestorIds($seatId));
    }

    /** True when the member holds an ancestor of any seat the subject holds. */
    public function aboveMember(?int $memberId, int $subjectId): bool
    {
        foreach ($this->seatIdsHeldBy($subjectId) as $seatId) {
            if ($this->above($memberId, $seatId)) {
                return true;
            }
        }

        return false;
    }

    public function leadershipSeatId(): ?int
    {
        foreach ($this->seats as $seat) {
            if ($seat->is_leadership_team) {
                return $seat->id;
            }
        }

        return $this->root()?->id;
    }

    /** @return int[] the flagged seat's team plus every ancestor of the flagged seat */
    public function leadershipSeatIds(): array
    {
        $flag = $this->leadershipSeatId();
        if (! $flag) {
            return [];
        }

        return array_values(array_unique([$flag, ...$this->childIds($flag), ...$this->ancestorIds($flag)]));
    }

    public function isLeadership(?int $memberId): bool
    {
        return (bool) array_intersect($this->seatIdsHeldBy($memberId), $this->leadershipSeatIds());
    }

    /** Nearest ancestor seat with a holder (D13). */
    public function nearestHolderAbove(int $seatId, ?int $exceptMemberId = null): ?int
    {
        foreach ($this->ancestorIds($seatId) as $id) {
            $h = $this->seats[$id]->holder_id;
            if ($h && $h !== $exceptMemberId) {
                return $h;
            }
        }

        return null;
    }

    /** @return int[] seat ids in a leader's subtree: everything below the seats they hold */
    public function subtreeSeatIds(?int $memberId): array
    {
        $ids = [];
        foreach ($this->seatIdsHeldBy($memberId) as $seatId) {
            array_push($ids, ...$this->descendantIds($seatId));
        }

        return array_values(array_unique($ids));
    }

    /** @return int[] team ids a member leads */
    public function ledTeamIds(?int $memberId): array
    {
        return array_values(array_filter($this->seatIdsHeldBy($memberId), fn ($id) => $this->isTeam($id)));
    }

    /** @return int[] teams a member leads plus every team below them */
    public function teamsInSubtree(?int $memberId): array
    {
        $ids = $this->ledTeamIds($memberId);
        foreach ($this->subtreeSeatIds($memberId) as $id) {
            if ($this->isTeam($id)) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Team options for selects: [id => label] */
    public function teamOptions(): array
    {
        return collect($this->teams())->mapWithKeys(fn ($s) => [$s->id => $s->teamLabel()])->all();
    }

    public function depth(int $seatId): int
    {
        return count($this->ancestorIds($seatId));
    }

    /** @return Collection<int, Seat> seats in depth-first tree order */
    public function ordered(): Collection
    {
        $out = [];
        $walk = function ($id) use (&$walk, &$out) {
            $out[] = $this->seats[$id];
            foreach ($this->childIds($id) as $c) {
                $walk($c);
            }
        };
        if ($root = $this->root()) {
            $walk($root->id);
        }

        return collect($out);
    }
}
