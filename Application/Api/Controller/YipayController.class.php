<?php
namespace Api\Controller;

use Common\Model\PayModel;
use Think\Controller;
use Common\Model\MemberModel;


/**
 *  彩虹易支付
 */
class YipayController extends Controller{

    //const UID = "126729";//"此处填写Yipay的uid";
    //const TOKEN = "E5D6B9048E09AE1D6206753EC071AF4D";//"此处填写Yipay的Token";
    const POST_URL = "http://pay.hackwl.cn/";

    public function pay(){

        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
        $rid  = isset($_REQUEST['rid']) ? $_REQUEST['rid'] : '';

        if ($type && $rid) {
            $payType = M('pay_type')->find($type);
            $recharge = M('recharge')->find($rid);

            if ($recharge['payment_type'] != $payType['id']) {
                $this->error('参数错误');
            }


            $goodsname = '用户充值';
            if ( substr($recharge['order_no'],0,4) == 'VIPC' ) {
                $goodsname = '用户升级';
            }
            $orderid   = $recharge['order_no'];
            $price     = $recharge['price'];

            $return_url = $payType['store_frontend_redirect_url'];
            $notify_url = $payType['store_notify_url'];;

            $uid   = $payType['store_name'];    //"此处填写Yipay的uid";
            $token = $payType['store_key'];     //"此处填写Yipay的Token";

            $alipay_config = array();
            $alipay_config['partner']		= $uid;
            $alipay_config['key']			= $token;
            $alipay_config['sign_type']    = strtoupper('MD5');
            $alipay_config['input_charset']= strtolower('utf-8');
            $alipay_config['transport']    = 'http';
            $alipay_config['apiurl']    =  self::POST_URL;
            $alipay_config['notify_url']    =  $notify_url;
            $alipay_config['return_url']    =  $return_url;



            require_once(dirname(__FILE__)."/ep/epay_submit.class.php");
            $parameter = array
            (
                "pid" => $uid ,
                "type" => $type,
                "notify_url"	=> $notify_url,
                "return_url"	=> $return_url,
                "out_trade_no"	=> $orderid,
                "name"	=> $goodsname,
                "money"	=> $price,
                "sign_type"	=> "MD5",
                #"sitename"	=> $orderid
            );
            //建立请求
            $alipaySubmit = new \AlipaySubmit($alipay_config);
            $html_text = $alipaySubmit->buildRequestForm($parameter);

            echo $html_text;

            exit;
        }

    }


    /**
     * return_url接收页面
     */
    public function return_url()
    {
        $this->assign('request', $_REQUEST);
        $this->display("/Alipay/return_url");
    }

    /**
     * notify_url接收页面
     */
    public function notify_url()
    {

        file_put_contents(RUNTIME_PATH . '/yipay_notify.log', json_encode($_REQUEST) . "\r\n", FILE_APPEND);
        require_once(dirname(__FILE__) . "/ep/epay_notify.class.php");
        //计算得出通知验证结果
        $out_trade_no = $_REQUEST['out_trade_no'];
        $trade_no = $_REQUEST['trade_no'];
        $trade_status = $_REQUEST['trade_status'];
        $type = $_REQUEST['type'];
        if ($_REQUEST['trade_status'] == 'TRADE_SUCCESS') {
            $d = M('recharge')->where(array('order_no' => $out_trade_no))->find();
            if (!$d || $d['is_pay']) {
                exit('success');
            }
            if (substr($d['order_no'], 0, 4) == 'VIPC') {
                //用户等级升级
                $pay_model = new PayModel();
                $pay_model->pay_vip_success($d['id'], $type, $trade_no);

            } else {
                //用户充值余额
                //添加金额变动记录
                $price = $d['price'];
                $member_id = $d['member_id'];
                $id = $d['id'];

                $model_member = new MemberModel();
                if ($price > 0) {
                    $mark = '会员充值, 彩虹易支付';
                    $res = $model_member->incPrice($member_id, $price, 97, $mark);

                } else {
                    $mark = '会员充值, 彩虹易支付';
                    $res = $model_member->decPrice($member_id, abs($price), 102, $mark);
                    M('member')->where(array('id' => $member_id))->setDec('total_price', abs($price));
                }

                if ($res) {
                    //
                    M('recharge')->where(array('id' => $id))->setField(array('is_pay' => 1, 'platform_no' => $trade_no));
                } else {
                    echo '操作失败';
                }
            }
            echo 'success';exit;
        }

    }

}