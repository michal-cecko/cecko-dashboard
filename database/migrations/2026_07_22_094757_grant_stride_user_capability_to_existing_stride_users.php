<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stride app access is now gated by the STRIDE_USER capability. Grant it to
     * every account that already uses the app (has a Stride profile) so the
     * gate locks nobody out on deploy.
     */
    public function up(): void
    {
        $strideUserIds = DB::table('stride_profiles')->pluck('user_id')->all();

        DB::table('users')->whereIn('id', $strideUserIds)->eachById(function (object $user) {
            $capabilities = json_decode($user->capabilities ?? '[]', true) ?? [];

            if (in_array('STRIDE_USER', $capabilities, true)) {
                return;
            }

            $capabilities[] = 'STRIDE_USER';

            DB::table('users')
                ->where('id', $user->id)
                ->update(['capabilities' => json_encode($capabilities)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->eachById(function (object $user) {
            $capabilities = json_decode($user->capabilities ?? '[]', true) ?? [];

            if (! in_array('STRIDE_USER', $capabilities, true)) {
                return;
            }

            $capabilities = array_values(array_filter(
                $capabilities,
                fn (string $capability) => $capability !== 'STRIDE_USER',
            ));

            DB::table('users')
                ->where('id', $user->id)
                ->update(['capabilities' => json_encode($capabilities)]);
        });
    }
};
