<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',  // 🔥 Vector ko array mein convert karo
        ];
    }

    /**
     * Get the document that owns this chunk.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}