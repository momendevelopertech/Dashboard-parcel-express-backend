<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

class Facility
{
    // رجّع اسم الجهة من جدولها
    public static function name(string $type, int $id): string
    {
        $map = [
            'App\\Models\\Station' => 'stations',
            'App\\Models\\Hub' => 'hubs',
            'App\\Models\\Branch' => 'branches', // لو موجود
        ];
        $table = $map[$type] ?? null;
        if (!$table)
            return "#{$id}";
        return (string) (DB::table($table)->where('id', $id)->value('name') ?? "#{$id}");
    }

    // هات/أنشئ حساب الجهة مع قفل للكتابة
    public static function lockAccount(string $type, int $id)
    {
        $acc = DB::table('facility_accounts')
            ->where('owner_type', $type)
            ->where('owner_id', $id)
            ->lockForUpdate()
            ->first();

        if (!$acc) {
            DB::table('facility_accounts')->insert([
                'owner_type' => $type,
                'owner_id' => $id,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $acc = DB::table('facility_accounts')
                ->where('owner_type', $type)->where('owner_id', $id)
                ->lockForUpdate()->first();
        }
        return $acc;
    }
}
