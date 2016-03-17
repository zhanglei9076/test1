<?php
/*  
 * 1、构造函数里定义的INDEX_PAGE是当前项目首页，如有更改，请在构造函数更改
 * 2、如果用户没有登入就进入会员中心，会默认跳转到登入页面，登入后再跳回来
 * code by lynn
 * */
class User_center extends MY_Controller{
    private $userinfo=array();
    function __construct(){
        parent::__construct();
        define("INDEX_PAGE",fuel_url('bms/user_center/uc_index'));
    }
    /**
     *  会员中心前三个页面共用部分，被uc_index、user_info、account_detail调用
     */
    function index_common(){
        //根据用户ID查询用户邮箱
        $username=$this->username;
        $uid=$this->uid;
        $wenjuan=$this->load->database('wenjuan',true);
        $w_m_u_re=$wenjuan->select('olduid,username,coin91,cash,email,emailstatus,modifyflag')->from('member_user')->where('olduid',$uid)->get();
        $w_m_u_arr=$w_m_u_re->row_array();
        if(!isset($w_m_u_arr['coin91'])){
            $w_m_u_arr['coin91']=0.00;
        }
        if(!isset($w_m_u_arr['cash'])){
            $w_m_u_arr['cash']=0.00;
        }
        if(!isset($w_m_u_arr['username'])){
            $w_m_u_arr['username']='';
        }
        if(!isset($w_m_u_arr['modifyflag'])){
            $w_m_u_arr['modifyflag']=2;
        }
        //第三方注册用户改名入口 ,post方式
        $w_m_u_arr['error_info']='';
        if($this->uri->segment(5) && $this->uri->segment(6)){
            if(($this->uri->segment(5) == 'ajax') && ($this->uri->segment(6) == 'new_username')){
                $new_username=$this->input->post('new_username');
                $this->load->library('form_validation');
                $this->form_validation->set_rules('new_username','用户名','trim|required|max_length[20]');
                $re=$this->form_validation->run();
                if($re){
                    //检测用户名是否存在
                    $m_u_re=$wenjuan->select('olduid')->where('username',$new_username)->get('member_user');
                    $m_u_arr=$m_u_re->row_array();
                    if(!empty($m_u_arr)){
                        echo '用户名已经存在';
                        exit;
                    }else{
                        //字段验证成功，执行修改用户名操作
                        $uid=$w_m_u_arr['olduid'];
                        $valid_user=$this->valid_user;
                        $fuel_id=$valid_user['id'];
                        //更新wenjuan数据库数据
                        $arr_update_re['wenjuan__member_user']=$wenjuan->update('member_user',array('username'=>$new_username,'modifyflag'=>2),array('olduid'=>$uid));
                        $arr_update_re['wenjuan__coin_record']=$wenjuan->update('coin_record',array('username'=>$new_username),array('userid'=>$uid));
                        $arr_update_re['wenjuan__fuel_api_user']=$wenjuan->update('fuel_api_user',array('username'=>$new_username),array('uid'=>$uid));
                        $arr_update_re['wenjuan__fuel_users']=$wenjuan->update('fuel_users',array('user_name'=>$new_username),array('id'=>$fuel_id));
                        //更新club数据库数据
                        $club=$this->load->database('club',true);
                        $arr_update_re['club__pre_ucenter_members']=$club->update('pre_ucenter_members',array('username'=>$new_username),array('uid'=>$uid));
                        $arr_update_re['club__pre_common_member']=$club->update('pre_common_member',array('username'=>$new_username),array('uid'=>$uid));
                        //更新uhome数据库数据
                        $uhome=$this->load->database('uhome',true);
                        $arr_update_re['uhome__uchome_member']=$uhome->update('uchome_member',array('username'=>$new_username),array('uid'=>$uid));
                        //判断是否所有表都新增数据成功，如果有一个不成功，则需要用户重新修改一次
                        foreach($arr_update_re as $k=>$v){
                            if($v != true){
                                $is_del["$k"]='defeat';
                            }else{
                                $is_del["$k"]='success';
                            }
                        }
                        if(in_array('defeat',$is_del)){
                            //有更新失败的
                            if($arr_update_re['wenjuan__member_user']){
                                //主表改名成功，其他表再同步一下
                                $wenjuan=$this->load->database('wenjuan',true);
                                $arr_update_re['wenjuan__coin_record']=$wenjuan->update('coin_record',array('username'=>$new_username),array('userid'=>$uid));
                                $arr_update_re['wenjuan__fuel_api_user']=$wenjuan->update('fuel_api_user',array('username'=>$new_username),array('uid'=>$uid));
                                $arr_update_re['wenjuan__fuel_users']=$wenjuan->update('fuel_users',array('user_name'=>$new_username),array('id'=>$fuel_id));
                                //更新club数据库数据
                                $club=$this->load->database('club',true);
                                $arr_update_re['club__pre_ucenter_members']=$club->update('pre_ucenter_members',array('username'=>$new_username),array('uid'=>$uid));
                                $arr_update_re['club__pre_common_member']=$club->update('pre_common_member',array('username'=>$new_username),array('uid'=>$uid));
                                //更新uhome数据库数据
                                $uhome=$this->load->database('uhome',true);
                                $arr_update_re['uhome__uchome_member']=$uhome->update('uchome_member',array('username'=>$new_username),array('uid'=>$uid));
                            }else{
                                //主表改名也失败
                                $params['error_str']='服务器正忙，请过会再试！';
                                $params['referer']=current_url();
                                $params['right_url']=current_url();
                                $params['left_str']='返回首页';
                                $params['left_url']=INDEX_PAGE;
                                $params['right_str']='返回上一步';
                                $this->errors($params);
                            }
                        }else{
                            //$w_m_u_arr['error_info']='用户名修改成功';
                            $url=fuel_url('bms/user_center/to_login?referer='.base64_encode(str_replace('/ajax/new_username','',current_url())));
                            echo "用户名修改成功，<span id='sec'>5</span> 秒后请重新登入！<script type='text/javascript'>"
                                ."function countSec(){
                                	var second=document.getElementById('sec');
                                	var num=parseInt(second.innerText);
                                	num=num-1;
                                	if(num > 0){
                                	    second.innerText=num;
                                	}else{
                                	    window.location.href='{$url}';
                                	}
                                }"
                                ."window.setInterval(countSec,1000);"
                                ."</script>";
                        }
                        exit;
                    }
                }else{
                    //字段验证出错，报出错误原因
                    echo str_replace(' ','',$this->form_validation->error('username'));
                    exit;
                }
            }
            exit;
        }
        //判断是否注册邮箱
        if(empty($w_m_u_arr['email'])){
            $w_m_u_arr['email']='尚未绑定邮箱';
            //如果未绑定邮箱，“点击激活”改成“绑定邮箱”
            $w_m_u_arr['email_str']='点击绑定';
            $w_m_u_arr['email_url']=fuel_url('bms/user_center/bind_email/nobind');
        }else if(!empty($w_m_u_arr['email']) && ($w_m_u_arr['emailstatus'] == 1)){
            //设置激活邮箱链接
            $w_m_u_arr['email_str']='点击激活';
            $w_m_u_arr['email_url']=fuel_url('bms/user_center/activate_email/notact');
        }else if(!empty($w_m_u_arr['email']) && ($w_m_u_arr['emailstatus'] == 2)){
            $w_m_u_arr['email_str']='更改邮箱';
            $w_m_u_arr['email_url']=fuel_url('bms/user_center/activate_email/hasact');
        }
        //传值
        $this->userinfo=$w_m_u_arr;
    }
    /**
     * 会员中心首页
     */
    function uc_index(){
        $this->index_common();
        $this->load->view('mobile/uc_index',array('userinfo'=>$this->userinfo));
    }
    /**
     * 个人信息页面
     */
    function person_info(){
        $this->index_common();
        $this->load->view('mobile/person_info',array('userinfo'=>$this->userinfo));
    }
    /**
     * 账户明细页面
     */
    function account_detail(){
        $this->index_common();
        $this->load->view('mobile/account_detail',array('userinfo'=>$this->userinfo));
    }
    /**
     * 用户名修改成功后，跳转的中转页面，此页面实现UCenter同步退出功能
     */
    function to_login(){
        if(empty($_GET['referer'])){
            if(empty($_GET['redct'])){
                $url=fuel_url('bms/mobile/login');
            }else{
                $url=fuel_url('bms/mobile/login?redct='.$_GET['redct']);
            }
        }else{
            if(empty($_GET['redct'])){
                $url=fuel_url('bms/mobile/login?referer='.$_GET['referer']);
            }else{
                $url=fuel_url('bms/mobile/login?referer='.$_GET['referer'].'&redct='.$_GET['redct']);
            }
        }
        //调用Ucenter接口
        include APPPATH.'config/ucenter.php';
        include APPPATH.'../uc_client/client.php';
        //销毁session中的用户名和uid，实现SESSION推出登入
        unset($_SESSION['username']);
        unset($_SESSION['uid']);
        session_destroy();
        echo uc_user_synlogout()."<script type='text/javascript'>
        setTimeout(window.location.href='{$url}',1000);</script>";
    }
    /**
     * 绑定邮箱页面，第三方注册不激活，先绑定
     */
    function bind_email(){
        //接收pathinfo值，rewrite为修改邮箱，nobind为绑定邮箱
        if($this->uri->segment(5)){
            //路径格式合法
            if(($this->uri->segment(5) == 'rewrite') || ($this->uri->segment(5) == 'nobind')){
                if($this->uri->segment(5) == 'rewrite'){
                    //rewrite入口
                    $params['title']='修改邮箱';
                    $params['referer']=fuel_url('bms/user_center/uc_index');
                }
                if($this->uri->segment(5) == 'nobind'){
                    //nobind入口
                    $params['title']='绑定邮箱';
                    $params['referer']=fuel_url('bms/user_center/uc_index');;
                }
                $params['error']='';
                //字段验证
                $this->load->library('form_validation');
                $this->form_validation->set_rules('email','邮箱','trim|required|valid_email|max_length[100]');
                $re=$this->form_validation->run();
                if($re){
                    //初步验证成功，验证输入的邮箱是否存在
                    $email=$this->input->post('email');
                    $uid=$this->uid;
                    $wenjuan=$this->load->database('wenjuan',true);
                    $w_m_u_re=$wenjuan->select('olduid')->from('member_user')->where('email',$email)->get();
                    $w_m_u_arr=$w_m_u_re->row_array();
                    $w_f_u_re=$wenjuan->select('id')->from('fuel_users')->where('email',$email)->get();
                    $w_f_u_arr=$w_f_u_re->row_array();
                    if(empty($w_m_u_arr) && empty($w_f_u_arr)){
                        //提交的邮箱未被注册，执行修改或者绑定邮箱操作
                        $username=$this->username;
                        $valid_user=$this->valid_user;
                        $fuel_id=$valid_user['id'];
                        //更新wenjuan数据库数据
                        $arr_update_re['wenjuan__member_user']=$wenjuan->update('member_user',array('email'=>$email,'emailstatus'=>1),array('olduid'=>$uid));
                        $arr_update_re['wenjuan__fuel_users']=$wenjuan->update('fuel_users',array('email'=>$email),array('id'=>$fuel_id));
                        //更新club数据库数据
                        $club=$this->load->database('club',true);
                        $arr_update_re['club__pre_ucenter_members']=$club->update('pre_ucenter_members',array('email'=>$email),array('uid'=>$uid));
                        $arr_update_re['club__pre_common_member']=$club->update('pre_common_member',array('email'=>$email,'emailstatus'=>1),array('uid'=>$uid));
                        //判断是否所有表都新增数据成功，如果有一个不成功，则需要用户重新修改一次邮箱
                        foreach($arr_update_re as $k=>$v){
                            if($v != true){
                                $is_del["$k"]='defeat';
                            }else{
                                $is_del["$k"]='success';
                            }
                        }
                        if(in_array('defeat',$is_del)){
                            //修改有失败，让用户稍候重新修改
                            $params['error_str']='系统正忙，请稍候再操作';
                            $params['referer']=fuel_url('bms/user_center/uc_index');
                            $params['right_url']=current_url();
                            $params['left_str']='返回首页';
                            $params['left_url']=INDEX_PAGE;
                            $params['right_str']='返回上一步';
                            $this->errors($params);
                        }else{
                            //修改成功
                            //显示成功页面
                            $params['title']='邮箱绑定成功';
                            $params['referer']=fuel_url('bms/user_center/uc_index');
                            $params['bigstr']='进入会员中心';
                            $params['bigstr_url']=fuel_url('bms/user_center/uc_index');
                            $params['smallstr']='返回首页';
                            $params['smallstr_url']=INDEX_PAGE;
                            $params['synlogin']='';
                            $this->success_register($params);
                        }
                    }else{
                        //邮箱已存在，报提示
                        $params['error']='该邮箱已被注册';
                        $this->load->view('mobile/bind_email',$params);
                    }
                }else{
                    $this->load->view('mobile/bind_email',$params);
                }
            }else{
                //非法路径
                $params['error_str']='您访问的页面不存在！';
                $params['referer']=INDEX_PAGE;
                $params['right_url']=fuel_url('bms/user_center/uc_index');
                $params['left_str']='返回首页';
                $params['left_url']=INDEX_PAGE;
                $params['right_str']='返回上一步';
                $this->errors($params);
            }
        }else{
            //路径格式不合法，报出错
            $params['error_str']='您访问的页面不存在！';
            $params['referer']=INDEX_PAGE;
            $params['right_url']=fuel_url('bms/user_center/uc_index');
            $params['left_str']='返回首页';
            $params['left_url']=INDEX_PAGE;
            $params['right_str']='返回上一步';
            $this->errors($params);
        }
    }
    
    /**
     * 激活邮箱页面
     */
    function activate_email(){
        //根据用户名查询用户邮箱
        $username=$this->username;
        $uid=$this->uid;
        $wenjuan=$this->load->database('wenjuan',true);
        $w_m_u_re=$wenjuan->select('olduid,username,email,emailstatus')->from('member_user')->where('olduid',$uid)->get();
        $w_m_u_arr=$w_m_u_re->row_array();
        $email=$w_m_u_arr['email'];
        //使用AJAX实现点击“激活邮箱”发邮件功能
        //AJAX入口，进入后，脚本终止，并返回ajax验证结果（正确不输出）
        if($this->uri->segment(6) && $this->uri->segment(7)){
            if($this->uri->segment(6) == 'ajax'){
                switch ($this->uri->segment(7)) {
                    case 'email':
                        //判断邮箱是否为空
                        $error='';
                        if(!empty($email)){
                            //判断邮箱是否激活状态
                            if($w_m_u_arr['emailstatus'] != 2){
                                //邮箱未激活
                                //使用noreply@91survey.com给用户发邮件
                                $to="{$email}";
                                //传递参数，以便修改密码时验证使用
                                $_SESSION['email']=$email;
                                $oldtime=time();
                                $_SESSION['oldtime']=$oldtime;
                                $_SESSION['uid']=$w_m_u_arr['olduid'];
                                $key=md5($oldtime.$email);//长度32,$oldtime和email的拼接
                                $url=fuel_url('bms/user_center/acemail_byemail')."?key={$key}";
                                $message=<<<EOF
                    <div style='padding:30px 0px 40px;background-color:#666666;height:413;' align='center'>
                    <table align='center' border='0' cellpadding='0' cellspacing='0' width='560' height='363' style='-webkit-border-radius:10px;background-color:#ffffff'>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp亲爱的&nbsp$username:
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp要激活您的邮箱，请点击下面的链接。您将到达一个可以激活邮箱的网页。
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp请注意，此链接将于电子邮件发送完三小时后过期。
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp<a target='_blank' style='color:#0088cc' href="$url">点击激活</a>
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp<hr width='520' color='#666666' border='2' style='margin:1em o'/>
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp如果您没有激活您的邮箱，不要担心，— 您的帐户依然安全，无人有权访问它。很可能是有人在<br/>&nbsp&nbsp&nbsp&nbsp尝试激活邮箱时输错自己的电子邮件地址。
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp 谢谢，<br/>&nbsp&nbsp&nbsp&nbsp91survey客户支持
                        </td></tr>
                    </table>
                    </div>
EOF;
                                //发送邮件
                                $re=$this->email($to,$message);
                                if($re){
                                    //邮件发送成功,且已通过session保存对接数据到服务器
                                    //根据用户邮箱格式判断其邮箱官网
                                    $email_list=array(
                                        'qq.com'=>'http://mail.qq.com',
                                        'gmail.com'=>'http://mail.google.com',
                                        'sina.com'=>'http://mail.sina.com.cn',
                                        '163.com'=>'http://mail.163.com',
                                        '126.com'=>'http://mail.126.com',
                                        'yeah.net'=>'http://www.yeah.net/',
                                        'sohu.com'=>'http://mail.sohu.com/',
                                        'tom.com'=>'http://mail.tom.com/',
                                        'sogou.com'=>'http://mail.sogou.com/',
                                        '139.com'=>'http://mail.10086.cn/',
                                        'hotmail.com'=>'http://www.hotmail.com',
                                        'live.com'=>'http://login.live.com/',
                                        'live.cn'=>'http://login.live.cn/',
                                        'live.com.cn'=>'http://login.live.com.cn',
                                        '189.com'=>'http://webmail16.189.cn/webmail/',
                                        'yahoo.com.cn'=>'http://mail.cn.yahoo.com/',
                                        'yahoo.cn'=>'http://mail.cn.yahoo.com/',
                                        'eyou.com'=>'http://www.eyou.com/',
                                        '21cn.com'=>'http://mail.21cn.com/',
                                        '188.com'=>'http://www.188.com/',
                                        'foxmail.coom'=> 'http://www.foxmail.com'
                                    );
                                    $email_arr=explode('@',$email);
                                    $e_k=$email_arr['1'];
                                    if(!empty($email_list["$e_k"])){
                                        $email_url=$email_list["$e_k"];
                                        echo "邮件已发送，<a href='$email_url' id='err' target='_blank'>点击查收</a>";
                                    }else{
                                        echo "邮件已发送。非常规邮箱，请手动查收";
                                    }
                                }else{
                                    //邮件发送失败
                                    $error_url=current_url();
                                    echo "邮件发送失败，<a href='{$error_url}' id='err'>点击刷新</a>，重新发送";
                                }
                            }else{
                                //提示邮箱已激活
                                echo '邮箱已激活';
                            }
                        }else{
                            //邮箱为空，是第三方注册用户，指向绑定邮箱页面。（首页的“点击激活”按钮已指向绑定邮箱页面）
                            $error_url=fuel_url('bms/user_center/bind_email/nobind');
                            echo "未绑定邮箱，<a href='{$error_url}' id='err'>请绑定</a>";
                        }
                            break;
                        default :
                            echo '';
                            break;
                }//switch结尾
                exit;
            }
            exit;
        }//AJAX入口结束
        //判断显示状态
        if($this->uri->segment(5)){
            $params['isact']=$this->uri->segment(5);
            switch ($params['isact']){
                case 'notact':
                    $params['actstr']='发邮件激活';
                    break;
                case 'hasact':
                    $params['actstr']='您的邮箱已激活';
                    break;
                case 'nobind':
                    $params['actstr']='尚未绑定邮箱';
                    break;
                default:
                    $params['actstr']='您的邮箱已激活';
                    break;
            }
        }else{
            $params['isact']='unknow';
            $params['actstr']='您的邮箱已激活';
        }
        //对未激活邮箱进行加密处理
        if(!empty($email)){
            $email_arr=explode('@',$email);
            $length=strlen($email_arr[0]);
            if($length>2){
                $match=substr($email_arr[0],1,-1);
                $email=str_replace($match,'xxxxx',$email_arr[0]).'@'.$email_arr[1];
            }else{
                $match=substr($email_arr[0],0,1);
                $email=$match.'xxxxx'.'@'.$email_arr[1];
            }
            $params['email']=$email;
        }else{
            $params['email']='请绑定邮箱';
        }
        $this->load->view('mobile/activate_email',$params);
    }
    
    /**
     * 链接激活邮箱页面
     */
    function acemail_byemail(){
        //接收授权参数
        if(empty($_SESSION['email']) || empty($_SESSION['oldtime']) || empty($_SESSION['uid']) || empty($_GET['key'])){
            //session为空，报出错，让用户重新发送邮件绑定邮箱
            session_destroy();
            $params['error_str']='链接失效，请重新发送邮件';
            $params['referer']=fuel_url('bms/user_center/uc_index');
            $params['left_str']='返回首页';
            $params['left_url']=INDEX_PAGE;
            $params['right_str']='返回上一步';
            $params['right_url']=fuel_url('bms/user_center/uc_index');
            $this->errors($params);
        }else{
            $key=$_GET['key'];
            $uid=$_SESSION['uid'];
            $email=$_SESSION['email'];
            $oldtime=$_SESSION['oldtime'];
            $now=time();
            //对比参数
            if($key == md5($oldtime.$email)){
                //连接密钥正确，接着检验链接是否过期,3小时3*60*60
                if($now <= $oldtime+10800){
                    //链接可用，激活邮箱操作
                    //更新wenjuan数据库数据
                    $wenjuan=$this->load->database('wenjuan',true);
                    $wj_mu_re=$wenjuan->select('salt')->from('member_user')->where(array('olduid'=>$uid,'email'=>$email))->get();
                    $arr_update_re['wenjuan__member_user']=$wenjuan->update('member_user',array('emailstatus'=>'2'),array('olduid'=>$uid,'email'=>$email));
                    //更新club数据库数据
                    $club=$this->load->database('club',true);
                    $arr_update_re['club__pre_common_member']=$club->update('pre_common_member',array('emailstatus'=>'2'),array('uid'=>$uid,'email'=>$email));
                    //判断是否所有表都新增数据成功，如果有一个不成功，则需要用户重新修改一次密码
                    foreach($arr_update_re as $k=>$v){
                        if($v != true){
                            $is_del["$k"]='defeat';
                        }else{
                            $is_del["$k"]='success';
                        }
                    }
                    if(in_array('defeat',$is_del)){
                        //邮箱激活失败，重新激活
                        $error='邮箱激活失败，请再试一次';
                        $this->load->view('',array('error'=>$error));
                    }else{
                        //邮箱激活成功,保留用户的登录状态
                        //清除session
                        //unset($_SESSION['uid']);
                        unset($_SESSION['email']);
                        unset($_SESSION['oldtime']);
                        //unset($_SESSION['username']);
                        //session_destroy();
                        //显示成功页面
                        $params['title']='邮箱激活成功';
                        $params['referer']=fuel_url('bms/user_center/uc_index');
                        $params['bigstr']='进入会员中心';
                        $params['bigstr_url']=fuel_url('bms/user_center/uc_index');
                        $params['smallstr']='返回首页';
                        $params['smallstr_url']=INDEX_PAGE;
                        $params['synlogin']='';
                        $this->success_register($params);
                    }
                }else{
                    //链接已过期，请重新申请验证。报出错页面，内含连接，点击返回激活邮箱（user_center/uc_index）页面
                    $params['error_str']='链接已过期，请重新发送邮件';
                    $params['referer']=fuel_url('bms/user_center/uc_index');
                    $params['right_url']=fuel_url('bms/user_center/uc_index');
                    $params['left_str']='返回首页';
                    $params['left_url']=INDEX_PAGE;
                    $params['right_str']='返回上一步';
                    $this->errors($params);
                }
            }else{
                //密钥不正确
                //非法操作，显示出错页面。
                $params['error_str']='无效的链接，请重新发送邮件';
                $params['referer']=fuel_url('bms/user_center/uc_index');
                $params['right_url']=fuel_url('bms/user_center/uc_index');
                $params['left_str']='返回首页';
                $params['left_url']=INDEX_PAGE;
                $params['right_str']='返回上一步';
                $this->errors($params); 
            }
        }
    }
    /**
     * @param string $to 收件方邮箱帐号 必填
     * @param sting $message 邮件内容 必填
     * @param string $smtp_user 实际服务的邮箱帐号
     */
    private function email($to,$message,$smtp_user='webmaster@www.91survey.com'){
        $this->load->library('email');
        //$config['protocol'] = 'smtp';
        //$config['smtp_host'] = "{$smtp_host}";
        //$config['smtp_user'] = "{$smtp_user}";//这里写上你的163邮箱账户
        //$config['smtp_pass'] = "{$smtp_pass}";//这里写上你的163邮箱密码
        $config['mailtype'] = 'html';
        $config['validate'] = true;
        $config['priority'] = 1;
        $config['crlf']  = "\r\n";
        //$config['smtp_port'] = 25;
        $config['charset'] = 'utf-8';
        $config['wordwrap'] = TRUE;
    
        $this->email->initialize($config);
    
        $this->email->reply_to('','no_reply');
        $this->email->subject('如何激活您的 91survey 绑定邮箱。');
    
        $this->email->from($smtp_user, $this->config->item('site_name', 'fuel'));
        //$this->email->from("{$smtp_user}", '91survey');//发件人
        $this->email->to("{$to}");
    
        $this->email->message("{$message}");
        $re=$this->email->send();
        //echo $this->email->print_debugger();exit;
        return $re;
    }
    /**
     * 重置密码页面，通过输入正确的老密码设置新密码
     */
    function resetps_byprev(){
        //加载必要和接收资源
        $this->load->library('form_validation');
        $oldpssword=$this->input->post('oldpassword');
        $password=$this->input->post('password');
        //验证字段
        $this->form_validation->set_rules('oldpassword','密码','trim|required|min_length[6]|max_length[32]|alpha_numeric');
        $this->form_validation->set_rules('password','新密码','trim|required|min_length[6]|max_length[32]|alpha_numeric');
        $this->form_validation->set_rules('repassword','确认密码','trim|required|matches[password]');
        $re=$this->form_validation->run();
        //AJAX入口，进入后，脚本终止，并返回ajax验证结果（正确不输出）
        if($this->uri->segment(5) && $this->uri->segment(6)){
            if($this->uri->segment(5) == 'ajax'){
                switch ($this->uri->segment(6)) {
                    case 'oldpassword':
                        //判断老密码格式
                        echo str_replace(' ','',$this->form_validation->error('oldpassword'));
                        break;
                    case 'password':
                        echo str_replace(' ','',$this->form_validation->error('password'));
                        break;
                    case 'repassword':
                        echo str_replace(' ','',$this->form_validation->error('repassword'));
                        break;
                    default :
                        echo '';
                        break;
                }
                exit;
            }
        }
        //接收回调路径
        //$referer=empty($_GET['referer'])?INDEX_PAGE:base64_decode($_GET['referer']);
        //判断字段验证结果
        if($re){
            $username=$this->username;
            $uid=$this->uid;
            //字段验证合法，进入老密码验证
            $wenjuan=$this->load->database('wenjuan',true);
            //密码加密比对！
            $wj_mu_re=$wenjuan->select('salt,password')->from('member_user')->where(array('olduid'=>$uid,'username'=>$username))->get();
            $wj_mu_arr=$wj_mu_re->row_array();
            if(empty($wj_mu_arr)){
                //用户未登入,跳转到登入页面,并提示先登入再使用个人中心
                redirect('wenjuan/bms/mobile/login?redct=uc&referer='.base64_encode(current_url()),'refresh');
            }
            $salt=$wj_mu_arr['salt'];
            $oldpssword=md5(md5($oldpssword).$salt);
            if($wj_mu_arr['password']==$oldpssword){
                //老密码输入正确
                $new_password=md5(md5($password).$salt);
                //更新wenjuan数据库数据
                $arr_update_re['wenjuan__member_user']=$wenjuan->update('member_user',array('password'=>$new_password),array('olduid'=>$uid,'username'=>$username));
                //更新club数据库数据
                $club=$this->load->database('club',true);
                $arr_update_re['club__pre_ucenter_members']=$club->update('pre_ucenter_members',array('password'=>$new_password),array('uid'=>$uid,'username'=>$username));
                $arr_update_re['club__pre_common_member']=$club->update('pre_common_member',array('password'=>$new_password),array('uid'=>$uid,'username'=>$username));
                //更新uhome数据库
                $uhome=$this->load->database('uhome',true);
                $arr_update_re['uhome__uchome_member']=$uhome->update('uchome_member',array('password'=>$new_password),array('uid'=>$uid,'username'=>$username));
                //判断是否所有表都新增数据成功，如果有一个不成功，则需要用户重新修改一次密码
                foreach($arr_update_re as $k=>$v){
                    if($v != true){
                        $is_del["$k"]='defeat';
                    }else{
                        $is_del["$k"]='success';
                    }
                }
                if(in_array('defeat',$is_del)){
                    //改密码失败，重新修改
                    $error='密码修改失败，请再试一次';
                    $this->load->view('mobile/resetps_byprev',array('error'=>$error));
                }else{
                    //密码修改成功,退出登入
                    //调用Ucenter接口
                    include APPPATH.'config/ucenter.php';
                    include APPPATH.'../uc_client/client.php';
                    //销毁session中的用户名和uid，实现SESSION推出登入
                    unset($_SESSION['username']);
                    unset($_SESSION['uid']);
                    session_destroy();
                    //提示密码修改成功，并显示重新登入
                    $params['title']='密码修改成功';
                    $params['referer']=current_url();
                    $params['bigstr']='重新登入';
                    $params['bigstr_url']=fuel_url('bms/mobile/login');
                    $params['smallstr']='回到首页';
                    $params['smallstr_url']=INDEX_PAGE;
                    $params['synlogin']=uc_user_synlogout();
                    $this->load->view('mobile/success_register',$params);
                }
            }else{
                //老密码输入错误
                $error='密码错误，请输入正确的密码';
                $this->load->view('mobile/resetps_byprev',array('error'=>$error));
            }
        }else{
            $this->load->view('mobile/resetps_byprev');
        }
    }
    /**
     * 91币明细页面
     */
    function coin91_detail(){
        //根据用户的登入状态查询用户的91币信息
        $username=$this->username;
        $userid=$this->uid;
        $wenjuan=$this->load->database('wenjuan',true);
        $offset=intval($this->uri->segment(6));
        if($this->uri->segment(5)){
            if($this->uri->segment(5) == 'limit'){
                $status='/limit';
            }else{
                $status='/default';
            }
        }else{
            $status='/default';
        }
        $wcr_re=$wenjuan->select('MAX(blance) as blance,COUNT(userid) as total')
                        ->from('coin_record')
                        ->where('userid',$userid)
                        ->get();
        $wcr_arr=$wcr_re->row_array();
        //获得当前余额
        $blance=$wcr_arr['blance'];
        //得到总条数
        $total_num=$wcr_arr['total'];
        //定义每页条数
        $page_size=5;
        //接收GET参数
        if(!empty($_GET['date'])){
            $d_arr=explode('__',$_GET['date']);
            $start_date=$d_arr[0];
            $close_date=$d_arr[1];
            $params['start_date']=$start_date;
            $params['close_date']=$close_date;
        }else{
            $params['start_date']='';
            $params['close_date']='';
        }
        $params['error']='';
        //得到数组形式详细信息
        if($this->uri->segment(5) && ($this->uri->segment(5)=='limit')){
            //接收表单数据
            if(empty($start_date) && empty($close_date)){
                $start_date=$this->input->post('startdate');
                $close_date=$this->input->post('closedate');
            }
            if($close_date < $start_date){
                //结束日期不能小于开始日期，提示用户
                $params['error']='结束日期不能小于开始日期';
                $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                    ->from('coin_record')
                                    ->where('userid',$userid)
                                    ->limit($page_size,$offset)
                                    ->order_by('createtime desc')
                                    ->get();
                $w_c_r_arr=$w_c_r_re->result_array();
            }else{
                if(!empty($start_date) && !empty($close_date)){
                    $pattern='/^\d{4}-\d{2}-\d{2}$/';
                    $start_re=preg_match($pattern,$start_date);
                    $close_re=preg_match($pattern,$close_date);
                    if($start_re==1 && $close_re==1){
                        $start_arr=explode('-',$start_date);
                        $close_arr=explode('-',$close_date);
                        $start_date=mktime(0,0,0,$start_arr[1],$start_arr[2],$start_arr[0]);
                        $close_date=mktime(23,59,59,$close_arr[1],$close_arr[2],$close_arr[0]);
                        $wcr_re=$wenjuan->select('userid,COUNT(userid) as total')
                                        ->from('coin_record')
                                        ->where(array('userid'=>$userid,'UNIX_TIMESTAMP(createtime) >='=>$start_date,'UNIX_TIMESTAMP(createtime) <='=>$close_date))
                                        ->get();
                        $wcr_arr=$wcr_re->row_array();
                        //获得用户ID
                        $userid=$wcr_arr['userid'];
                        //得到总条数
                        $total_num=$wcr_arr['total'];
                        $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                            ->from('coin_record')
                                            ->where(array('userid'=>$userid,'UNIX_TIMESTAMP(createtime) >='=>$start_date,'UNIX_TIMESTAMP(createtime) <='=>$close_date))
                                            ->limit($page_size,$offset)
                                            ->order_by('createtime desc')
                                            ->get();
                        $w_c_r_arr=$w_c_r_re->result_array();
                        $params['start_date']=date("Y-m-d",$start_date);
                        $params['close_date']=date("Y-m-d",$close_date);
                    }else{
                        $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                        ->from('coin_record')
                                        ->where('userid',$userid)
                                        ->limit($page_size,$offset)
                                        ->order_by('createtime desc')
                                        ->get();
                        $w_c_r_arr=$w_c_r_re->result_array();
                    }
                }else{
                    $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                    ->from('coin_record')
                                    ->where('userid',$userid)
                                    ->limit($page_size,$offset)
                                    ->order_by('createtime desc')
                                    ->get();
                    $w_c_r_arr=$w_c_r_re->result_array();
                }
            }
        }else{
            $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                            ->from('coin_record')
                            ->where('userid',$userid)
                            ->limit($page_size,$offset)
                            ->order_by('createtime desc')
                            ->get();
            $w_c_r_arr=$w_c_r_re->result_array();
        }
        
        //使用分页类
        $this->load->library('page');
        $config['base_url']=fuel_url('bms/user_center/coin91_detail').$status;
        $config['total_rows']=$total_num;
        $config['per_page']=$page_size;
        $config['first_link']='首页';
        $config['next_link']='下一页';
        $config['last_link']='尾页';
        $config['prev_link']='上一页';
        $config['num_links']=2;
        $config['uri_segment']=6;
        $config['reuse_query_string']=true;
        //$config['attributes']=array('class'=>'submit');
        
        $this->page->initialize($config);
        //传递参数,并加载页面
        $params['blance']=intval($blance);
        $params['total_num']=$total_num;
        $params['rows']=$w_c_r_arr;
        $links=$this->page->create_links();
        $params['links']= $links;
        $this->load->view('mobile/coin91_detail',$params);
    }
    /**
     * 现金明细页面
     */
    function cash_detail(){
        $username=$this->username;
        $userid=$this->uid;
        $wenjuan=$this->load->database('wenjuan',true);
        $offset=intval($this->uri->segment(6));
        if($this->uri->segment(5)){
            if($this->uri->segment(5) == 'limit'){
                $status='/limit';
            }else{
                $status='/default';
            }
        }else{
            $status='/default';
        }
        $wcr_re=$wenjuan->select('MAX(blance) as blance,COUNT(userid) as total')
                        ->from('cash_record')
                        ->where('userid',$userid)
                        ->get();
        $wcr_arr=$wcr_re->row_array();
        //获得当前余额
        $blance=$wcr_arr['blance'];
        //得到总条数
        $total_num=$wcr_arr['total'];
        //定义每页条数
        $page_size=5;
        //接收GET参数
        if(!empty($_GET['date'])){
            $d_arr=explode('__',$_GET['date']);
            $start_date=$d_arr[0];
            $close_date=$d_arr[1];
            $params['start_date']=$start_date;
            $params['close_date']=$close_date;
        }else{
            $params['start_date']='';
            $params['close_date']='';
        }
        $params['error']='';
        //得到数组形式详细信息
        if($this->uri->segment(5) && ($this->uri->segment(5)=='limit')){
            //接收表单数据
            if(empty($start_date) && empty($close_date)){
                $start_date=$this->input->post('startdate');
                $close_date=$this->input->post('closedate');
            }
            if($close_date < $start_date){
                //结束日期不能小于开始日期，提示用户
                $params['error']='结束日期不能小于开始日期';
                $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                    ->from('cash_record')
                                    ->where('userid',$userid)
                                    ->limit($page_size,$offset)
                                    ->order_by('createtime desc')
                                    ->get();
                $w_c_r_arr=$w_c_r_re->result_array();
            }else{
                if(!empty($start_date) && !empty($close_date)){
                    $pattern='/^\d{4}-\d{2}-\d{2}$/';
                    $start_re=preg_match($pattern,$start_date);
                    $close_re=preg_match($pattern,$close_date);
                    if($start_re==1 && $close_re==1){
                        $start_arr=explode('-',$start_date);
                        $close_arr=explode('-',$close_date);
                        $start_date=mktime(0,0,0,$start_arr[1],$start_arr[2],$start_arr[0]);
                        $close_date=mktime(23,59,59,$close_arr[1],$close_arr[2],$close_arr[0]);
                        $wcr_re=$wenjuan->select('userid,COUNT(userid) as total')
                                        ->from('cash_record')
                                        ->where(array('userid'=>$userid,'UNIX_TIMESTAMP(createtime) >='=>$start_date,'UNIX_TIMESTAMP(createtime) <='=>$close_date))
                                        ->get();
                        $wcr_arr=$wcr_re->row_array();
                        //获得用户ID
                        $userid=$wcr_arr['userid'];
                        //得到总条数
                        $total_num=$wcr_arr['total'];
                        $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                            ->from('cash_record')
                                            ->where(array('userid'=>$userid,'UNIX_TIMESTAMP(createtime) >='=>$start_date,'UNIX_TIMESTAMP(createtime) <='=>$close_date))
                                            ->limit($page_size,$offset)
                                            ->order_by('createtime desc')
                                            ->get();
                        $w_c_r_arr=$w_c_r_re->result_array();
                        $params['start_date']=date("Y-m-d",$start_date);
                        $params['close_date']=date("Y-m-d",$close_date);
                    }else{
                        $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                            ->from('cash_record')
                                            ->where('userid',$userid)
                                            ->limit($page_size,$offset)
                                            ->order_by('createtime desc')
                                            ->get();
                        $w_c_r_arr=$w_c_r_re->result_array();
                    }
                }else{
                    $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                        ->from('cash_record')
                                        ->where('userid',$userid)
                                        ->limit($page_size,$offset)
                                        ->order_by('createtime desc')
                                        ->get();
                    $w_c_r_arr=$w_c_r_re->result_array();
                }
            }
        }else{
            $w_c_r_re=$wenjuan->select('income,blance,ruelsname,createtime,rulesextname')
                                ->from('cash_record')
                                ->where('userid',$userid)
                                ->limit($page_size,$offset)
                                ->order_by('createtime desc')
                                ->get();
            $w_c_r_arr=$w_c_r_re->result_array();
        }
        
        //使用分页类
        $this->load->library('page');
        $config['base_url']=fuel_url('bms/user_center/cash_detail').$status;
        $config['total_rows']=$total_num;
        $config['per_page']=$page_size;
        $config['first_link']='首页';
        $config['next_link']='下一页';
        $config['last_link']='尾页';
        $config['prev_link']='上一页';
        $config['num_links']=2;
        $config['uri_segment']=6;
        $config['reuse_query_string']=true;
        //$config['attributes']=array('class'=>'submit');
        
        $this->page->initialize($config);
        //传递参数,并加载页面
        $params['blance']=intval($blance);
        $params['total_num']=$total_num;
        $params['rows']=$w_c_r_arr;
        $links=$this->page->create_links();
        $params['links']= $links;
        $this->load->view('mobile/cash_detail',$params);
    }
    /**
     * 退出按钮跳转页面，实现用户退出
     */
    function quit(){
        //退出登入状态
        //调用Ucenter接口
        include APPPATH.'config/ucenter.php';
        include APPPATH.'../uc_client/client.php';
        //显示提示，密码修改成功，请登入
        //销毁session中的用户名和uid，实现SESSION退出登入
        session_start();
        unset($_SESSION['username']);
        unset($_SESSION['uid']);
        session_destroy();
        $url=fuel_url('bms/mobile/login');
        echo uc_user_synlogout()."<script type='text/javascript'>
        setTimeout(window.location.href='{$url}',1000);</script>";
    }
    /**
     * @param array $arr 给前台success_register界面传参数
     * 作用是显示前台success_register界面
     */
    function success_register($arr=array()){
        $this->load->view('mobile/success_register',$arr);
    }
    /**
     * @param array $arr 调用“出错了”页面，通过传值显示不同提示和链接
     */
    function errors($arr=array()){
        $this->load->view('mobile/errors',$arr);
    }
}