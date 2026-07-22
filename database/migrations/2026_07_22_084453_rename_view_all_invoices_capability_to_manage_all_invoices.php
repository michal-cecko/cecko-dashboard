<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameCapability('VIEW_ALL_INVOICES', 'MANAGE_ALL_INVOICES');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renameCapability('MANAGE_ALL_INVOICES', 'VIEW_ALL_INVOICES');
    }

    protected function renameCapability(string $from, string $to): void
    {
        DB::table('users')->eachById(function (object $user) use ($from, $to) {
            $capabilities = json_decode($user->capabilities, true) ?? [];

            if (! in_array($from, $capabilities, true)) {
                return;
            }

            $capabilities = array_map(
                fn (string $capability) => $capability === $from ? $to : $capability,
                $capabilities,
            );

            DB::table('users')
                ->where('id', $user->id)
                ->update(['capabilities' => json_encode($capabilities)]);
        });
    }
};
