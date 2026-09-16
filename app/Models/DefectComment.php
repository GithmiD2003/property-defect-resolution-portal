<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefectComment extends Model
{
    protected $fillable = [
        'body',
    ];

    /** @return BelongsTo<Defect, $this> */
    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
