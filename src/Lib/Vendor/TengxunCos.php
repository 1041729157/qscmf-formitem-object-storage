<?php


namespace FormItem\ObjectStorage\Lib\Vendor;


use FormItem\ObjectStorage\Lib\Common;
use FormItem\ObjectStorage\Lib\UploadConfig;
use GuzzleHttp\Psr7\Utils;

class TengxunCos implements IVendor
{
    public $vendor_type = Context::VENDOR_TENGXUN_COS;

    private $_client;
    private $_upload_config;
    private $_vendor_config;

    public function __construct()
    {
        $this->setVendorConfig([
            'accessKey' => env('COS_SECRETID'),
            'secretKey' => env('COS_SECRETKEY'),
            'bucket' => env('COS_BUCKET'),
            'endPoint' => env('COS_ENDPOINT'),
            'region' => env('COS_REGION'),
            'host' => env('COS_HOST'),
            'upload_host' => env('COS_UPLOAD_HOST'),
            'host_key' => 'cos_host',
            'upload_host_key' => 'cos_host',
        ]);
    }

    public function getVendorType():string{
        return $this->vendor_type;
    }

    public function setUploadConfig(string $type, ?array $config = []): IVendor
    {
        $this->_upload_config = new UploadConfig($type, $config);

        return $this;
    }

    public function getUploadConfig():UploadConfig{
        return $this->_upload_config;
    }

    public function getClient()
    {
        return $this->_client;
    }

    public function getVendorConfig():VendorConfig
    {
        return $this->_vendor_config;
    }

    public function setBucket(string $bucket): IVendor
    {
        $this->_vendor_config->setBucket($bucket);
        return $this;
    }

    public function setEndPoint(string $endPoint): IVendor
    {
        $this->_vendor_config->setEndPoint($endPoint);
        return $this;
    }

    public function setVendorConfig(array $config): IVendor
    {
        $this->_vendor_config = new VendorConfig($config);

        return $this;
    }

    public function getUploadHost(array $config):string{
        return $config[$this->getVendorConfig()->getUploadHostKey()] ?? $config[$this->getVendorConfig()->getHostKey()];
    }

    public function getCosClient($type, ?bool $check_config = true){
        if (!isset($this->_upload_config) || !$this->getUploadConfig()->getAll()){
            $this->setUploadConfig($type);
        }
        if (!$check_config || Common::checkUploadConfig($this)){
            $handle = $this->_handleCosUrl($this->getUploadConfig()->getAll()[$this->getVendorConfig()->getHostKey()]);

            $this->_client = new \Qcloud\Cos\Client([
                'region' => $this->_vendor_config->getRegion(),
                'schema' => $handle['protocol'], //协议头部，默认为 http
                'credentials' => array(
                    'secretId' => $this->_vendor_config->getAccessKey(),
                    'secretKey' => $this->_vendor_config->getSecretKey()
                )
            ]);

            return $this;
        }
    }


