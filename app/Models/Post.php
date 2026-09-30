<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'title', 'body'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Keep posts whose title or body contains every word of the search term.
     *
     * "!" is the LIKE escape character (portable across SQLite, MySQL and
     * PostgreSQL), so "%" and "_" typed by the user are matched literally.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        foreach (preg_split('/\s+/', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';

            $query->where(fn (Builder $q) => $q
                ->whereRaw("title like ? escape '!'", [$like])
                ->orWhereRaw("body like ? escape '!'", [$like]));
        }
    }
}
