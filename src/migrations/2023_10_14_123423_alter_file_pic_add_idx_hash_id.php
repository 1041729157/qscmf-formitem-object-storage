<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterFilePicAddIdxHashId extends Migration
{

    protected $file_pic_table;

    public function __construct()
    {
        $this->file_pic_table = env('OBJECT_STORAGE_FILE_TABLE_NAME', 'qs_file_pic');
    }

    public function beforeCmmUp()
    {
        //
    }

    public function beforeCmmDown()
    {
        //
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table($this->file_pic_table, function (Blueprint $table) {
            //
            $columns = DB::select("show index FROM `" . $this->file_pic_table . "` WHERE Column_name = 'hash_id'");

            !$columns && $table->index('hash_id','idx_hashId');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }

    public function afterCmmUp()
    {
        //
    }

    public function afterCmmDown()
    {
        //
    }
}
