<?php
namespace Home\Controller;
use Common\Controller\HomeBaseController;
use Common\Model\LevelModel;
use Common\Model\MemberModel;
use Common\Model\PhonecodeModel;
use Common\Model\PostsApplyModel;
use Think\Model;

class MemberController extends HomeBaseController{

    protected $member_id;

    /**
     * 初始化方法
     */
    public function _initialize(){
        parent::_initialize();

        if( !$this->is_login() ) {
            $this->redirect('Public/login');
            //$this->error('请先登陆', U('Public/login'));
        }

        $this->member_id = $this->get_member_id();
    }

    /**
     * 个人中心首页
     */
    public function index()
    {
        //$str = '{"gmt_create":"2019-10-28 22:50:56","charset":"utf-8","seller_email":"pay@xiaoyuanwa.com","subject":"\u7528\u6237\u5145\u503c","sign":"RjVRd7PwNaBVHnJ5wKVLvzISD3QKQpxl3x61\/FCMIQYsrJD1M7mPN5qGgOysGE2MtEfqRhKj2hkI4NhRcCcxDVZ1lESMOgEtvWtDQveGJNhdoJ9ZgHtCy8y9rK8T1SWqSVYem4YHesoYY9krf+c+8Iy9hYzKg5mw3oE2tp+UbjQRwD2ScM4dqtjD5OOrJlUBBDmFKaG6D\/jX+TJEGEPwIoQLivqXrxyc8s0tNW7M2z5VGiUkqHpzkkekWX2AHCoNYEl6I8mmPDgCMjCgcwrt7WHwhogUL16Y8\/yZxFjd0zKzcDoa+3rI9bYLENWMFQnNXk30sfeqWDTPdoDX5B2Npg==","body":"\u5145\u503c\u4e2d\u5fc3,\u611f\u8c22\u652f\u6301!","buyer_id":"2088702899104965","invoice_amount":"0.01","notify_id":"2019102800222225057004960538619199","fund_bill_list":"[{\"amount\":\"0.01\",\"fundChannel\":\"ALIPAYACCOUNT\"}]","notify_type":"trade_status_sync","trade_status":"TRADE_SUCCESS","receipt_amount":"0.01","buyer_pay_amount":"0.01","app_id":"2018070460503367","sign_type":"RSA2","seller_id":"2088131807973300","gmt_payment":"2019-10-28 22:50:57","notify_time":"2019-10-28 22:50:58","version":"1.0","out_trade_no":"C51500A28742442738532","total_amount":"0.01","trade_no":"2019102822001404960549539560","auth_app_id":"2018070460503367","buyer_logon_id":"141***@qq.com","point_amount":"0.00","host":"renwu2.cn"}';


        //var_dump(json_decode($str));die;


        $data = M('member')->find($this->member_id);
        $this->assign('level_name', $this->get_level_name($data['level']));
        $this->assign('data', $data);
        //今日佣金
        $total_price['today'] = M('sale_list')->where("member_id={$this->member_id} and TO_DAYS(from_unixtime(create_time)) = TO_DAYS(now())")->sum('price');
        $total_price['month'] = M('sale_list')->where("member_id={$this->member_id} and DATE_FORMAT(from_unixtime(create_time),'%Y%m') = DATE_FORMAT(CURDATE(),'%Y%m')")->sum('price');
        $total_price['all'] = M('sale_list')->where("member_id={$this->member_id}")->sum('price');
        $this->assign('total_price', $total_price);

        //下级新增人数
        $total_team['today'] = M('member')->where(" (p1={$this->member_id} or p2={$this->member_id} or p3={$this->member_id}) and (TO_DAYS(from_unixtime(create_time)) = TO_DAYS(now())) ")->count();
        $total_team['month'] = M('member')->where(" (p1={$this->member_id} or p2={$this->member_id} or p3={$this->member_id}) and (DATE_FORMAT(from_unixtime(create_time),'%Y%m') = DATE_FORMAT(CURDATE(),'%Y%m'))")->count();
        $total_team['all'] = M('member')->where("p1={$this->member_id} or p2={$this->member_id} or p3={$this->member_id}")->count();
        $this->assign('total_team', $total_team);

        //新消息条数
        if( $data['role'] == 1 ) {
            $role_type = 2;
        } else {
            $role_type = 1;
        }
        $notice_view_time = intval($data['notice_view_time']);
        $where = "(( member_id = {$this->member_id} ) or ( (is_system = 1 and role_type = 0) or ( is_system = 1 and role_type = {$role_type} ) ) ) and create_time > {$notice_view_time} ";
        $notice_num = M('notice')->where($where)->count();
        $this->assign('notice_num', intval($notice_num));

        $point_level = M('point_level')->order('point desc')->find();
        $total_point = $point_level['point'];
        $step_point = ceil($total_point / 2);
        $setStep = $data['point'] <= $step_point ? 2 : 3;
//        var_dump($setStep);die;
        $this->assign('setStep', $setStep);

        $this->display();
    }

