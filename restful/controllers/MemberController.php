<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/7
 * Time: 9:52
 */

namespace restful\controllers;


use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBasicAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;
use yii\rest\ActiveController;
use yii\web\Response;

class MemberController extends ActiveController {

    public $modelClass = 'common\models\User';

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        /**
         * 只返回JSON格式的数据。
         */
        $behaviors['contentNegotiator']['formats'] = [
            'application/json' => Response::FORMAT_JSON,
        ];

        /**
         * http 基础验证
         */
//        $behaviors['authenticator'] = [
//            'class' => HttpBasicAuth::className(),
//        ];

        /**
         * 使用OAuth2的身份认证方式。
         */
        if ($this->isAuthenticatorEnabled()) {
            $behaviors['authenticator'] = [
                'class' => CompositeAuth::className(),
                'authMethods' => [
                    //HttpBasicAuth::className(),
                    HttpBearerAuth::className(), //通过http头来获取参数
                    QueryParamAuth::className(), //通过请求来获取到参数
                ],
                //'except' => $this->exceptionalAuthenticationActions(),
                //'optional' => $this->optionalAuthenticationActions(),
            ];
        }
        
        return $behaviors;
    }

    protected function isAuthenticatorEnabled()
    {
        return true;
    }


    public function actionTest(){

        return '3';

    }

} 