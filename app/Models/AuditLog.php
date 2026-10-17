<?php

namespace App\Models;

use App\Enums\AuditCategory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

#[Guarded(['id'])]
#[Table(timestamps: false)]
class AuditLog extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AuditCategory::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Newest first, narrowed by the audit log screens' filters (DECISIONS D-54).
     *
     * @param  Builder<self>  $query
     * @param  array{action?: ?string, user?: ?string, from?: ?string, to?: ?string}  $filters
     */
    #[Scope]
    protected function filtered(Builder $query, array $filters): void
    {
        $query
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['user'] ?? null, fn (Builder $query, string $search) => $query->whereHas(
                'user',
                fn (Builder $users) => $users->where(fn (Builder $match) => $match->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")),
            ))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
