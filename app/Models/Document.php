<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'title',
        'content',
        'status',
    ];

    /**
     * Get the chunks for this document.
     */
    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class);
    }
}