    public function getAuthorization($pathname,$method='GET',$queryParams = array(),$headers = array()){

        $secretId = $this->_vendor_config->getAccessKey(); //"云 API 密钥 SecretId";
        $secretKey = $this->_vendor_config->getSecretKey(); //"云 API 密钥 SecretKey";

        // 获取个人 API 密钥 https://console.qcloud.com/capi
        $sid = $secretId;
        $skey = $secretKey;

        // 工具方法
        function getObjectKeys($obj)
        {
            $list = array_keys($obj);
            sort($list);
            return $list;
        }

        function obj2str($obj)
        {
            $list = [];
            $keyList = getObjectKeys($obj);
            $len = count($keyList);
            for ($i = 0; $i < $len; $i++) {
                $key = $keyList[$i];
                $val = isset($obj[$key]) ? $obj[$key] : '';
                $key = strtolower($key);
                $key = urlencode($key);
                $list[] = $key . '=' . urlencode($val);
            }
            return implode('&', $list);
        }

        // 签名有效起止时间
        $now = time() - 1;
        $expired = $now + 600; // 签名过期时刻，600 秒后

        // 要用到的 Authorization 参数列表
        $qSignAlgorithm = 'sha1';
        $qAk = $sid;
        $qSignTime = $now . ';' . $expired;
        $qKeyTime = $now . ';' . $expired;
        $qHeaderList = strtolower(implode(';', getObjectKeys($headers)));
        $qUrlParamList = strtolower(implode(';', getObjectKeys($queryParams)));

        // 签名算法说明文档：https://www.qcloud.com/document/product/436/7778
        // 步骤一：计算 SignKey
        $signKey = hash_hmac("sha1", $qKeyTime, $skey);

        // 步骤二：构成 FormatString
        $formatString = implode("\n", array(strtolower($method), $pathname, obj2str($queryParams), obj2str($headers), ''));

        // 步骤三：计算 StringToSign
        $stringToSign = implode("\n", array('sha1', $qSignTime, sha1($formatString), ''));

        // 步骤四：计算 Signature
        $qSignature = hash_hmac('sha1', $stringToSign, $signKey);

        // 步骤五：构造 Authorization
        $authorization = implode('&',array(
            'q-sign-algorithm=' . $qSignAlgorithm,
            'q-ak=' . $qAk,
            'q-sign-time=' . $qSignTime,
            'q-key-time=' . $qKeyTime,
            'q-header-list=' . $qHeaderList,
            'q-url-param-list=' . $qUrlParamList,
            'q-signature=' . $qSignature
        ));

        return $authorization;

    }

    protected function genPostSignField(array $policy_data):array{
        $now = time() - 1;
        $expired = $now + 600;

        $qSignAlgorithm = 'sha1';
        $qAk = $this->_vendor_config->getAccessKey();
        $qSignTime = $now . ';' . $expired;
        $qKeyTime = $now . ';' . $expired;

        $policy_data = collect($policy_data)->map(function($value, $key){
            return ['eq', '$'.$key, $value];
        })->values()->all();

        $policy = [
            "expiration" =>  gmt_iso8601($expired),
            "conditions" => array_merge($policy_data, [
                ["q-sign-algorithm" => $qSignAlgorithm],
                ["q-ak" => $qAk],
                ["q-sign-time" => $qSignTime],
            ])
        ];
        $policy_string = json_encode($policy);

        $signKey = hash_hmac("sha1", $qKeyTime, $this->_vendor_config->getSecretKey());
        $stringToSign = sha1($policy_string);
        $signature = hash_hmac("sha1", $stringToSign, $signKey);

        return [
            'policy' => base64_encode($policy_string),
            'q-sign-algorithm' => $qSignAlgorithm,
            'q-ak' => $qAk,
            'q-key-time' => $qKeyTime,
            'q-signature' => $signature,
        ];
    }

    public function genReturnBodyHeader():array{
        $return_body = [
            'bucket' => '${bucket}',
            'key' => '${object}',
            'size' => '${size}',
            'mimeType' => '${mimeType}',
        ];

        $body = base64_encode(json_encode($return_body));

        return ['x-cos-return-body' => $body];
    }

    private function _handleCosUrl($url){
        $res=[];
        $parse=parse_url($url);
        $res['protocol']=$parse['scheme'];
        $res['key']=$parse['path'];

        $host=explode('.',$parse['host']);
        $res['region']=$host[2];
        $res['bucket']=$host[0];

        return $res;
    }

    /**
     * 获取对象元数据
     * @param $bucket_host
     * @param $key
     * @return array
     */
    public function headObj($bucket_host,$key){
        $obj = $this->_client->headObject([
            'Bucket' => $this->_vendor_config->getBucket(),
            'Key' => $key
        ]);
        return $obj->toArray();
    }

    public function genSignedUrl(array $param)
    {
        return $this->_genSignUrl($param['object'],$param['timeout']);
    }

    private function _genSignUrl($key, $expire){

### 使用封装的 getObjectUrl 获取下载签名
        try {
            $bucket =  $this->_vendor_config->getBucket(); //存储桶，格式：BucketName-APPID
            $signedUrl = $this->_client->getObjectUrl($bucket, $key, '+'.$expire.' seconds'); //签名的有效时间
            // 请求成功
            return $signedUrl;
        } catch (\Exception $e) {
            // 请求失败
            E($e);
        }
    }

