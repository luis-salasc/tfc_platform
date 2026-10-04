<?php

namespace App\Models;

use App\Enums\TimeclockCorrectionStatus;
use App\Enums\TimeclockCorrectionType;
use App\Enums\TimeclockEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeclockCorrectionRequest extends Model
{
    protected $fillable = [
        'organization_id', 'location_id', 'employee_user_id', 'requested_by_user_id', 'local_date',
        'correction_type', 'original_event_id', 'proposed_event_type', 'proposed_occurred_at',
        'reason', 'status', 'resolved_by_user_id', 'resolved_at', 'resolution_comment',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function employee(): BelongsTo { return $this->belongsTo(User::class, 'employee_user_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function originalEvent(): BelongsTo { return $this->belongsTo(TimeclockEvent::class, 'original_event_id'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by_user_id'); }

    protected function casts(): array
    {
        return [
            'correction_type' => TimeclockCorrectionType::class,
            'status' => TimeclockCorrectionStatus::class,
            'proposed_event_type' => TimeclockEventType::class,
            'proposed_occurred_at' => 'immutable_datetime',
            'local_date' => 'date:Y-m-d',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
