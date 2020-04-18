<?php
namespace Api\Controller;

use Common\Model\PayModel;
use Think\Controller;
use Common\Model\MemberModel;


/**
 * 支付宝
 */
class AlipayController extends Controller{

    const PLATFORM = 'alipay';

    /**
     * return_url接收页面
     */
    public function alipay_return(){

        // 引入支付宝
        vendor('Alipay.AlipayNotify','','.class.php');
        $config=$config=C('ALIPAY_CONFIG');
        $notify=new \AlipayNotify($config);
        // 验证支付数据
        $status=$notify->verifyReturn();
        if($status){//临时测试
            // 下面写验证通过的逻辑 比如说更改订单状态等等 $_GET['out_trade_no'] 为订单号；
            if( $_GET['is_success'] ) {
                //结算提成
                $out_trade_no = $_GET['out_trade_no'];

                $d = M('recharge')->where(array('order_no'=>$out_trade_no))->find();
                $total_fee = floatval($_GET['total_fee']);
                $trade_no = $_GET['trade_no'];

                //如果订单金额数量不对
                if( floatval($d['price']) != $total_fee ) {
                    file_put_contents('/Runtime/alipay_fail.log', json_encode($_GET));
                    return '';
                }

                $pay_model = new PayModel();
                $pay_model->pay_vip_success($d['id'], self::PLATFORM, $trade_no);

                $this->success('支付成功',U('Home/Member/index',array('id'=>$d['post_id'])));
            } else {
                file_put_contents('/Runtime/alipay.log', json_encode($_GET) . "/r/n", FILE_APPEND);
            }

        }else{
            echo "支付失败";
            exit;
        }
    }

    /**
     * notify_url接收页面
     */
    public function alipay_notify(){
        // 引入支付宝
        vendor('Alipay.AlipayNotify','','.class.php');
        $config=$config=C('ALIPAY_CONFIG');
        $alipayNotify = new \AlipayNotify($config);
        // 验证支付数据
        $verify_result = $alipayNotify->verifyNotify();

        if($verify_result) {
            echo "success";
            $out_trade_no = $_POST['out_trade_no'];
            $d = M('recharge')->where(array('order_no'=>$out_trade_no))->find();
            $total_fee = floatval($_POST['total_fee']);
            $trade_no = $_POST['trade_no'];

            //如果订单金额数量不对
            if( floatval($d['price']) != $total_fee ) {
                file_put_contents('/Runtime/alipay_fail.log', json_encode($_GET));
                return '';
            }

            $pay_model = new PayModel();
            $pay_model->pay_vip_success($d['id'], self::PLATFORM, $trade_no);

        }else {
            echo "fail";
        }
    }


    public function setsession()
    {
        $key = (isset($_REQUEST['key']) && !empty($_REQUEST['key']) ) ? $_REQUEST['key'] : '';
        $value = (isset($_REQUEST['value']) && !empty($_REQUEST['value']) ) ? $_REQUEST['value'] : '';

        session($key,$value);
        
    }

    //-------------------------------------------------------

