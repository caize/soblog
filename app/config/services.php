<?php

use Phalcon\Mvc\View;
use Phalcon\DI\FactoryDefault;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Url as UrlProvider;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Mvc\Model\Metadata\Memory as MetaData;
use Phalcon\Flash\Session as FlashSession;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Qiniu\Auth as Auth;
use Qiniu\Storage\UploadManager as UploadManager;

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault();

/**
 * We register the events manager
 */
$di->set('dispatcher', function() use ($di,$config) {

    $eventsManager = $di->getShared('eventsManager');

    //调试模式下不拦截， 展示错误。
    if(!$config->application->debug){
        /**
         * Handle exceptions and not-found exceptions using NotFoundPlugin
         */
        $eventsManager->attach('dispatch:beforeException', new NotFoundPlugin);
    }

    /**
     * Check if the user is allowed to access certain action using the SecurityPlugin
     */
    $eventsManager->attach('dispatch:beforeDispatch', new SecurityPlugin);


    $dispatcher = new Phalcon\Mvc\Dispatcher();
    $dispatcher->setDefaultNamespace('Souii\Controllers');
    //-----------------------WARNING！----------------------------
    //-------------------------注意！-----------------------------
    //高级调试模式时，会允许访问所有链接【如果不是非常明白，严禁打开此模式，严禁注释此代码！】
    if(!$config->application->SeniorDebug){
        $dispatcher->setEventsManager($eventsManager);
    }
    return $dispatcher;
});


/**
 * The URL component is used to generatef all kind of urls in the application
 */
$di->set('url', function () use ($config) {
    $url = new \Phalcon\Mvc\Url();
    $url->setBaseUri($config->application->baseUri);
    return $url;
}, true);


$di->set('view', function() use ($config) {

    $view = new View();

    $view->setViewsDir( $config->application->viewsDir);

    $view->registerEngines(array(
        ".volt" => 'volt'
    ));

    return $view;
});


require APP_PATH . 'app/config/Volt.php';
/**
 * 注解
 */
$di->setShared('annotations', function () {
    $reader = $annotations = new \Phalcon\Annotations\Adapter\Files(
    array(
        'annotationsDir' => APP_PATH.'cache/annotations/'
    ));
    return $reader;
});
/**
 * 注解路由
 */
$di->set('router', function() use ($config){
    $router = new \Phalcon\Mvc\Router\Annotations(true);
    $router->setDefaultNamespace('Souii\Controllers');
    $router->addResource('Souii\Controllers\Api');
    $router->addResource('Souii\Controllers\Index');
    $router->addResource('Souii\Controllers\Article');
    $router->addResource('Souii\Controllers\Sitemap');
//    $router->addResource('MFront');
    return $router;
});


/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->set('db',function () use ($config,$di) {

        $connection = new DbAdapter($config->database->toArray());

        $debug = $config->application->debug;
        if ($debug) {
            $eventsManager = $di->getShared('eventsManager');

            $logger = $di->getShared('logger');

            //Listen all the database events
            $eventsManager->attach(
                'db',
                function ($event, $connection) use ($logger) {
                    /** @var Phalcon\Events\Event $event */
                    if ($event->getType() == 'beforeQuery') {
                        /** @var DatabaseConnection $connection */
                        $variables = $connection->getSQLVariables();
                        if ($variables) {
                            $logger->log($connection->getSQLStatement() . ' [' . join(',', $variables) . ']', \Phalcon\Logger::INFO);
                        } else {
                            $logger->log($connection->getSQLStatement(), \Phalcon\Logger::INFO);
                        }
                    }
                }
            );

            //Assign the eventsManager to the db adapter instance
            $connection->setEventsManager($eventsManager);
        }
        return $connection;
    }
);




/**
 * write the logger
 */
$di->setShared('logger',function(){
    $data = date('Y-m-d');
    return   new Phalcon\Logger\Adapter\File(APP_PATH."app/logs/debug$data.log");
});



/**
 * Start the session the first time some component request the session service
 */
$di->set('session', function ()  use ($config) {
    $reids = $config->server['redis'];
    $array = array(
        'path' => "http://".$reids['ip'].":".$reids['port']."?auth=".$reids['auth']."&database=".$reids['dbindex'],
//        'name'=>'',
//        'lifetime'=>'',
//        'cookie_lifetime'=>'',
//        'cookie_secure'=>'',
//        'cookie_domain' =>'bst.com',
    );
    $session = new Souii\Redis\RedisSession($array);
    $session->start();
    return $session;
});


$di->setShared('redis',function()  use ($config){
    $reids = $config->server['redis'];
    $redis = new Redis();
    $redis->connect($reids['ip'],$reids['port']);
    $redis->auth($reids['auth']);
    $redis->select($reids['dbindex']);
    return $redis;
});

$di->setShared('redisUtils',function(){
    return new \Souii\Redis\RedisUtils();
});

/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di->set('modelsMetadata', function() {
    return new MetaData();
});


/**
 * Register the flash service with custom CSS classes
 */
$di->set('flash', function(){
    return new FlashSession(array(
        'error'   => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice'  => 'alert alert-info',
    ));
});

/**
 * Register a user component
 */
$di->set('elements', function(){
    return new Souii\Site\Elements();
});


$di->set('config', $config);



$di->setShared('sphinx',function() use ($config){
    /** @var \Souii\Sphinx\SphinxClient $sc */
    $sc = new \SphinxClient(); // 实例化Api
    $sc->SetServer($config->server->sphinx->ip, $config->server->sphinx->port); // 设置服务端，第一个参数sphinx服务器地址，第二个sphinx监听端口
    $sc->SetArrayResult(true);
    return $sc;
});

$di->set('weiboOauth',function() use ($config){
    $o = new Souii\Weibo\WeiBoOAuth($config->thirdpart->weibo->WB_AKEY , $config->thirdpart->weibo->WB_SKEY );
//    $code_url = $o->getAuthorizeURL( $config->thirdpart->weibo->WB_CALLBACK_URL );
    return $o;
});

$di->setShared('qiniuuploadMgr',function() use ($config){
    return new UploadManager();
});

$di->setShared('qiniuToken',function() use ($config){
    // 构建鉴权对象
    $auth = new Auth($config->thirdpart->qiniu->accessKey, $config->thirdpart->qiniu->secretKey);

    // 要上传的空间
    $bucket = $config->thirdpart->qiniu->bucket;

    // 生成上传 Token
    return  $auth->uploadToken($bucket);
});

$di->setShared('weixinMsg',function() use ($config){
   return new \Souii\WeiXinQiYe\WXBizMsgCrypt();
});

$di->set('crypt', function ()use ($config) {
    $crypt = new \Phalcon\Crypt();
    $crypt->setKey($config->application->cryptKey);
    return $crypt;
});

$di->set('markdown', function ()use ($config) {
    return new Parsedown();
});