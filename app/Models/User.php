<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }


    public function getStoragePath(): string
    {
        return "users/{$this->id}";
    }

    /**
     * Gets the storage instance for the user.
     */
    public function getStorageInstance(): Filesystem|FilesystemAdapter
    {
        $root = storage_path("app/" . $this->getStoragePath());

        if (!is_writable($root)) {
            mkdir($root, 0777, true);
        }

        return Storage::createLocalDriver(["root" => $root]);
    }
}