    /**
     * 系统消息
     */
    public function notice()
    {
        $type = I('get.type');
        $where = "( member_id = {$this->get_member_id()} and has_view=0 )";
        $noticeCount = M('notice')->where($where)->count('id');
        $type =='' ? $type= 3 :'';

        if ($type == 1) {
            $member = M('member')->field('notice_view_time,role')->find($this->member_id);
            if ($member['role'] == 1) {
                $role_type = 2;
            } else {
                $role_type = 1;
            }
            $member_id = $this->member_id;
            $where = "( member_id = {$member_id} ) or ( (is_system = 1 and role_type = 0) or ( is_system = 1 and role_type = {$role_type} ) )";

            $list = M('notice')->where($where)->order('id desc')->limit(100)->select();
            foreach ($list as &$_list) {
                if ($_list['is_system'] == 1) {
                    $view_style = $member['notice_view_time'] < $_list['create_time'] ? 0 : 1;
                } else {
                    $view_style = $_list['has_view'];
                }
                $_list['view_style'] = $view_style;

                if ($_list['msg'] == '恭喜您，您的VIP等级已升级为') {
                    $_list['view_style'] = $this->get_level_name($_list['view_style']);
                }
            }
            $this->assign('type', $type);
            $this->assign('list', $list);

            //将消息都设置为已读
            M('notice')->where(array('member_id' => $this->member_id))->save(array('has_view' => 1));
            session('weidu_xiaoxi',null);
            M('member')->where(array('id' => $this->member_id))->setField('notice_view_time', time());
        }else if ($type == 2) {
            $where = '1=1';
            $list = M('news')->where($where)->order('id desc')->limit(100)->select();
            foreach ($list as &$li) {
                $li['msg'] = $li['title'];
                $li['type'] = 'news';
            }
            $this->assign('type', $type);
            $this->assign('list', $list);
        }else{
            $this->assign('type', $type);
            $list = M('page')->field('id,title,recommend,create_time')->order('sort asc,id asc')->select();
            $this->assign('title', '消息中心');

            foreach ($list as &$item) {
                $item['msg'] = $item['title'];
                $item['recommend'] == 1 ? $item['msg'] = '【系统公告】'.$item['msg'] : '';
                $item['type'] = 'page';

            }

            $this->assign('list', $list);

        }
        $this->assign('noticeCount', $noticeCount);
        $this->display();
    }


    /**
     * 个人信息
     */
    public function info()
    {
        $this->display();
    }

    /**
     * 编辑个人信息
     */
    public function info_edit()
    {
        if( IS_POST ) {
            $field = I('field');
            $value = I('value');
            $data['id'] = $this->member_id;
            $data[$field] = $value;

            if( $field == 'idc' ) {
                if( !is_idcard($value) ) {
                    //$this->error('身份证号码不正确');
                }
            }

            //更新地址
            if( $field == 'address' ) {
                $city_ids = explode(',',I('post.city_ids'));
                $city_names = explode(' ',I('post.city_names'));
                if( count($city_ids) != 3 || count($city_names) != 3 ) {
                    $this->error('请选择地址信息');
                }
                $data['province_id'] = $city_ids[0];
                $data['city_id'] = $city_ids[1];
                $data['area_id'] = $city_ids[2];
                $data['province'] = $city_names[0];
                $data['city'] = $city_names[1];
                $data['area'] = $city_names[2];
            }

            //更新银行卡信息
            if( $field == 'bank_number' ) {
                $data['bank_name'] = I('post.bank_name');
                $data['subbranch_name'] = I('post.subbranch_name');
                $data['bank_user'] = I('post.bank_user');

                $zfb_ewm = I('post.zfb_ewm');
                $weixin_ewm = I('post.weixin_ewm');

                if ($zfb_ewm) {
                    $data['zfb_ewm'] = $zfb_ewm;
                }
                if ($weixin_ewm) {
                    $data['weixin_ewm'] = $weixin_ewm;
                }
            }

            $res = M('member')->save($data);
            if( $res ) {

                $member = M('member')->find($this->member_id);
                $member[$field] = $value;
                session('member', $member);

                if( I('f') == 'tixian' ) {
                    $this->success('更新成功', U('Member/tixian'));
                } else {
                    $this->success('更新成功', U('info'));
                }


            } else {
                $this->error('更新成功');
            }

        } else {
            $field_info = array(
                'nickname' => '更新昵称',
                'phone' => '更新手机号码',
                'idc' => '更新身份证信息',
                'bank_number' => '更新银行卡信息',
                'address' => '更新地址信息',
                'intro' => '个人说明',
                'sex' => '性别',
            );

            $field = I('get.field');
            $member = session('member');
            $value = $member[$field];

            $this->assign('field',$field);
            $this->assign('value',$value);
            $this->assign('info',$field_info[$field]);

            if( $field == 'address' ) {
                $city_ids = array($member['province_id'],$member['city_id'],$member['area_id']);
                if( !($member['province_id'] > 0 ) ) {
                    $city_ids[0] = M('area')->where(array('title'=>$member['province']))->getField('area_id');

                    if( !($member['city_id'] >0 ) ) {
                        $city_ids[1] = M('area')->where(array('title'=>$member['city']))->getField('area_id');
                    }
                }
                //$city_ids = implode(',', $city_ids);
                $this->assign('city_ids',$city_ids);

                /*$city_names = array($member['province'],$member['city'],$member['area']);
                $city_names = implode(',', $city_names);
                $this->assign('city_names',$city_names);*/

                $tpl = "info_edit_address";
            } elseif( $field == 'bank_number' ) {
                $banks = array(
                    "支付宝","中国农业银行","中国建设银行","中国工商银行","中国银行","交通银行","邮政储蓄银行","招商银行","兴业银行",
                    "中信银行","中国光大银行","上海浦东发展银行","中国民生银行","深圳发展银行","上海浦东发展银行","深圳发展银行","民生银行","广东发展银行","华夏银行"
                );
                $this->assign('banks',$banks);
                $tpl = 'info_edit_bank';
            } elseif( $field == 'sex' ) {
                $tpl = 'info_edit_sex';
            } else {
                $tpl = '';
            }

            $this->display($tpl);
        }
    }

