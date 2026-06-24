<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
// StaffHours is in the same namespace — no extra use needed

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'account_id', 'name', 'phone', 'email', 'photo_path', 'notes', 'is_active', 'public_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new AccountScope());

        static::creating(function (self $model) {
            if (! $model->account_id) {
                $model->account_id = auth()->user()?->account_id;
            }
            if (! $model->public_token) {
                $model->public_token = Str::random(48);
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function deliveryBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'delivery_staff_id');
    }

    public function pickupBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'pickup_staff_id');
    }

    public function hours(): HasMany
    {
        return $this->hasMany(StaffHours::class);
    }

    /** Volledige URL naar de profielfoto, of null als er geen foto is. */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) return null;
        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path);
    }
}
