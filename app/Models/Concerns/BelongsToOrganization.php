<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Services\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            $id = app(Tenant::class)->id();
            if ($id !== null) {
                $query->where($query->getModel()->getTable().'.organization_id', $id);
            }
        });

        static::creating(function ($model) {
            $id = app(Tenant::class)->id();
            if ($id === null && ! $model->organization_id) {
                throw new LogicException('Tenant model created without a resolved organization.');
            }
            // The resolved organization always wins over anything set on the model.
            $model->organization_id = $id ?? $model->organization_id;
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