    /**
     * 我的申请
     */
    public function apply()
    {
        $map = array();
        $map['member_id'] = $this->member_id;

        $model = M('task_apply');
        $count = $model->where($map)->count();
        $page = sp_get_page_m($count, 10);//分页
        $firstRow = $page->firstRow;
        $listRows = $page->listRows;

        $list = $model->alias('a')
            ->join(C('DB_PREFIX').'task as b on a.task_id = b.id','left')
            ->where(array('a.member_id'=>$this->member_id))
            ->field('a.*,b.title')
            ->order('a.id desc')->limit("$firstRow , $listRows")
            ->select();

        $this->assign("Page", $page->show());
        $this->assign("list", $list);
        $this->assign('count', $count);

        //已经申请的状态
        $apply_status = C('APPLY_STATUS');
        $this->assign ( 'apply_status', $apply_status );

        $this->display();
    }

    /**
     * 申请详情
     */
    public function apply_show()
    {
        $id = I('get.id');
        $apply_data = M('task_apply')->find($id);

        $task_id = $apply_data['task_id'];
        $apply_data['task_title'] = M('task')->where(array('id'=>$task_id))->getField('title');
        $apply_status = C('APPLY_STATUS');
        $apply_data['apply_status'] = $apply_status[$apply_data['status']];
        $this->assign("apply_data", $apply_data);

        $this->display();
    }

    /**
     * 账单
     */
    public function bill()
    {
        $weekarray=array("日","一","二","三","四","五","六");

        $map['member_id'] = $this->member_id;
        //$map['is_pay'] = 1;
        $list = $this->_list('member_price_log', $map, '', 'id desc', 500);
        $news_list = array();
        foreach( $list as $k=>$v ) {
            $day = date('Y-m', $v['create_time']);
            $news_list[$day][] = $v;
        }
        foreach( $news_list as &$_list ) {
            foreach( $_list as &$_list2 ) {
                $_list2['w'] = $weekarray[date('w', $_list2['create_time'])];
            }
        }
        $this->assign('news_list', $news_list);
        $this->display();
    }

    /**
     * 余额
     */
    public function balance()
    {
        $data = $this->get_member_data();
        $balance = $data['price'];
        $this->assign('balance', $balance);
        $this->display();
    }

