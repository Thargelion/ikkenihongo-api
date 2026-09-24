<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyItem extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'romaji' => 'array',
            'meanings_es' => 'array',
            'meanings_en' => 'array',
            'onyomi' => 'array',
            'kunyomi' => 'array',
        ];
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserItemProgress::class);
    }
}