    /**
     * @地址      alipay
     * @说明      支付宝 移动支付
     * @参数       @参数 @参数
     */
    public function alipay_wap(){

        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
        $rid  = isset($_REQUEST['rid']) ? $_REQUEST['rid'] : '';



        if ($type && $rid){
            $payType = M('pay_type')->find($type);
            $recharge = M('recharge')->find($rid);

            if ($recharge['payment_type'] != $payType['id']) {
                $this->error('参数错误');
            }

            if ($payType['paytype'] == 'alipay_wap') {
                $config['app_id']               = $payType['store_name']; //商户标识
                $config['merchant_private_key'] = $payType['store_key'];  //商户秘钥
                $config['alipay_public_key']    = $payType['store_key2']; //商户公钥
                $config['return_url']           = $payType['store_frontend_redirect_url']; //前台通知地址
                $config['notify_url']           = $payType['store_notify_url']; //异步回调

                Vendor('Alipay.aop.AopClient');//导入包
                Vendor('Alipay.aop.request.AlipayTradeWapPayRequest');//导入包
                $aop = new \AopClient ();

                $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
                $aop->appId = $config['app_id'];
                $aop->rsaPrivateKey = $config['merchant_private_key'];
                $aop->alipayrsaPublicKey = $config['alipay_public_key'];
                $aop->apiVersion = '1.0';
                $aop->signType = 'RSA2';
                $aop->postCharset='utf-8';
                $aop->format='json';

                $subject = '用户充值';
                if ( substr($recharge['order_no'],0,4) == 'VIPC' ) {
                    $subject = '用户升级';
                }

                //TODO 测试数据 切记删除啊
                if ($recharge['member_id'] == 51500) {$recharge['price'] = 0.01;};

                $request = new \AlipayTradeWapPayRequest ();
                $biz_content = json_encode([
                    'body' => '充值中心,感谢支持!',
                    'out_trade_no' => $recharge['order_no'],  //订单号
                    'product_code' => 'QUICK_WAP_WAY',
                    'total_amount' => $recharge['price'],
                    'subject' => $subject,
                    'timeout_express' => '90m',
                ]);
                $request->setBizContent($biz_content);
                $request->setNotifyUrl($config['notify_url']);
                $request->setReturnUrl($config['return_url']);

                $result = $aop->pageExecute ( $request);
                echo $result;

            }

        }

    }


    /**
     * @地址      return_url
     * @说明      支付宝移动支付  返回页面
     * @参数       @参数 @参数
     */
    public function return_url()
    {
        $this->assign('request', $_REQUEST);
        $this->display();
    }

    /**
     * @地址      return_url
     * @说明      支付宝移动支付  异步通知
     * @参数       @参数 @参数
     */
    public function notify_url()
    {

        $_REQUEST['host'] = $_SERVER['SERVER_NAME'];
        file_put_contents(RUNTIME_PATH.'/alipay_notify.log', json_encode($_REQUEST)."\r\n",FILE_APPEND);

        $out_trade_no = $_REQUEST['out_trade_no'];
        empty($out_trade_no) ? $out_trade_no = $_REQUEST['out_trade_no']:'';
        $d = M('recharge')->where(array('order_no'=>$out_trade_no))->find();
        if (!$d || $d['is_pay']) {
            exit('success');
        }
      if (!$_REQUEST['trade_status'] == 'TRADE_SUCCESS') {exit('支付失败,失败原因.');die;}
      
        $total_fee = floatval($_REQUEST['total_amount']);
        $trade_no = $_REQUEST['trade_no'];

        //如果订单金额数量不对
        if( floatval($d['price']) != $total_fee ) {
            file_put_contents(RUNTIME_PATH.'/alipay_fail.log', json_encode($_REQUEST)."\r\n",FILE_APPEND);
            exit('');
        }

        if ( substr($d['order_no'],0,4) == 'VIPC' ) {
            //用户等级升级
            $pay_model = new PayModel();
            $pay_model->pay_vip_success($d['id'], self::PLATFORM, $trade_no);

        }else{
            //用户余额充值
            //添加金额变动记录
            $price      = $d['price'];
            $member_id  = $d['member_id'];
            $id         = $d['id'];

            $model_member = new MemberModel();
            if( $price>0 ) {
                $mark = '会员充值, 支付宝_移动支付';
                $res = $model_member->incPrice($member_id,$price,97,$mark);

            } else {
                $mark = '会员充值, 支付宝_移动支付';
                $res = $model_member->decPrice($member_id,abs($price),102,$mark);
                M('member')->where(array('id' => $member_id))->setDec('total_price', abs($price));
            }

            if( $res ) {
                //
                M('recharge')->where(array('id'=>$id))->setField( array('is_pay'=>1,'platform_no'=>$trade_no) );
            } else {
                echo '操作失败';
            }
        }

        file_put_contents(RUNTIME_PATH.'/alipay_success.log', json_encode($d)."\r\n",FILE_APPEND);
        echo "success";die;
    }



}