    public function formatHeicToJpg(string $url):string{
        return self::combineImgOpt($url, 'format/jpg');
    }

    public function resizeImg(string $url, string $width = '', string $height = ''):string
    {
        return self::combineImgOpt($url, 'thumbnail/'.$width.'x'.$height);
    }

    public function combineImgOpt(string $url, string $img_opt):string
    {
        return Common::combineCosUrlImgOpt($url, $img_opt);
    }

    public function extraObject(?array $params = []){
        $body = file_get_contents('php://input');
        parse_str($body, $body_arr);

        $body_arr = $this->headObj($params[$this->getVendorConfig()->getHostKey()],$params['cb_key']);
        $body_arr['filename'] = $params['cb_key'];
        $body_arr['mimeType'] = $this->_extraObjectMimeType($body_arr);

        Common::extraBodyTitle($body_arr);

        return $body_arr;
    }

    public function policyGet($type){
        $get_data = Common::extractParams(I('get.'));

        $is_jump = $get_data['jump'] !== '0';

        $this->setUploadConfig($type);
        $config = $this->getUploadConfig()->getAll();
        $host = $this->getUploadHost($config);

        $ext='';
        if (isset($get_data['title']) && str_contains($get_data['title'], '.')){
            $ext = '.'.pathinfo(urldecode($get_data['title']),PATHINFO_EXTENSION);
        }

        $dir=Common::genObjectName($config,$ext);

        $pathname=$dir;
        $pathname[0] !== '/' && ($pathname = '/' . $pathname);

        $upload_meta = $this->getUploadConfig()->getMeta();
        $upload_meta = Common::injectMeta($upload_meta, $get_data);
        $upload_meta['Content-Type'] = $get_data['file_type'];

        $authorization=$this->getAuthorization($pathname,'POST');

        $redirect_url = Common::getCbUrlByType($type, $this->vendor_type, $get_data['title']??''
            , Common::getHashId(), $get_data['resize']??'', $is_jump, $get_data);

        $policy_data = !$is_jump ? [] : ['success_action_redirect'=> $redirect_url];
        $post_sign_field = $this->genPostSignField($policy_data+$upload_meta);

        $res = [
            'host' => $host,
            'url'=>$host.$pathname,
            'authorization'=>$authorization, // 不起作用？
            'params'=>[
                'key'=>$dir,
                'success_action_redirect'=> $redirect_url
            ]+$upload_meta+$post_sign_field
        ];

        !$is_jump && $this->_handleUnJumpRes($res);

        return $res;
    }

    private function _handleUnJumpRes(&$res):void{
        $params = $res['params'];

        $res['jump_url'] = $params['success_action_redirect'];
        unset($params['success_action_redirect']);
        $params += $this->genReturnBodyHeader();

        $res['params'] = $params;
    }

    public function genClient(string $type, ?bool $check_config = true){
        return $this->getCosClient($type, $check_config);
    }

    private function _extraObjectMimeType(?array $params = []){
        return $params['ContentType'];
    }

    public function extraFile(array $config, array $body_arr):array{
        if ($body_arr['title']){
            $file_data['title'] = $body_arr['title'];
        }else {
            $array = explode('/', $body_arr['Key']);
            $file_data['title'] = end($array);
        }

        $file_data['url'] = $config[$this->getVendorConfig()->getHostKey()] . '/' . $body_arr['Key'];
        $file_data['size'] = $body_arr['ContentLength'];
        $file_data['security'] = $config['security'] ? 1 : 0;
        $file_data['file'] = '';
        $file_data['vendor_type'] = $this->vendor_type;

        return $file_data;
    }

    public function uploadFile(string $file_path, ?string $object_name = '', ?array $header_options = []){
        $output = $this->_client->upload($this->_vendor_config->getBucket(), $object_name,
            Utils::tryFopen($file_path, 'r'), $header_options);
        $output_arr = $output->toArray();
        if (isset($output_arr['ETag']) && $output['ETag']){
            return $object_name;
        }

        return $output;
    }
}