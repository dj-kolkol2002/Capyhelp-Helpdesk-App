<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'category', 'problem', 'symptoms', 'solution', 'customer_reply', 'tags', 'is_published'])]
class KnowledgeArticle extends Model
{
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
