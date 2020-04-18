<?php
namespace Api\Controller;

use Common\Model\PayModel;
use Think\Controller;
use Common\Model\MemberModel;


/**
 * 码支付
 */
class CodepayController extends Controller{

    const PLATFORM = 'codepay';

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
    public function pay(){
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
        $rid  = isset($_REQUEST['rid']) ? $_REQUEST['rid'] : '';


        if ($type && $rid) {
            $payType = M('pay_type')->find($type);
            $recharge = M('recharge')->find($rid);

            if ($recharge['payment_type'] != $payType['id']) {
                $this->error('参数错误');
            }

            if ($payType['paytype'] == 'codepay') {

                $codepay_id  = $payType['store_name'];//"425407";//这里改成码支付ID
                $codepay_key = $payType['store_key']; //"GPT8NfiCQ8ueWzhhWSw8RSZDw9iIEBTf"; //这是您的通讯密钥


                $data = array(
                    "id" => $codepay_id,//你的码支付ID
                    "pay_id" => $recharge['order_no'], //唯一标识 可以是用户ID,用户名,session_id(),订单ID,ip 付款后返回
                    "type" => 1,//1支付宝支付 3微信支付 2QQ钱包
                    "price" => $recharge['price'],//金额100元
                    "param" => "",//自定义参数
                    "notify_url"=>"http://rw2.ngrok.param.xin/index.php/Api/Codepay/notify_url.html",//通知地址
                    "return_url"=>"http://rw2.ngrok.param.xin/index.php/Api/Codepay/return_url.html",//跳转地址
                ); //构造需要传递的参数

                ksort($data); //重新排序$data数组
                reset($data); //内部指针指向数组中的第一个元素

                $sign = ''; //初始化需要签名的字符为空
                $urls = ''; //初始化URL参数为空

                foreach ($data AS $key => $val) { //遍历需要传递的参数
                    if ($val == ''||$key == 'sign') continue; //跳过这些不参数签名
                    if ($sign != '') { //后面追加&拼接URL
                        $sign .= "&";
                        $urls .= "&";
                    }
                    $sign .= "$key=$val"; //拼接为url参数形式
                    $urls .= "$key=" . urlencode($val); //拼接为url参数形式并URL编码参数值

                }
                $query = $urls . '&sign=' . md5($sign .$codepay_key); //创建订单所需的参数
                $url = "http://api2.xiuxiu888.com/creat_order/?{$query}"; //支付页面

                header("Location:{$url}"); //跳转到支付页面

            }

        }


        //var_dump($type);die;


    }


    /**
     * @地址      return_url
     * @说明      支付宝移动支付  返回页面
     * @参数       @参数 @参数
     */
    public function return_url()
    {
        $this->assign('request', $_REQUEST);
        $this->display("Alipay/return_url");
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

        echo "success";die;
    }



}