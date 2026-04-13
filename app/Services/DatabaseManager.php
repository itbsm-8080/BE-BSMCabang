<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseManager
{
    public static function switchToCabang($databaseName)
    {
        // Ganti nama database
        Config::set('database.connections.mysql.database', $databaseName);
        DB::purge('mysql');
        
        \Log::info('Switch database ke: ' . $databaseName);
        
        return true;
    }
    
    public static function resetToPusat()
    {
        Config::set('database.connections.mysql.database', 'bsm');
        DB::purge('mysql');
    }
}
