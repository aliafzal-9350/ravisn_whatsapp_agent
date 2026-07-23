<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'title', 'message', 'type', 'is_toast_shown', 'read_at'])]
class SystemNotification extends Model
{
    protected function casts(): array
    {
        return [
            'is_toast_shown' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
