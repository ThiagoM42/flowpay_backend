<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->insert([
            'name'              => 'Admin',
            'email'             => 'admin@hotmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('4815162342'),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'admin@hotmail.com')->delete();
    }
};
