<?php

/**
 * author: seekerliu
 * createTime: 2016/10/20 下午3:44
 * Description:
 */
namespace App;
use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Dns
{
    private $ip;
    private $cache;
    private $options;
    private $baseUri;
    private $domain;
    private $subDomain;
    private $log;

    public function __construct()
    {
        //初始化缓存
        $this->cache = new FilesystemCache(__DIR__ . '/../cache/');

        //设置DNSPOD需要的User-Agent,login_token,format参数
        $this->options['headers']['User-Agent'] = getenv('DNSPOD_CLIENT');
        $this->options['form_params'] = [
            'login_token' => join(',', [getenv('DNSPOD_ID') , getenv('DNSPOD_TOKEN')]),
            'format' => 'json',
        ];

        $this->baseUri = getenv('DNSPOD_URI');  //DNSPOD api
        $this->domain = getenv('DOMAIN');   //域名
        $this->subDomain = getenv('SUB_DOMAIN');    //二级域名

        //初始化日志
        $this->log = new Logger('dynamicDns');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../log/dynamicDns.log', Logger::INFO));
    }

    /**
     * 执行
     */
    public function run()
    {
        //检查IP是否有变化, 如果有变化则更新DNS
        if($ip = $this->checkIpUpdate()) {
            $this->updateDnsRecord($ip);
        }
    }

    public function getLocalIP()
    {
        $ip = '127.0.0.1';
        $eth = getenv('ETH_NAME');

        $command = 'sudo ifconfig '. $eth. ' | grep netmask';
        $ipInfo = exec($command);
        $result = preg_match_all('/inet(.*?)netmask/', $ipInfo, $match);
        if($result)
            $ip = $match[1][0];

        return $ip;
    }

    public function getInternetIp()
    {
        $client = new Client(['base_uri' => getenv('GET_IP_URI')]);
        $response = $client->request('GET');
        $ip = json_decode($response->getBody())->ip;
        return $ip;
    }

    public function getCurrentIp()
    {
        if(getenv('GET_LOCAL_IP'))
            return $this->getLocalIp();
        else
            return $this->getInternetIp();
    }

    /**
     * 更新record记录
     * @param $ip
     */
    public function updateDnsRecord($ip)
    {
        $record = $this->getRecord($this->domain, $this->subDomain);

        $options = $this->options;
        $options['form_params']['domain'] = $this->domain;  //域名
        $options['form_params']['sub_domain'] = $this->subDomain;   //子域名
        $options['form_params']['record_id'] = $record->id; //记录id
        $options['form_params']['record_line_id'] = $record->line_id;   //记录线路id
        $options['form_params']['value'] = $ip; //IP

        //更新DNS
        $response = $this->http('POST', 'Record.Ddns', $options);

        //更新缓存
        if($response->status->code == 1) {
            $this->fetchAndCacheRecordList($this->domain);

            //记录日志
            $this->log->info('更新IP:', [$response->record->value]);
        }

    }

    /**
     * 根据域名,子域名获取子域名对应的record_id
     * @param $domain
     * @param $subDomain
     * @return mixed
     */
    public function getRecord($domain, $subDomain) {
        $recordList = $this->getRecordList($domain);

        foreach($recordList->records as $record) {
            if($record->name==$subDomain) {
                return $record;
            }
        }
    }

    /**
     * 获取域名的解析列表, 并返回名子域名的解析记录
     * @param $domain
     * @return false|mixed
     */
    public function getRecordList($domain)
    {
        if(!$this->cache->contains('recordList')) {
            $this->fetchAndCacheRecordList($domain);
        }

        return $this->cache->fetch('recordList');
    }


    /**
     * 远程获取域名的解析列表并更新缓存
     * @param $domain
     */
    public function fetchAndCacheRecordList($domain)
    {
        $options = $this->options;
        $options['form_params']['domain'] = $domain;

        $recordList = $this->http('POST', 'Record.List', $options);
        if(isset($recordList->records)) {
            $this->cache->save('recordList', $recordList, 3600);
        }
    }

    /**
     * 检查IP是否产生变化
     */
    public function checkIpUpdate()
    {
        $lastIp = $this->getRecord($this->domain, $this->subDomain)->value;
        $currentIp = $this->getCurrentIp();
        if(!empty($currentIp) && !empty($lastIp) && $lastIp != $currentIp) {
            return $currentIp;
        }

        return false;
    }

    /**
     * 封装HTTP请求, 返回json_decode之后的数组
     * @param string $method
     * @param $uri
     * @param $options
     * @return mixed
     */
    public function http($method = 'POST', $uri, $options)
    {
        $client = new Client(['base_uri' => $this->baseUri]);
        $recordList = $client->request($method, $uri, $options);
        return json_decode($recordList->getBody());
    }
}