    /**
     * 充值界面
     */
    public function recharge_do()
    {
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
        if( IS_POST ) {
            $price = I('price');
            if( !is_numeric($price) || !($price > 0) ) {
                $this->error('价格必须为数字，且大于0');
            }
            $price = $price; //单位为分
            $prices = 0.01 + lcg_value()*(abs(0.99 - 0.01));
            $prices = sprintf("%.2f", $prices);
            $data = array();
            $order_no = $this->create_order_no();
            $data['order_no'] = $order_no;
            $data['member_id'] = $this->member_id;
            //$data['msg'] = strip_tags(I('msg'));
            $data['price'] = $price + $prices;
            $data['create_time'] = time();
            $data['is_pay'] = 0;
            $data['payment_id'] = 1;
            $data['payment_type'] = $type;
            $insert_id = M('recharge')->add($data);
            if( $insert_id ) {
                //跳转到微信支付
                //$host = $_SERVER['HTTP_HOST'];
                //header("Location: http://".$host."/wxpay/payapi/jsapi.php?out_trade_no={$order_no}&openid=".session('member.openid'));
                $payType = M('pay_type')->find($type);
                $data2 = $payType;
                //支付宝 移动支付 直接调用支付宝
                if ($payType['paytype'] == 'alipay_wap') {

                    $host = $_SERVER['HTTP_HOST'];
                    $url = "http://".$host.U('Api/Alipay/alipay_wap',array('type'=>$payType['id'],'rid'=>$insert_id));
                    $data2['redirect_url'] = $url;
                }
                //码支付 暂时不予对接
                if ( $payType['paytype'] == 'codepay' ) {
                    $host = $_SERVER['HTTP_HOST'];
                    $url = "http://".$host.U('Api/Codepay/pay',array('type'=>$payType['id'],'rid'=>$insert_id));
                    $data2['redirect_url'] = $url;
                }
                //彩虹易支付
                if ( $payType['paytype'] == 'yipay' ) {
                    $host = $_SERVER['HTTP_HOST'];
                    $url = "http://".$host.U('Api/Yipay/pay',array('type'=>$payType['id'],'rid'=>$insert_id));
                    $data2['redirect_url'] = $url;
                }
                //paysapi 个人免签
                if ( $payType['paytype'] == 'paysapi' ) {
                    $host = $_SERVER['HTTP_HOST'];
                    $url = "http://".$host.U('Api/Paysapi/pay',array('type'=>$payType['id'],'rid'=>$insert_id));
                    $data2['redirect_url'] = $url;
                }

                //线下支付 指 返回二维码 用户付款之后 需要后台自己手动确认 无回调
                $data2['price'] = $data['price'];
                $data2['order_no'] = $data['order_no'];
                echo json_encode($data2);
                exit();
                //$this->redirect('Api/Weixinpay/pay',array('out_trade_no'=>$order_no));
            }
        } else {
            $map=array();
            $map['status'] = 1;

            $list = M('pay_type')->where($map)->order('order_number')->limit(10)->select();
            $data = M('member')->find($this->member_id);
            $this->assign('data', $data);
            $this->assign('list', $list);
            $this->display();
        }
    }



    //生成订单号
    private function create_order_no() {
        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $orderSn = $yCode[intval(date('Y')) - 2018] . $this->member_id . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        return $orderSn;
    }

    /**
     * 申请提现
     */
    public function tixian()
    {
        if( IS_POST ) {
            $member_data = M('member')->field('price,bank_name,bank_user,bank_number')->find($this->member_id);

            if( empty($member_data['bank_name']) || empty($member_data['bank_user']) || empty($member_data['bank_number']) ) {
                $this->error('请先完善您的银行卡信息');
            }

            $member_price = $member_data['price'];
            $price = floatval(I('post.price'));
            if( !($price >= 10) ) {
                $this->error('提现金额不能少于10');
            }
            if( !($member_price > 0) ) {
                $this->error('没有可提现的余额');
            }
            if( $price > $member_price ) {
                $this->error('余额不足');
            }
            if( $price%10 != 0 ) {
                $this->error('提现金额必须为10的倍数');
            }

            $data = array();
            $data['member_id'] = $this->member_id;
            $data['price'] = $price;
            $data['create_time'] = time();
            $data['status'] = 0;
            $data['charge'] = sp_cfg('charge');
            $data['actual_price'] = $price - $price*sp_cfg('charge')/100;
            $result = M('member_tixian')->add($data);
            if( $result ) {
                $model = new MemberModel();
                $res = $model->decPrice($this->member_id, $price, 100, '申请提现');
                if( $res ) {
                    $this->success('提交申请成功，等待管理员审核', U('index'));
                } else {
                    $this->error('提交失败');
                }
            } else {
                $this->error('提交失败');
            }
        } else {

            $data = M('member')->find($this->member_id);
            $data['bank_number_last'] = substr($data['bank_number'],-4);
            $this->assign('data', $data);
            $this->display();
        }
    }

    public function tixian_log()
    {
        $TIXIAN_STATUS = C('TIXIAN_STATUS');

        $status = I('get.status');
        $start_date = I('get.start_date');
        $end_date = I('get.end_date');

        $map = array();
        $map['member_id'] = $this->member_id;

        if( $status != '' ) $map['a.status'] = $status;

        //搜索时间
        if( !empty($start_date) && !empty($end_date) ) {
            $start_date = strtotime($start_date . "00:00:00");
            $end_date = strtotime($end_date . "23:59:59");
            $map['_string'] = "( a.create_time >= {$start_date} and a.create_time < {$end_date} )";
        }

        $model = M('member_tixian')->alias('a');
        $count = $model->where($map)->count();
        $page = sp_get_page($count, 20);//分页
        $firstRow = $page->firstRow;
        $listRows = $page->listRows;

        $list = M('member_tixian')->alias('a')->join(C('DB_PREFIX').'member as c on a.member_id = c.id','left')
            ->where($map)
            ->field('a.*,c.nickname,c.phone,c.bank_name,c.bank_user,c.bank_number')
            ->order('a.id desc')->limit("$firstRow , $listRows")
            ->select();
        foreach( $list as &$_list ) {
            $_list['status_text'] = $TIXIAN_STATUS[$_list['status']];
            /*$price = abs($_list['price']);
            $_list['price'] = $price;
            $_list['charge'] = sp_cfg('charge');
            $_list['actual_price'] = $price - $price * sp_cfg('charge')/100;*/
        }
        $this->assign('list',$list);
        $this->assign('tixian_status',$TIXIAN_STATUS);
        $this->assign('get',$_GET);
        $this->display();
    }

