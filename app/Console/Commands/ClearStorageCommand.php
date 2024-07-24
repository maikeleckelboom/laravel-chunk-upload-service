<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Nette\Utils\FileSystem;

class ClearStorageCommand extends Command
{
    private const int ERROR_CODE = 1;
    private const int SUCCESS_CODE = 0;

    protected $signature = 'storage:clear {user_id?}';
    protected $description = 'clear all files and directories, optionally for a specific user.';

    public function handle(): int
    {
        $userId = $this->argument('user_id');

        if (!$userId) {
            return $this->clearAllStorages();
        }

        $user = User::find($userId);

        if (!$user) {
            $this->error("User $userId not found.");
            return self::ERROR_CODE;
        }

        return $this->clearUserStorage($user);
    }

    private function clearAllStorages(): int
    {
        $users = User::all();

        if ($users->count() >= 10) {
            $this->withProgressBar($users, function ($user) {
                $this->clearUserStorage($user);
            });
        } else {
            $users->each(function ($user) {
                $this->clearUserStorage($user);
            });
        }

        return self::SUCCESS_CODE;
    }

    private function clearUserStorage(User $user): int
    {
        $storagePath = storage_path("app/" . $user->getStoragePrefix());

        if (!$this->storageExists($storagePath)) {
            return self::SUCCESS_CODE;
        }

        FileSystem::delete($storagePath);

        $this->info("Cleared storage for user {$user->id}.");

        return self::SUCCESS_CODE;
    }

    private function storageExists(string $path): bool
    {
        return file_exists($path);
    }
}
