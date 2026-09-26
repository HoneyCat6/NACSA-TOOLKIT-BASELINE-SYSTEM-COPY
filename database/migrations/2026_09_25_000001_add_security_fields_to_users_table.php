<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', Role::values())->default(Role::ComplianceOfficer->value)->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('password_changed_at')->nullable()->after('is_active');
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('mfa_verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'is_active', 'password_changed_at',
                'otp_code', 'otp_expires_at', 'otp_attempts', 'mfa_verified_at',
            ]);
        });
    }
};