    /**
     * 生成二维码
     */
    public function qrcode(){
        $url  = U('Index/index',array('smid'=>$this->member_id),'',true);
        qrcode($url,6);
    }

    /**
     * 修改密码
     */
    public function password()
    {
        if( IS_POST ) {
            $d = I('post.');

            if( empty($d['old_password']) ) {
                $this->error('请输入当前密码。');
            }
            if( empty($d['password']) ) {
                $this->error('请输入当前密码。');
            }
            if( empty($d['repassword']) ) {
                $this->error('请再次输入新密码。');
            }
            if( $d['password'] != $d['repassword'] ) {
                $this->error('两次密码不一致。');
            }
            //短信验证码
            /*if( !PhonecodeModel::check_phone_code($code_type, $d['phone'], $code) ) {
                $this->error('短信验证码错误。');
            }*/

            $password = sp_encry($d['password']);

            $old_password = M('member')->where(array('id'=>$this->member_id))->getField('password');
            if( $old_password != sp_encry($d['old_password']) ) {
                $this->error('当前密码不正确。');
            }


            $res = M('member')->where(array('id'=>$this->member_id))->setField('password', $password);
            if( $res ) {
                $this->success('修改成功', U('index'));
            } else {
                $this->error('密码修改失败');
            }
        } else {
            $this->display();
        }
    }


