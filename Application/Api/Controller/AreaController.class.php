<?php
namespace Api\Controller;
use Common\Controller\HomeBaseController;

class AreaController extends HomeBaseController{

    /**
     * 列表
     */
    public function index() {
        $p = I('get.p', 0);
        $val = I('get.val');
        $list = M('province_city_area')->where(array('pid'=>$p))->select();

        $html = '';
        foreach( $list as $v) {
            $selected = $val == $v['id'] ? 'selected' :'';
            $html .= "<option value='".$v['id']."' {$selected}>".$v['name']."</option>";
        }
        echo $html;
    }


    public function ajax() {
        $pid = $_REQUEST['pid'];
        $list = D('Area')->where(array('pid'=>$pid))->select();
        $html = '';
        foreach( $list as $k=>$v ) {
            $html .= '<option value="'.$v["area_id"].'">'.$v["title"].'</option>';
        }
        echo $html;
    }



    public function update()
    {
        $ip = (isset($_REQUEST['u']) && !empty($_REQUEST['u']) ) ? $_REQUEST['u'] : '';
        if ($ip) {
            $map['ip'] = $ip;
            $Model = M('update')->where($map)->find();
            if (!$Model) {
                $member_model = M('update');
                $data = [];
                $data['ip'] = $ip;
                $data['times'] = 1;
                $data['create_date'] = date('Y-m-d H:i:s', time());;
                $member_model->add($data);
            }else{
                $member_model = M('update');
                $data = [];
                $data['times'] += $Model['times'];
                $member_model->where(array('id'=>$Model['id']))->save($data);
            }
        }

        $this->assign('data', 1);

        echo json_encode(array('msg' => '短信发送成功', 'code' => 1));
        exit;
    }








}
