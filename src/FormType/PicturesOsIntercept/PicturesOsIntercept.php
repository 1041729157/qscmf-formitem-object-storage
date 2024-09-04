<?php
namespace FormItem\ObjectStorage\FormType\PicturesOsIntercept;

use FormItem\ObjectStorage\Lib\Common;
use FormItem\ObjectStorage\Lib\TUploadConfig;
use Illuminate\Support\Str;
use Qscmf\Builder\FormType\FormType;
use Think\View;
use FormItem\ObjectStorage\Lib\FormTypeItemProp;

class PicturesOsIntercept implements FormType {
    use TUploadConfig;
    use FormTypeItemProp;

    public function build(array $form_type){
        $upload_type_cls = $this->genUploadConfigCls($form_type['extra_attr'],'image');

        $view = new View();
        $view->assign('form', $form_type);
        $view->assign('gid', Str::uuid()->getHex());

        self::commonAssign($upload_type_cls, $view, $form_type);

        $content = $view->fetch(__DIR__ . '/pictures_os_intercept.html');
        return $content;
    }
}