    public function alipay_wap(){

        $orderid = '20191028121757410';
        $price = '0.01';
        $subject  = '商品名称'; //四个字以内
        //var_dump($_SERVER);die;

        $config = array (
            //应用ID,您的APPID。
            'app_id' => "2018070460503367",
            //商户私钥
            'merchant_private_key' => "MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCVOKDw+4KS4GrSCK3f0oA9RP9lURaIifeVMhhb8BN+qK4QaCY7FM2oWVWL2t2b+JgS3eHTIvRIzNYzGi4k1Y73AMu6Gvo7wPHTq0xbq+fvgeMo97UChbNtk/S/EVJgK5c3DGx3rwfSuQRvG51BOnA2K5d+aL2Qx5jl1lZZhy7RK4NDEESiFVQL2Y6zl5vWTIKTsSXxfda/mUVE5LjvWzRxpadppaqpkVnMx5FNvNVMt/xsO7ab5PCVpR0SuwE2uQR2bOkW2emIQsEhb96JCBOldzuH0EtS15T4N78u8Czy9T4mg5tmtnbAYGQzFqFyeXxRadkWtAMwNl+bMbbXbcxlAgMBAAECggEAauQv1bA07sW3f1EcTHLbzgf48zjM7W33XnaOIH2vWVG3rqUMjdHvKCMmNpLHoIzJUuqCc95cIzIoVl7wow4f5Sw6w8vDaL5j/H5+qkRQVq8ybAxVK8NerqYH8j6URbO0FIjfwjQtZHOIi5a6ZRlZfFRppvGcqXNxQWLyZBuEG+z1Qv4Bx8+cQt3HuQXKp8hydMXyeWrVHjBIjflQgrtYePoQWE4I9hyeIK3ttysImq2JELTi+WzrLn0W5HYFzHkVSTj0exmusPPs2Tuam2pt+/dwiPxNfh8zjDFgdE3fr36mHRjghdNqf2BNaOtzPnEZZAgKk+d8qQfiVfAtdVT8QQKBgQDow5z9srVhFWuih+7Gl4ckWz0TS01Q2ryzU4DYtEMWgXukHWDDXydBqEzsEpLf6mzMEouykGSG6l+F339uJHjbsamjM5bYddEhxiOfFTJzJyPJjCCkas2GnhaNZzuISI3r7UDCXqcHvKPmreB0+5Tpd3q2gZJtqJCRZxrpltfvLQKBgQCkHgpjcdkucTBSR8oC4PulikIa4DmVRuk/+I5iqwki4Hpn4iDibP09HkBZlIDDBVyV6DWYlb+fGfqJs2WSmYD8mNeogDYiFQlAcez2M4PpElu1mVR2i9W9tx8QcRZUY5D8HDDaziu48WumUEeGRCfVL+6kF+VCVb/D/EH4uhPVGQKBgAOk3FZUb+Z/MVowCprtUF5PV1tv+FvlsMKV8hRybgJyMH9XPmaQnMq4WcvwVoBO6TkgqTM4c3pxPOGZqCMPSx0VYPR/IENvRMDkmzYoXMvUtwi2uuQYD/OlkfDQxuvRRveElVj1pmPGnkJEQplSPviQuEkXKjWxR+Ie3Rr/E45ZAoGBAIQC1gBf136P9Xp53HisWD80EzBjJG5696xJVt7vDQ5M2qktL55yZNEAwGpOFbTJX0wF2Pa/nb9wuiKBdzaQ2zxUBUS4vNJ1cVexTBZOIdEcv0A38cTZfjh4UDh9fqSq4jioxHN8W5cMOrcw5BeQQyoswBymS/cr2nDfPIHBy6ohAoGASSDXw8DEz0UWzz55hSK29pT0Yh6Fdr2y4GywPyKYltgHJ2A2yzZQdRex2T51Xd2AzWYY1PyqiwcZ/JwDbRxLuSil/IJKH+Q6GVAbZoH9/exsw+MmY4vQ5Z6/5R4QAMBhNMz5cFWHKETi2idkVoI8rFp6YFrf9lgFikfAg8pKB0Y=",
            //异步通知地址
            //'notify_url' => "http://外网可访问网关地址/alipay.trade.page.pay-PHP-UTF-8/notify_url.php",
            'notify_url' => "http://rw2.ngrok.param.xin/index.php/Api/Alipay/notify_url.html",
            //同步跳转
            //'return_url' => "http://外网可访问网关地址/alipay.trade.page.pay-PHP-UTF-8/return_url.php",
            'return_url' => "http://rw2.ngrok.param.xin/index.php/Api/Alipay/return_url.html",
            //编码格式
            'charset' => "UTF-8",
            //签名方式
            'sign_type'=>"RSA2",
            //支付宝网关
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAw9NDpnhVc6FNesPVUjKHL920Zr9vEeS497gnMlm33EElLp9wpZnTmU7zBJdblnTj753jmDR+qipj0meQ3XkEx4ueiLrOKeAj+xQEFFFl/wG6ve8gxiaLzs+XP4T24wJGcTHi+lREBwZv/pGJmpeYru1sqNNHPBWmPVPyV4bL+QjJTP0FoXxbeTLNzMlErUiW3ubxeErCwQsj61Ril1flMjdtFMmiImMuRcW7ewIHHTJzdC0Ccr7p3D6savJvcWtCYTpx7obovOKU+QFWQkUClbZ9lDDmKsYhQ5/GMau7qBwBbqTRK2z+ovJt2VgJcRm6LKGqAeJUZITVEtD8o7LMWwIDAQAB",
        );

//3、pageExecute 测试
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

        $request = new \AlipayTradeWapPayRequest ();
        $request->setBizContent("{" .
            "    \"body\":\"对一笔交易的具体描述信息。如果是多种商品，请将商品描述字符串累加传给body。\"," .
            "    \"subject\":\"测试\"," .
            "    \"out_trade_no\":\"$orderid\"," .
            "    \"timeout_express\":\"90m\"," .
            "    \"total_amount\":0.01," .
            "    \"product_code\":\"QUICK_WAP_WAY\"" .
            "  }");
        $request->setNotifyUrl($config['notify_url']);
        $request->setReturnUrl($config['return_url']);

        $result = $aop->pageExecute ( $request);
        var_dump($result);die;
        echo $result;
    }

