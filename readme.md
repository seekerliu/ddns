#使用说明
##1. 注册DNSPod账户,获取TOKEN
参考: https://support.dnspod.cn/Kb/showarticle/tsid/227/
##2.配置.env文件
####复制.env-example为.env
####设置
DOMAIN=你的域名

SUB_DOMAIN=二级域名

DNSPOD_ID=你的DNSPOD ID

DNSPOD_TOKEN=你的DNSPOD TOKEN
##3.下载依赖
composer update

##4.设置计划任务
执行:crontab -e,添加如下一行(每分钟执行一次):

*/1 * * * * php ~/Code/dynamicDns/public/index.php

执行:sudo service cron restart