<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Services\Access;

class EventPolicy
{
    public function __construct(private Access $a) {}

    public function view(User $u, Event $e): bool
    {
        return match ($e->scope) {
            'private' => $this->a->owner($e->created_by),
            'pair' => $this->a->owner($e->created_by) || $this->a->owner($e->partner_id),
            default => $this->a->member(),
        };
    }

    /** Who may create each event type (PRD C-02). */
    public function create(User $u, string $type, string $scope, ?int $team, ?int $partner = null): bool
    {
        $f = $this->a->formation();

        return match ($type) {
            'custom' => match ($scope) {
                'org' => $this->a->leadership() || $this->a->orgAdmin(),
                'team' => $this->a->inTeam($team),
                'private' => $this->a->member(),
                default => false,
            },
            'quarterly_session', 'annual_session' => $scope === 'team' && $this->a->leads($team),
            'guide_shoutout' => in_array($scope, ['team', 'org'], true) && ($scope === 'org' || $f->isTeam($team)) && $this->a->member(),
            'guide_reflection', 'guide_passion' => $scope === 'private' && $this->a->member(),
            'guide_checkin' => $scope === 'pair' && $partner && $this->leadsDirectly($partner),
            default => false,
        };
    }

    /** I hold a seat that is the direct parent of a seat the partner holds. */
    public function leadsDirectly(int $partner): bool
    {
        $f = $this->a->formation();
        foreach ($f->seatIdsHeldBy($partner) as $sid) {
            if (in_array($f->seat($sid)->parent_id, $f->seatIdsHeldBy($this->a->id()), true)) {
                return true;
            }
        }

        return false;
    }

    public function update(User $u, Event $e): bool
    {
        return $this->a->owner($e->created_by) && ! in_array($e->type, ['diagnostic', 'assessment'], true);
    }

    /** Notes on pair events are editable by both participants. */
    public function notes(User $u, Event $e): bool
    {
        return $this->a->owner($e->created_by) || ($e->scope === 'pair' && $this->a->owner($e->partner_id))
            || ($e->type === 'weekly_meeting' && $this->a->inTeam($e->team_seat_id));
    }
}
