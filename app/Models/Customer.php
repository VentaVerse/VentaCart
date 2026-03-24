<?php

namespace App\Models;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notification;

class Customer extends Authenticatable
{
    /**
     * Send the password reset notification to the customer route (not admin).
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('customer.password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);

        $this->notify(new class($url) extends Notification {
            public function __construct(private string $url) {}

            public function via($notifiable): array
            {
                return ['mail'];
            }

            public function toMail($notifiable): \Illuminate\Notifications\Messages\MailMessage
            {
                return (new \Illuminate\Notifications\Messages\MailMessage)
                    ->subject('Reset Your Password')
                    ->greeting('Hello ' . $notifiable->first_name . '!')
                    ->line('You are receiving this email because a password reset was requested for your account.')
                    ->action('Reset Password', $this->url)
                    ->line('This link will expire in 60 minutes.')
                    ->line('If you did not request a password reset, no further action is required.');
            }
        });
    }

    protected $fillable = [
        'store_id', 'customer_group_id', 'first_name', 'last_name',
        'email', 'phone', 'password', 'is_active', 'newsletter', 'legacy_oc_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'newsletter' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cart(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Cart::class)->latest();
    }

    public function defaultAddress()
    {
        return $this->addresses()->where('is_default', true)->first();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
