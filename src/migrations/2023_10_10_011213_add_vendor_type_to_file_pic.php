<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVendorTypeToFilePic extends Migration
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
            $columns = DB::select("show columns from " . $this->file_pic_table . " WHERE FIELD in ('vendor_type');");

            collect($columns)->each(function ($column) use($table) {
                $comment = "提供图片存储服务的媒介，如：aliyun_oss 阿里云；tengxun_cos 腾讯云； volcengine_tos 火山引擎, 空的话就是服务器存储";
                if ($column->Field === 'vendor_type'){
                    $table->string("vendor_type", 50)->default("")
                        ->comment($comment)
                        ->after("cate")->change();
                }else{
                    $table->string("vendor_type", 50)->default("")
                        ->comment($comment)
                        ->after("cate");
                }
            });

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