    /**
     * 升级VIP
     */
    public function vip()
    {
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
        if( IS_POST ) {

            $member_level = LevelModel::get_member_level();
            $level = intval(I('post.level'));
            $price = $member_level[$level]['price'];
            if( !($level > 0) ) {
                $this->error('请选择要升级的级别');
            }
            if( !($price > 0) ) {
                $this->error('价格参数错误');
            }

            //TODO 测试数据 切记删除啊
            if ($this->member_id == 51500) {$recharge['price'] = 0.01;};

            $payment_type = I('payment_type');
            $prices = 0.01 + lcg_value()*(abs(0.99 - 0.01));
            $prices = sprintf("%.2f", $prices);
            $data = array();
            $order_no = 'VIP'.$this->create_order_no();
            $data['order_no'] = $order_no;
            $data['member_id'] = $this->member_id;
            $data['price'] = $price + $prices;
            $data['create_time'] = time();
            $data['is_pay'] = 0;
            $data['level'] = $level;
            $data['payment_type'] = $payment_type;
            $data['payment_type'] = $type;
            $insert_id = M('recharge')->add($data);
            if( $insert_id ) {
                //在线支付 个人免签
                //$this->redirect('Api/Paysapi/pay',array('order_no'=>$order_no,'payment_type'=>$payment_type));
                #$this->redirect('Api/Yipay/pay',array('order_no'=>$order_no,'payment_type'=>$payment_type));
                //线下扫码转账
                //$this->success('提交成功，前往支付',U('pay',array('out_trade_no'=>$order_no)));

                $payType = M('pay_type')->find($type);
                $data2 = $payType;

                //支付宝 移动支付 直接调用支付宝
                if ($payType['paytype'] == 'alipay_wap') {

                    $host = $_SERVER['HTTP_HOST'];
                    $url = "http://".$host.U('Api/Alipay/alipay_wap',array('type'=>$payType['id'],'rid'=>$insert_id));
                    $data2['redirect_url'] = $url;
                }
                //码支付 暂时不予对接
                if ( $payType['paytype'] == 'codepay' ) {
                    $host = $_SERVER['HTTP_HOST'];
                    $url = "http://".$host.U('Api/Codepay/pay',array('type'=>$payType['id'],'rid'=>$insert_id));
                    $data2['redirect_url'] = $url;
                }
                //彩虹易支付
                if ( $payType['paytype'] == 'yipay' ) {
                    $host = $_SERVER['HTTP_HOST'];
                    $url = "http://".$host.U('Api/Yipay/pay',array('type'=>$payType['id'],'rid'=>$insert_id));
                    $data2['redirect_url'] = $url;
                }
                //paysapi 个人免签
                if ( $payType['paytype'] == 'paysapi' ) {
                    $host = $_SERVER['HTTP_HOST'];
                    $url = "http://".$host.U('Api/Paysapi/pay',array('type'=>$payType['id'],'rid'=>$insert_id));
                    $data2['redirect_url'] = $url;
                }



                $data2['price'] = $data['price'];
                $data2['order_no'] = $data['order_no'];

                echo json_encode($data2);
            }
        } else {
            $member = $this->get_member_data();
            $member['level_name'] = $this->get_level_name($member['level']);
            $member_level = LevelModel::get_member_level();
            unset($member_level[0]);//不分钻石会员
            $this->assign('member_level',$member_level);
            $this->assign('member',$member);

            $map['status'] = 1;
            $list = M('pay_type')->where($map)->order('order_number')->limit(10)->select();

            $this->assign('list', $list);

            $this->display();
        }
    }

    public function pay()
    {
        $out_trade_no = I('get.out_trade_no');
        $data = M('recharge')->where(array('order_no'=>$out_trade_no))->find();
        if( empty($data) ) {
            $this->error('单号不存在');
        }
        $this->assign('data',$data);
        $this->assign('out_trade_no',$out_trade_no);
        $this->display();
    }

    public function pay_screenshot()
    {
        if( IS_POST ) {
            $order_no = I('post.order_no');
            if( empty($order_no) ) {
                $this->error('请选择要提交的订单');
            }

            if( $_FILES ) {
                $file = ajax_upload('/Uploads/pay_image/',1,$this->member_id);
            } else {
                $this->error('请上传任务截图');
            }

            $data['file'] = $file;
            $data['order_no'] = $order_no;
            $data['member_id'] = $this->member_id;
            $data['create_time'] = time();
            $data['status'] = 0;//未处理
            $result = M('recharge_screenshot')->add($data);
            if($result) {
                $this->success('提交成功，等待管理员审核',U('index'));
            } else {
                $this->error('提交失败');
            }
        } else {
            //待支付记录
            $pay_list = M('recharge')->where(array('member_id'=>$this->member_id,'is_pay'=>0))->group('price')->select();
            $this->assign('pay_list',$pay_list);
            $this->display();
        }
    }

    /**
     * 我的团队
     */
    public function team()
    {
        $map = array();
        $rank = intval(I('get.rank',1));

        if( $rank == 2 ) {
            $map['p2'] = $this->member_id;
        } elseif( $rank == 3 ) {
            $map['p3'] = $this->member_id;
        } else {
            $map['p1'] = $this->member_id;
        }

        $model = M('member');
        $count = $model->where($map)->count();
        $page = sp_get_page_m($count, 20);//分页
        $firstRow = $page->firstRow;
        $listRows = $page->listRows;
        $list = $model->field('id,username,head_img,create_time,level')
            ->where($map)
            ->order('level desc,id desc')->limit("$firstRow , $listRows")
            ->select();
        $member_level = LevelModel::get_member_level();
        foreach($list as &$_list) {
            $where = array();
            $where['p1'] = $_list['id'];
            $_list['number'] = M('member')->where($where)->count();
            $_list['level_name'] = $member_level[$_list['level']]['name'];
        }
        $this->assign("Page", $page->show());
        $this->assign("list", $list);
        $this->assign('count', intval($count));
        $this->assign("rank", $rank);

        $this->assign('count_1', M('member')->where(array('p1'=>$this->member_id))->count());
        $this->assign('count_2', M('member')->where(array('p2'=>$this->member_id))->count());
        $this->assign('count_3', M('member')->where(array('p3'=>$this->member_id))->count());

        $this->display();
    }

