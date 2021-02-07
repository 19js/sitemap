<?php
namespace Tom\Sitemap;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FirePHPHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use QL\QueryList;

class Sitemap
{
    protected $url;
    public $res;
    public $now;
    protected $logger;
    public $xml;
    protected $loggerHandler=null;
    public function __construct()
    {
        $this->logger=new Logger('sitemap');
        if($this->loggerHandler == null)
        {
            $this->loggerHandler = new ErrorLogHandler();
        }
        $this->logger->pushHandler($this->loggerHandler, Logger::DEBUG);
        $this->logger->pushHandler(new FirePHPHandler());
    }

    /**
     * 设置日志处理
     * @param HandlerInterface $handler
     */
    public function setLoggerHandle(HandlerInterface $handler)
    {
        $this->loggerHandler=$handler;
    }
    /**
     * @param $url 网站首页地址
     * @param $dir sitemap.xml存储文件夹
     * @param int $type 0:响应式 1：wap 2: 自适应 3：pc
     */
    public static function handle($url,$dir,$type=0)
    {
        set_time_limit(0);
        ini_set('memory_limit','-1');
       $self=new self();
       $self->url=$url;
       $urls=$self->getFirst($url);
       $urls=$self->filterUrl($urls);
       $self->logger->info('首页信息获取成功');
       $self->res=$urls;
       $self->logger->info('开始查找内页');
       $self->multiGetData($urls);
       $self->logger->info('数据获取success');
       $type=$self->getType($type);
       $data=$self->calDataToXmlArray($self->res,$type);
       $xml=$self->arrayToXml($data);
       $self->$xml=$xml;
       $self->storeData($xml,$dir);
       return $self;
    }
    protected function storeData($xml,$dir){
        if(!file_exists($dir)){
            @mkdir($dir);
        }
        $filename=$dir.'/sitemap.xml';
        file_put_contents($filename,$xml);
        $this->logger->info('生成sitemap成功，位于：'.$filename);
    }
    // 将数组转化xml
    protected function arrayToXml($arr){
        $xml='<?xml version="1.0" encoding="UTF-8" ?>'."\n";
        $xml .='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'."\n";
        $xml .= ' xmlns:mobile="http://www.baidu.com/schemas/sitemap-mobile/1/"> '."\n";
        foreach ($arr as $vo)
        {
            $xml.='<url>';
            foreach ($vo as $k=>$vv)
            {
                if($k == 'mobile:mobile'){
                    $xml .='<mobile:mobile type="'.$vv.'"/>'."\n";
                }else{
                    $xml .= '<'.$k.'>'.$vv.'</'.$k.'>'."\n";
                }
            }
            $xml.='</url>';
        }
        $xml .= '</urlset>';
        $this->logger->info('生成xml成功!');
        return $xml;
    }
    protected function calDataToXmlArray($arr,$type='none')
    {
        $temp= [];
        if($arr){
            $date=date('Y-m-d');
            foreach ($arr as $k=>$v)
            {
                $temp[$k]=[
                    'loc' => $v,
                    'lastmod'=>$date,
                    'changefreq' => 'daily',
                    'priority'=>0.8
                ];
                if($type != 'none'){
                    $temp[$k]['mobile:mobile']=$type;
                }
            }
        }else{
            $this->logger->error('网站链接数据异常，为空！');
        }
        return $temp;
    }
    protected function getType($type)
    {
        $arr=[
            'pc,mobile','mobile','htmladapt','none'
        ];
        return isset($arr[$type])?$arr[$type]:'none';
    }
    /**
     * @param $urls 数据
     * @param array $fileUrls 要过滤掉的地址
     * @return mixed
     */
    protected function filterUrl(array $urls,$fileUrls=[])
    {
        $temp=[];
        if($urls){
            foreach ($urls as $k=>$url)
            {
                if(strpos($url,$this->url) !== false){ //过滤非本站域名
                    if(!in_array($url,$fileUrls) && $this->filterPic($url)){ //过滤已经存在的
                        array_push($temp,$url);
                    }
                }
            }
        }
        return $temp;
    }
    // 过滤图片
    public function filterPic($url){
       $urlD= explode('/',$url);
       $ext=explode('.',$urlD[count($urlD)-1]);
       if(isset($ext[1]))
       {
           if(in_array($ext[1],['png','jpg','jpeg','gif','bmp','webp','pcx','svg','icon'])){
               return false;
           }else{
               return true;
           }
       }else{
           return true;
       }
    }
    protected function getFirst($url)
    {
        $this->logger->info('获取首页中');
        $ql=QueryList::getInstance()->get($url);
        return $ql->find('a')->attrs('href')->toArray();
    }
    protected function multiGetData(array $urls=[])
    {
        QueryList::getInstance()->multiGet($urls)->success(function (QueryList $ql, $response,$r) use ($urls){
            $res=$ql->find('a')->attrs('href')->toArray();
            $data=$this->filterUrl($res,$this->res);
            $this->logger->info($urls[$r]." success");
            if($data){
                $this->res=array_merge($this->res,$data);
                $this->multiGetData($data);
            }
        })->error(function ($error){
            $this->logger->error($error);
        })->send();
    }
}