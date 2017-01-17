<?php
$params = array_merge(
    require(__DIR__ . '/../../common/config/params.php'),
    require(__DIR__ . '/../../common/config/params-local.php'),
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

return [
    'id' => 'app-backend', // 必须设定应用id，否则会抛出异常
    'basePath' => dirname(__DIR__), // 必须设定应用basePath，否则会抛出异常(相对于系统的路径)
    //'vendorPath' => dirname(__DIR__),  // 可以自定义vendor的path
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [],
    'on beforeRequest' => function (\yii\base\Event $event) {
        // request相应之前的回调事件
        // echo "Event beforeRequest \n";
    },
    'on afterRequest' => function (\yii\base\Event $event) {
        // request相应之后的回调事件
        // echo "\nEvent afterRequest \n";
    },
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-backend',
        ],
        'response' => [
            'on beforeSend' => function ($event) {
                // echo "beforeSend\n";
            },
            'on afterSend' => function ($event) {
                // echo "afterSend\n";
            },
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-backend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'advanced-backend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        
        'urlManager' => [
            'enablePrettyUrl' => true,  //url 美化
            'showScriptName' => false,  //是否在 URL 中显示脚本文件名称，当设置为 false 时需要服务器支持
            //'urlSuffix' => '.html',   //pathinfo 模式时的 url 后缀
            'rules' => [
            ],
        ],
    ],
    // 维护状态接管所有路由
//    'catchAll' =>  [
//        'offline',              //控制器名字，默认方法为index
//        'param1'=>'value1',	  //可选参数
//        'param2'=>'value2',     //可选参数
//    ],

    'params' => $params,
];
