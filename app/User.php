<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

// class User extends Authenticatable implements MustVerifyEmail 
class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'referral_id', 'email', 'username', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    
    const ADMIN_TYPE = 'admin';
    const DEFAULT_TYPE = 'default';
    
    public function isAdmin(){        
        return $this->type === self::ADMIN_TYPE;    
    }

    protected static function boot() {
        parent::boot();

        static::created(function ($user) {
            $user->wallet()->create([
                'amnt' => 0,
                'wall_add' => Str::random(10),
            ]);

            $user->referral()->create([
                'ref_add' => Str::random(5),
            ]);

            $user->account()->create();
        });
    }

    public function wallet() {
        return $this->hasOne(Wallet::class);
    }

    public function referral() {
        return $this->hasOne(Referral::class);
    }

    public function account() {
        return $this->hasOne(Account::class);
    }

    public function usrcode() {
        return $this->hasOne(Usrcode::class);
    }
}
