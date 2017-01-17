<?php
/**
 * Created by PhpStorm.
 * User: welly
 * Date: 16-6-13
 * Time: 下午6:15
 */

namespace restful\controllers;

use yii\base\Event;
use yii\filters\auth\HttpBearerAuth;
use yii\helpers\ArrayHelper;
use yii\rest\Controller;
use yii\web\Response;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBasicAuth;
use yii\filters\auth\QueryParamAuth;


abstract class Base extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // 不处理OPTIONS请求
        $behaviors['authenticator']['except'] = ['options'];

        /*
         * 只返回JSON格式的数据。
         */
        $behaviors['contentNegotiator']['formats'] = [
            'application/json' => Response::FORMAT_JSON,
        ];

        /*
         * 使用OAuth2的身份认证方式。
         */
        if($this->isAuthenticatorEnabled()) {
            $behaviors['authenticator'] = [
                'class' => CompositeAuth::className(),
                'authMethods' => [
                    //HttpBasicAuth::className(),
                    HttpBearerAuth::className(),
                    QueryParamAuth::className(),
                ],
                'except' => $this->exceptionalAuthenticationActions(),
                'optional' => $this->optionalAuthenticationActions(),
            ];
        }
        return $behaviors;
    }

    public function init()
    {
        parent::init();
        \Yii::$app->response->on(Response::EVENT_BEFORE_SEND, [$this, 'handleResponse']);
    }

    public function handleResponse(Event $event) {
        /** @var Response $response */
        $response = $event->sender;

        // 錯誤處理
        $exception = \Yii::$app->errorHandler->exception;
        if($exception !== null) {

            // 异常响应日志
            $trace = $exception->getTrace()[0];
            $log_cat = $trace['class'] . '::' . $trace['function'];
            \Yii::error("响应异常中断：" . $exception->getMessage(), $log_cat);

            // 处理返回数据
            //$code = $exception->getCode();
            $response->data = [
                'result' => false,
                'eventCode'=>1000,
                'message' => $exception->getMessage(),
            ];
            return;
        }

        // 正常輸出
        //$response->data = [
            //'code' => 0,
            //'msg' => '',
            //'data' => $response->data,
            //$response->data
        //];

    }
    protected function isAuthenticatorEnabled() {
        return true;
    }

    /**
     * 不需要身份认证的Actions.
     *
     * @return array
     */
    protected function exceptionalAuthenticationActions() {
        return ['signup', 'signup-cn', 'signin', 'logout', 'send-register-s-m-s-code', 'exist-email',
            'exist-phone', 'reset-s-m-s-code', 'language', 'top-list', 'oauth','send-password-email', 'get-password-email',
            'get-info-by-token', 'bonus-activity'];
    }

    /**
     * 可选身份认证的Actions.
     * @return array
     */
    protected function optionalAuthenticationActions() {
        return [];
    }
}