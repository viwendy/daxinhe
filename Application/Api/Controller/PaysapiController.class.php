<?php
namespace Api\Controller;

use Common\Model\PayModel;
use Think\Controller;
use Common\Model\MemberModel;


/**
 * Paysapi个人免签
 */
class PaysapiController extends Controller{

    //const UID = "898f93eba4aa9bfedd798c60";//"此处填写PaysApi的uid";
    //const TOKEN = "1f29849e5a9c877e6dab0bb2f2766f91";//"此处填写PaysApi的Token";
    const POST_URL = "https://pay.bearsoftware.net.cn/";

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
            if (substr($recharge['order_no'], 0, 4) == 'VIPC') {
                $goodsname = '用户升级';
            }

            $order_no = $recharge['order_no'];
            $istype = I('get.payment_type')=='alipay' ? 1 :2;


            $return_url = $payType['store_frontend_redirect_url'];
            $notify_url = $payType['store_notify_url'];;

            $uid   = $payType['store_name'];    //"此处填写Yipay的uid";
            $token = $payType['store_key'];     //"此处填写Yipay的Token";

            $orderid  = $order_no;
            $orderuid = $recharge['member_id'];
            $price    = $recharge['price'];

            $key = md5($goodsname. $istype . $notify_url . $orderid . $orderuid . $price . $return_url . $token. $uid);

            $data = array(
                'goodsname'=>$goodsname,
                'istype'=>$istype,
                'key'=>$key,
                'notify_url'=>$notify_url,
                'orderid'=>$orderid,
                'orderuid'=>$orderuid,
                'price'=>$price,
                'return_url'=>$return_url,
                'uid'=>$uid
            );
            $this->assign('data',$data);
            $this->assign('post_url',self::POST_URL);
            $this->display();
        }



    }


    /**
     * return_url接收页面
     */
    public function return_url(){
        $this->assign('request', $_REQUEST);
        $this->display("/Alipay/return_url");
    }

    /**
     * notify_url接收页面
     */
    public function notify_url(){

        $paysapi_id = $_POST["paysapi_id"];
        $orderid = $_POST["orderid"];
        $price = $_POST["price"];
        $realprice = $_POST["realprice"];
        $orderuid = $_POST["orderuid"];
        $key = $_POST["key"];

        //校验传入的参数是否格式正确，略
        $token = self::TOKEN;
        $temps = md5($orderid . $orderuid . $paysapi_id . $price . $realprice . $token);

        if ($temps != $key){
            return jsonError("key值不匹配");
        }else{
            //校验key成功
            $out_trade_no = $orderid;
            $d = M('recharge')->where(array('order_no'=>$out_trade_no))->find();
            if (!$d || $d['is_pay']) {
                exit('success');
            }
            $trade_no = $paysapi_id;

            if (substr($d['order_no'], 0, 4) == 'VIPC') {
                //用户等级升级
                $pay_model = new PayModel();
                $pay_model->pay_vip_success($d['id'], self::PLATFORM, $trade_no);

            } else {

                //用户充值余额
                //添加金额变动记录
                $price = $d['price'];
                $member_id = $d['member_id'];
                $id = $d['id'];

                $model_member = new MemberModel();
                if ($price > 0) {
                    $mark = '会员充值, 支付宝_移动支付';
                    $res = $model_member->incPrice($member_id, $price, 97, $mark);

                } else {
                    $mark = '会员充值, 支付宝_移动支付';
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

        }
    }


}