<?php

use App\Models\Common\MobileAppVersion;
use App\Models\Common\User;
use App\Models\Invoices\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '!=', '')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    try {
                        $user->addMediaFromDisk($user->avatar_path, 'public')
                            ->toMediaCollection('avatar');
                    } catch (Throwable $e) {
                        logger()->warning("Failed to migrate avatar for user {$user->id}: {$e->getMessage()}");
                    }
                }
            });

        Company::query()
            ->where(function ($q) {
                $q->whereNotNull('logo_path')->where('logo_path', '!=', '')
                    ->orWhere(function ($q) {
                        $q->whereNotNull('signature_path')->where('signature_path', '!=', '');
                    });
            })
            ->chunkById(100, function ($companies) {
                foreach ($companies as $company) {
                    if ($company->logo_path) {
                        try {
                            $company->addMediaFromDisk($company->logo_path, 'public')
                                ->toMediaCollection('logo');
                        } catch (Throwable $e) {
                            logger()->warning("Failed to migrate logo for company {$company->id}: {$e->getMessage()}");
                        }
                    }

                    if ($company->signature_path) {
                        try {
                            $company->addMediaFromDisk($company->signature_path, 'public')
                                ->toMediaCollection('signature');
                        } catch (Throwable $e) {
                            logger()->warning("Failed to migrate signature for company {$company->id}: {$e->getMessage()}");
                        }
                    }
                }
            });

        MobileAppVersion::query()
            ->whereNotNull('apk_path')
            ->where('apk_path', '!=', '')
            ->chunkById(100, function ($versions) {
                foreach ($versions as $version) {
                    try {
                        $version->addMediaFromDisk($version->apk_path, 'local')
                            ->toMediaCollection('apk');
                    } catch (Throwable $e) {
                        logger()->warning("Failed to migrate APK for version {$version->id}: {$e->getMessage()}");
                    }
                }
            });
    }

    public function down(): void
    {
        // Reversing this migration is not practical - old file paths are still intact
    }
};