    //销售提成记录
    public function sale()
    {
        $day = date('Y-m-d');
        $start_date = I('get.start_date');
        $end_date = I('get.end_date');
        $type = I('get.type');
        $type =='' ? $type= 1 :'';

        //$type = 1;
        $member_id = $this->member_id;

        $where = '';
        //搜索时间
        if( !empty($start_date) && !empty($end_date) ) {
            $_start_date = strtotime($start_date . "00:00:00");
            $_end_date = strtotime($end_date . "23:59:59");
            $where = " and create_time >= {$_start_date} and create_time < {$_end_date}";
        }
        $where .= " and type=$type";


        //日期内的收入
        $sql = "select SUM(price) as total from `dt_sale_list` where `member_id` = {$member_id} {$where}";
        $dao = new Model();
        $sum_data = $dao->query($sql);
        $today_total_price = $sum_data[0]['total'];
        $this->assign("today_total_price", floatval($today_total_price));


        $map = array();
        //$map['a.type'] = $type;
        $map['a.member_id'] = $member_id;
        if( $start_date == $end_date ) {
            $this->assign("show_day", $start_date);
        } else {
            $this->assign("show_day", "{$start_date} ~ {$end_date}");
        }
        $this->assign("start_date", $start_date);
        $this->assign("end_date", $end_date);
        $this->assign("type", $type);

        //搜索时间
        if( !empty($start_date) && !empty($end_date) ) {
            $start_date = strtotime($start_date . "00:00:00");
            $end_date = strtotime($end_date . "23:59:59");
            $map['_string'] = "( a.create_time >= {$start_date} and a.create_time < {$end_date} )";
        }

        $map['a.type'] = $type;

        $model = M('sale_list');
        $count = $model->alias('a')->where($map)->count();
        $page = sp_get_page_m($count, 50);//分页
        $firstRow = $page->firstRow;
        $listRows = $page->listRows;
        $list = $model->alias('a')
            ->join(C('DB_PREFIX').'member as b on a.from_member_id = b.id','left')
            ->where($map)
            ->field('a.*,b.username,b.phone')
            ->order('a.id desc')->limit("$firstRow , $listRows")
            ->select();
        $this->assign("Page", $page->show());
        $this->assign("list", $list);
        $this->assign('count', intval($count));
        $this->assign("type", $type);
        $this->assign("day", $day);
        //充值记录
        if ($type ==3 ){
            $map1 =array();
            $map1['member_id'] = $this->get_member_id();
            $map1['is_pay'] = 1;
            $list = m('recharge')->where($map1)->order('id desc')->select();
            foreach ($list as &$item) {
                $item['remark'] = $item['level'] > 0 ? '用户会员升级充值' : '用户余额充值';
            }
            $this->assign("list", $list);
        }

        $this->display();
    }

    //用户信息
    private function get_member_data()
    {
        $data = M('member')->find($this->member_id);
        return $data;
    }

    /**
     * 会员等级名称
     * @param $level
     */
    private function get_level_name($level) {
        $member_level = LevelModel::get_member_level();
        return $member_level[$level]['name'];
    }


    /**
     * @地址      paihang
     * @说明      排行榜
     * @参数       @参数 @参数
     */
    public function paihang()
    {
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 3;

        if ($type==1) {
            $start=time()-7*24*60*60;
            $end=time();
            $data['last_login_time'] = array('between',array($start,$end));
        }elseif($type == 2) {
            $start=time()-30*24*60*60;
            $end=time();
            $data['last_login_time'] = array('between',array($start,$end));
        }else{
            $data = "1=1";
        }

        $user = M("Member")->field("id,nickname,username,head_img,total_price")->where($data)->order('total_price desc')->limit(10)->select();
        //var_dump($user);die;

        foreach ($user as &$item) {
            if ( strlen($item['nickname']) > 6 ){
                $item['nickname'] = substr($item['nickname'],0,3).'***'.substr($item['nickname'],-3,-1);
            }
        }

        $this->assign('list',$user);
        $this->assign('type',$type);
        $this->display();
        
    }


    public function getDanmu()
    {
        $vip_where['r.level'] = array('neq',0);
        $vip_where['r.is_pay'] = 1;
        $vip = M('recharge r')
            ->join(C('DB_PREFIX').'member as m ON r.member_id = m.id')
            ->where($vip_where)
            ->field('m.id,m.nickname,m.username,r.level')
            ->order('r.create_time desc')
            ->limit(3)
            ->select();
        foreach($vip as $key=>$val){
            $vip[$key]['level'] = $this->get_level_name($val['level']);
            $vip[$key]['href'] = U('Member/vip');
            $vip[$key]['info']= '恭喜<font color="#ff9917">'.$val['nickname'].'</font>成功抢购并开通<font color="#ff0000">'.$vip[$key]['level'].'</font>';
        }
        echo json_encode($vip);
    }
    
    
    
    
    
    
    
}

