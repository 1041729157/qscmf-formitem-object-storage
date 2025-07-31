<?php

namespace FormItem\ObjectStorage\Lib;

use Illuminate\Support\Str;

class Helper
{
    public static function getModelNameFormEnv()
    {
        $file_pic_table = env('OBJECT_STORAGE_FILE_TABLE_NAME', 'qs_file_pic');
        $prefix = env('DB_PREFIX', '');

        return Str::ucfirst(Str::camel(str_replace($prefix, '', $file_pic_table)));
    }
}