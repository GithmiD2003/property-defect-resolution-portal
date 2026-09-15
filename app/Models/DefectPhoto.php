<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $defect_id
 * @property int $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property string $type
 */
class DefectPhoto extends Model
{
    protected $guarded = ['*'];

    protected $hidden = [
        'disk',
        'path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<Defect, $this> */
    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
