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
}
