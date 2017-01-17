<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/15
 * Time: 19:52
 */

namespace backend\controllers;

use common\models\Config;
use common\models\User;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Site controller
 */
class OfflineController extends Controller
{
    // 默认控制器
    public $defaultAction = 'home';
    
    public function actionIndex() {
        echo "off line!\n";
        echo Yii::$app->version;  // 命名空间的区别
    }
    
    public function actionHome() {
        echo 'home';
    }
    
    public function actionUser() {
        $model = new User();
        $user = $model->findIdentity(1);
        
        echo $user->email;
    }
    
    public function actionAdd() {
        $model = new User();
        $model->username = 'test';
        $model->auth_key = '123';
        $model->password_hash = 'hash';
        $model->email = 'test@test.com';
        $model->created_at = time();
        $model->updated_at = time();
        
        $model->save();
    }
    
    public function actionAddConfig() {
        $model = new Config();
        $model->name = 'mobile';
        $model->value = '18515478520';
        $model->created_at = time();
        $model->updated_at = time();
        
        $model->save();
    }
    
    public function actionGetConfig() {
        Yii::$app->getResponse()->format = Response::FORMAT_JSON;
        // $model->attributes = \Yii::$app->request->post('ContactForm');
        
        $model = Config::findOne('mobile');
        
        echo $model->name;
    }
    
    public function actionGo() {
        return $this->redirect('http://www.baidu.com');
    }
    
    public function actionKey() {
        $user = \Yii::$app->user;
        var_dump($user);
    }
    
    public function actionHash() {
        $user = new User();
        $user->generateAuthKey();
        
        echo $user->getAuthKey()."\n";

        $user->setPassword("a11111111");
        echo $user->getPassword();
    }
    
    public function actionPassword() {
        $user = new User();
        $user->setPassword();

        echo $user->password_hash;
    }
}