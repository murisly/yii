<?php

// 出入金

namespace restful\controllers;

use common\helpers\Bankinfo;
use common\helpers\MT4;
use common\helpers\Publics;
use common\models\Balance;
use common\models\User;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class DepositController extends Base
{
    protected function exceptionalAuthenticationActions()
    {
        return ['callback', 'notice'];
    }

    public function actionWithdraw()
    {
        /** @var User $user */
        $user = \Yii::$app->user->getIdentity();
        
        $amount = \Yii::$app->request->post('amount');
        $amount = abs($amount);
        
        self::log($user->mid, 'withdraw amount: ' . $amount, __METHOD__);
        
        if(0 == $amount) {
            throw new BadRequestHttpException(\Yii::t('api', 'please fill in amount'));
        }
        
        // bankinfo
        $bankinfo = \common\helpers\Bankinfo::getBankInfo($user->mid);
        
        self::log($user->mid, 'bankinfo: ' . ($bankinfo ? json_encode($bankinfo) : 'null'), __METHOD__);
        
        if(!$bankinfo) {
            throw new Exception(\Yii::t('api', 'Please fill in the bank account information!'));
        }

        if(ArrayHelper::getValue($bankinfo, 'status', -1) == Bankinfo::STATUS_WAITING) {
            throw new Exception(\Yii::t('api', 'Bank account information is being audited, please wait patiently!'));
        }
        if(ArrayHelper::getValue($bankinfo, 'status', -1) == Bankinfo::STATUS_REJECTED) {
            throw new Exception(\Yii::t('api', 'Please fill in the bank account information!'));
        }
        
        try {
            MT4::deposit($user->mid, $amount * -1, 'withdraw', 'live');

            $order = new Balance();
            $order->mid = $user->mid;
            $order->order_id = self::generateOrderId();
            $order->transaction_type = Balance::TRANS_TYPE_OUT;
            $order->type = Balance::TYPE_WITHDRAW;
            $order->currency = 'USD';
            $order->amount = $amount;
            $order->created_at = time();
            $order->status = Balance::STATUS_WITHDRAW_WAITING;
            if($order->save(false)) {
                self::log($user->mid, 'withdraw order success: ' . json_encode($order->toArray()), __METHOD__);
            } else {
                self::log($user->mid, 'withdraw order error: ' . json_encode($order->getErrors()), __METHOD__);
            }
        } catch (Exception $e) {
            self::log($user->mid, 'MT4 error: ' . $e->getMessage(), __METHOD__);
            throw new Exception('withdraw fail');
        }
    }

    public function actionChannels()
    {
        return Publics::PaymentMethods();
    }
    
    public function actionCreateOrder()
    {
        /** @var User $user */
        $user = \Yii::$app->user->getIdentity();
        
        $amount = \Yii::$app->getRequest()->get('amount');
        $channel = \Yii::$app->getRequest()->get('channel');
        $channelcode = \Yii::$app->getRequest()->get('channelcode');
        
        self::log($user->mid, "deposit: [amount:{$amount}]-[channel:{$channel}]-[channelcode:{$channelcode}]", __METHOD__);
        
        if(!$amount || !$channel || !$channelcode) {
            throw new BadRequestHttpException('params error');
        }
        
        $amount = abs(intval($amount));
        
        $order = new Balance();
        $order->mid = $user->mid;
        $order->order_id = self::generateOrderId();
        $order->transaction_type = Balance::TRANS_TYPE_IN;
        $order->type = Balance::TYPE_DEPOSIT;
        $order->currency = 'USD';
        $order->amount = $amount;
        $order->channel = $channel;
        $order->channelcode = $channelcode;
        $order->created_at = time();
        $order->status = Balance::STATUS_DEPOSIT_WAITING;
        $order->save(false);
        
        $url = self::generatePayUrl($order);
        \Yii::$app->response->redirect($url);
    }
    
    public function actionCallback()
    {
        $params = [];
        foreach (['Status', 'MerchantCode', 'MerchantOrderNo', 'ReferenceId', 'Amount', 'Currency'] as $field) {
            $params[$field] = \Yii::$app->request->post($field);
        }

        self::log(0, 'GE callback: ' . json_encode($params), __METHOD__);

        $checkValue = self::hashParams($params);

        //正常情况
        $checkRecord = \Yii::$app->request->post('CheckCode');
        if(!$checkRecord) {
            //用户取消返回
            $checkRecord = \Yii::$app->request->post('CheckValue');
        }

        if($checkValue != strtoupper($checkRecord)) {
            self::log(0, 'wrong CheckCode!!!', __METHOD__);
            throw new BadRequestHttpException('check failed');
        }

        self::log(0, 'right CheckCode', __METHOD__);

        $order = Balance::findOne(['order_id' => $params['MerchantOrderNo']]);
        if(!$order) {
            self::log(0, 'order not found: ' . $params['MerchantOrderNo'], __METHOD__);
            throw new NotFoundHttpException();
        }
        
        self::log($order->mid, 'order found: ' . json_encode($order->toArray()), __METHOD__);
        
        if($order->status == Balance::STATUS_DEPOSIT_WAITING) {
            $status = strtolower($params['Status']);
            $order->trans_id = $params['ReferenceId'];

            switch ($status) {
                case 'success':
                    $order->status = Balance::STATUS_DEPOSIT_SUCCESS;
                    $order->finish_at = time();
                    if($order->save()) {
                        try {
                            MT4::deposit($order->mid, $order->amount, 'deposit', 'live');
                        } catch (Exception $e) {
                            self::log($order->mid, 'MT4 error: ' . $e->getMessage(), __METHOD__);
                        }
                    }
                    break;
                case 'pending':
                    $order->status = Balance::STATUS_DEPOSIT_WAITING;
                    break;
                case 'canceled':
                    $order->status = Balance::STATUS_DEPOSIT_CANCEL;
                    $order->finish_at = time();
                    $order->save();
                    break;
                default:
            }
        }

        $domain = \Yii::$app->params['domain.main'];
        \Yii::$app->response->redirect('http://' . $domain);
    }

    public function actionNotice()
    {
        return $this->actionCallback();
    }

    public function actionPayLog()
    {
        $pageNum = \Yii::$app->request->get('pageNum', 1);
        $pageSize = \Yii::$app->request->get('pageSzie', 10);
        
        return Balance::find()
            ->where(['mid' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->offset(($pageNum - 1) * $pageSize)
            ->limit($pageSize)
            ->all();
    }
    
    private static function generateOrderId()
    {
        $oldTz = date_default_timezone_get();
        if('UTC' != $oldTz) {
            date_default_timezone_set('UTC');
        }
        
        $epoch = strtotime('2015-08-20') * 100;
        $mtime = intval(microtime(true) * 100);

        // generate epoch point
//        var_dump(date('Y-m-d H:i:s', ($mtime - hexdec('100000000'))/100));

        $orderId = dechex($mtime - $epoch);
        
        if('UTC' != $oldTz) {
            date_default_timezone_set($oldTz);
        }
        
        return $orderId;
    }
    
    private static function generatePayUrl(Balance $order)
    {
        $domain = \Yii::$app->params['domain.api'];
        $env = \Yii::$app->params['env'];
        
        $domain = 'forex.bo.local';
        
        $params = [
            'MerchantCode' => 'forex',
            'MerchantOrderNo' => $order->order_id,
            'TimeStamp' => time(),
            'UserToken' => $order->mid,
            'Amount' => $order->amount
        ];

        $params['Currency'] = 'USD';
        $params['CheckValue'] = self::hashParams($params);

        $params['PaymentMethod'] = $order->channel;
        $params['PaymentMethodCode'] = $order->channelcode;
        $params['Custom'] = 'dirpay';

        $params['Language'] = 'en-US';

        $callback_url = "http://{$domain}/deposit/callback?code=forex";
        $return_url = "http://{$domain}/deposit/notice?code=forex&lang=" . \Yii::$app->language;

        $params['Email'] = 'ifkccp@163.com';//\Yii::$app->user->email;
        $params['NotifyURL'] = $callback_url;
        $params['ReturnURL'] = $return_url;
        
        $baseUrl = ('product' == $env) ? 'https://www.gainextreme.com/merchant/quick-recharge-request' : 'http://www.nuagetimes.info/merchant/quick-recharge-request';
        
        $url = sprintf('%s?%s', $baseUrl, http_build_query($params));
        
        return $url;
    }
    
    private static function hashParams($params)
    {
        if('product' == \Yii::$app->params['env']) {
            $GEKey = '49i49u-RYtfjLKFby22pg_Swo516jcHk';
        } else {
            $GEKey = '5capcI1J18i9z2JTnR6U3q-bpIQ8dt-E';
        }

        ksort($params);
        $check_code = http_build_query($params);
        $check_code = "$check_code&merchantKey=" . $GEKey;
        return strtoupper(hash("sha256", $check_code));
    }
    
    private function log($mid, $msg, $category = null)
    {
        $category = $category ?: __METHOD__;
        \Yii::info("[mid:{$mid}] " . $msg, $category);
    }
}
