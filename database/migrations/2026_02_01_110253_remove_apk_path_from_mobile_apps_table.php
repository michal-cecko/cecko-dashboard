<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing APK data to mobile_app_versions
        $mobileApps = DB::table('mobile_apps')
            ->whereNotNull('apk_path')
            ->get();

        foreach ($mobileApps as $app) {
            DB::table('mobile_app_versions')->insert([
                'mobile_app_id' => $app->id,
                'version' => '1.0.0',
                'apk_path' => $app->apk_path,
                'changelog' => null,
                'created_at' => $app->created_at,
                'updated_at' => $app->updated_at,
            ]);
        }

        Schema::table('mobile_apps', function (Blueprint $table) {
            $table->dropColumn('apk_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_apps', function (Blueprint $table) {
            $table->string('apk_path')->nullable();
        });

        // Restore APK paths from versions (latest version)
        $versions = DB::table('mobile_app_versions')
            ->select('mobile_app_id', 'apk_path')
            ->whereNotNull('apk_path')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('mobile_app_id');

        foreach ($versions as $version) {
            DB::table('mobile_apps')
                ->where('id', $version->mobile_app_id)
                ->update(['apk_path' => $version->apk_path]);
        }
    }
};
