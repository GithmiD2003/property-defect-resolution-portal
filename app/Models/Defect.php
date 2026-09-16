<?php

namespace App\Models;

use App\Enums\DefectStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $property_id
 * @property int $room_id
 * @property int $reported_by
 * @property int|null $assigned_to
 * @property string $title
 * @property string $description
 * @property string $category
 * @property string $priority
 * @property DefectStatus $status
 * @property CarbonInterface|null $due_date
 * @property string|null $repair_notes
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $repaired_at
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $verified_at
 * @property CarbonInterface|null $reopened_at
 * @property string|null $reopen_reason
 */
class Defect extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DefectStatus::class,
            'due_date' => 'date',
            'started_at' => 'immutable_datetime',
            'repaired_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<DefectPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(DefectPhoto::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<DefectComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(DefectComment::class);
    }

    /** @return HasMany<DefectActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(DefectActivity::class);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordActivity(
        User $actor,
        string $event,
        string $description,
        array $metadata = [],
    ): void {
        $activity = new DefectActivity([
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        $activity->defect()->associate($this);
        $activity->user()->associate($actor);
        $activity->save();
    }
}
