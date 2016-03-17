<?php
/*
 *   1、页面跳转过来需要有回调地址referer，且referer是base64_encode()处理过的，登入或者注册后会跳回去
 *   2、构造函数里定义的INDEX_PAGE是当前项目首页，如有更改，请在构造函数更改
 *   code by lynn
 *   */
class Mobile extends CI_Controller{
    function __construct(){
        parent::__construct();
        $this->load->helper('url');
        define("INDEX_PAGE",fuel_url('bms/user_center/uc_index'));
    }
    /**
     * 1、显示注册界面
     * 2、对注册信息和验证码进行判断
     * 3、最后将符合条件且注册成功的用户信息写入到数据表
     */
    function register(){
        //加载必要的资源
        $this->load->library('form_validation');
        //接收回调路径
        $referer=empty($_GET['referer'])?INDEX_PAGE:base64_decode($_GET['referer']);
        //进行表单字段验证
        $this->form_validation->set_rules('email','邮箱','trim|required|valid_email|max_length[100]');
        $this->form_validation->set_rules('username','用户名','trim|required|max_length[20]');
        $this->form_validation->set_rules('password','密码','trim|required|min_length[6]|max_length[32]|alpha_numeric');
        $this->form_validation->set_rules('repassword','确认密码','trim|required|matches[password]');
        //设置表单验证错误信息标记设置，避免和前端的标记有效果冲突
        //$this->form_validation->set_error_delimiters('<em><b>','</b></em>');
        //运行表单验证
        $re=$this->form_validation->run();
        //AJAX入口，进入后，脚本终止，并返回ajax验证结果（正确不输出）
        if($this->uri->segment(5) && $this->uri->segment(6)){
            if($this->uri->segment(5) == 'ajax'){
                $club=$this->load->database('club',true);
                $email=$this->input->post('email');
                $username=$this->input->post('username');
                switch ($this->uri->segment(6)) {
                    case 'email':
                        //判断邮箱是否已经被注册
                        if(!empty($email)){
                            $email_re=$club->select('uid')->where('email',$email)->get('pre_ucenter_members');
                            $em_un_re=$email_re->row_array();
                            if(!empty($em_un_re)){
                                echo '该email已经被注册';
                            }else{
                                echo str_replace(' ','',$this->form_validation->error('email'));
                            }
                        }else{
                            echo str_replace(' ','',$this->form_validation->error('email'));
                        }
                        break;
                    case 'username':
                        //判断用户名是否已经存在
                        if(!empty($username)){
                            $user_re=$club->select('uid')->where('username',$username)->get('pre_ucenter_members');
                            $us_un_re=$user_re->row_array();
                            if(!empty($us_un_re)){
                                echo '用户名已经存在';
                            }else{
                                echo str_replace(' ','',$this->form_validation->error('username'));
                            }
                        }else{
                            echo str_replace(' ','',$this->form_validation->error('username'));
                        }
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
        //判断验证结果
        if($re){
            //判断验证码是否正确
            $verify=strtolower($this->input->post('verify'));
            session_start();
            $error='';
            if($_SESSION['verify'] == $verify){
                //后台判断是否接受众调网服务条款
                $rule=$this->input->post('rule');
                if($rule == 'agree'){
                    //将需要入表的数据生成关联数组
                    $insert['username']=$this->input->post('username');
                    $insert['email']=$this->input->post('email');
                    $salt=mt_rand(100000,999999);
                    $insert['salt']=$salt;
                    $insert['password']=md5(md5($this->input->post('password')).$salt);
                    $insert['regdate']=time();
                    $insert['regip']=($_SERVER['REMOTE_ADDR'] == '::1'?'127.0.0.1':$_SERVER['REMOTE_ADDR']);
                    $insert['logintype']='mobile';
                    //ucenter同步注册
                    include APPPATH.'config/ucenter.php';
                    include APPPATH.'../uc_client/client.php';
                    $uid = uc_user_register($insert['username'], $insert['password'], $insert['email']);
                    if($uid<=0){
                        //Ucenter同步注册失败
                        $array_er=array(
                            'error_u'=>'',
                            'error_e'=>'',
                            'error'=>''
                        );
                        switch($uid){
                            case -1:
                                $array_er['error_u']='用户名不合法';
                                break;
                            case -2:
                                $array_er['error_u']='用户名包含不被允许的词语';
                                break;
                            case -3:
                                $array_er['error_u']='用户名已经存在';
                                break;
                            case -4:
                                $array_er['error_e']='email格式有误';
                                break;
                            case -5:
                                $array_er['error_e']='不被允许注册的email';
                                break;
                            case -6:
                                $array_er['error_e']='该email已经被注册';
                                break;
                            default:
                                $array_er['error']='未知错误，注册失败，请重新注册';
                                break;
                        }
                        $array_er['referer']=$referer;
                        $this->load->view('mobile/register',$array_er);
                    }else{
                        //将UID的值传给$insert
                        $insert['uid']=$uid;
                        //Ucenter同步注册成功，调用数据模型类，数据入表pre_ucenter_members
                        $club=$this->load->database('club',true);
                        //$club__pre_ucenter_members=$club->insert('pre_ucenter_members',$insert);
                        //同步数据到同数据库的pre_common_member表中
                        $club__pre_common_member=$club->insert('pre_common_member',
                            array('uid'=>$insert['uid'],
                                  'email'=>$insert['email'],
                                  'username'=>$insert['username'],
                                  'password'=>$insert['password']
                        ));
                        //同步数据到不同数据库的表中
                        //uhome库中uchome_member表
                        $uhome=$this->load->database('uhome',true);
                        $uhome__uchome_member=$uhome->insert('uchome_member',
                            array('uid'=>$insert['uid'],
                                  'username'=>$insert['username'],
                                  'password'=>$insert['password']
                        ));
                        //wenjuan库中member_user表
                        $wenjuan=$this->load->database('wenjuan',true);
                        $wenjuan__member_user=$wenjuan->insert('member_user',
                            array('olduid'=>$insert['uid'],
                                  'username'=>$insert['username'],
                                  'password'=>$insert['password'],
                                  'salt'=>$insert['salt'],
                                  'logintype'=>$insert['logintype'],
                                  'email'=>$insert['email']
                        )); 
                        //判断是否四表连插都成功
                        if($club__pre_common_member===true && $uhome__uchome_member===true && $wenjuan__member_user===true){
                            //注册成功且四表都插入成功后，客户端需要默认处于登入状态，效果等同于登入操作
                            //session保存用户名和uid
                            session_start();
                            $_SESSION['username']=$insert['username'];
                            $_SESSION['uid']=$insert['uid'];
                            $synlogin=uc_user_synlogin($insert['uid']);
                            $params['title']='注册成功';
                            $params['referer']=$referer;
                            $params['bigstr']='进入会员中心';
                            $params['bigstr_url']=fuel_url('bms/user_center/uc_index');
                            $params['smallstr']='立即返回';
                            $params['smallstr_url']=$referer;
                            $params['synlogin']=$synlogin;
                            $this->success_register($params);
                        }else{
                            //如有插入失败，删除所有刚写入信息，重新注册，确保信息都能同步（表引擎为MyISAM，不支持MYsql事务）
                            $arr_re=compact('club__pre_ucenter_members','club__pre_common_member','uhome__uchome_member','wenjuan__member_user');
                            foreach($arr_re as $k=>$v){
                                //只删除已经插入成功的表
                                if($v==true){
                                    $arr_f=explode('__',$k);
                                    if($arr_f['0'] == 'wenjuan'){
                                        $del=$this->load->database($arr_f['0'],true);
                                        $del->delete("{$arr_f['1']}",array('olduid'=>$insert['uid']));
                                    }else{
                                        $del=$this->load->database($arr_f['0'],true);
                                        $del->delete("{$arr_f['1']}",array('uid'=>$insert['uid']));
                                    }
                                }
                            }
                            $error='注册失败，重新注册';
                            $this->load->view('mobile/register',array('error_uc'=>$error,'referer'=>$referer));
                        }
                    }
                }else{
                    $error='需要接受众调网服务条款';
                    $this->load->view('mobile/register',array('error_ru'=>$error,'referer'=>$referer));
                }
            }else{
                $error='验证码错误';
                $this->load->view('mobile/register',array('error_ve'=>$error,'referer'=>$referer));
            }
        }else{
            $this->load->view('mobile/register',array('referer'=>$referer));
        }
    }
    
    /**
     * 调用验证码类，将生成的验证码直接输出到浏览器，验证码字符存储到$_SESSION['verify']中
     */
    function show_verify(){
        $this->load->library('verify');
        $vars=array(
            //'img_path'=>$img_path,
            //'img_url'=>base_url().'/captcha/',
            'img_width'=>'159',
            'img_height'=>'50',
            'font_path'=>'./wenjuan/codeigniter/fonts/mobile/DJB Belly Button-Outtie.ttf',
            'font_size'=>'22'
            //'expiration'=>'300'
        );
        $this->verify->get_params($vars);
        $this->verify->show_verify();
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
     * 登录页面
     */
    function login(){
        //加载必要资源
        $this->load->library('form_validation');
        //接收回调路径
        $referer=empty($_GET['referer'])?INDEX_PAGE:base64_decode($_GET['referer']);
        //开始字段验证
        $this->form_validation->set_rules('username','用户名','trim|required|max_length[20]');
        $this->form_validation->set_rules('password','密码','trim|required|min_length[6]|max_length[32]|alpha_numeric');
        $re=$this->form_validation->run();
        //AJAX入口，进入后，脚本终止，并返回ajax验证结果（正确不输出）
        if($this->uri->segment(5) && $this->uri->segment(6)){
            if($this->uri->segment(5) == 'ajax'){
                switch ($this->uri->segment(6)) {
                    case 'username':
                        echo str_replace(' ','',$this->form_validation->error('username'));
                        break;
                    case 'password':
                        echo str_replace(' ','',$this->form_validation->error('password'));
                        break;
                    default :
                        echo '';
                        break;
                }
                exit;
            }
        }
        //判断验证是否成功
        $error='';
        if($re){
            //判断验证码是否正确
            $verify=strtolower($this->input->post('verify'));
            session_start();
            if($_SESSION['verify'] == $verify){
                //判断用户名或者密码是否正确
                $username=$this->input->post('username');
                $wenjuan=$this->load->database('wenjuan',true);
                $w_m_u_ure=$wenjuan->select('olduid,salt')->from('member_user')->where('username',"{$username}")->get();
                $w_m_u_uarr=$w_m_u_ure->row_array();
                if(!empty($w_m_u_uarr)){
                    $password=md5(md5($this->input->post('password')).$w_m_u_uarr['salt']);
                    $w_m_u_pre=$wenjuan->select('olduid')->from('member_user')->where(array('username'=>$username,'password'=>$password,'olduid'=>$w_m_u_uarr['olduid']))->get();
                    $w_m_u_parr=$w_m_u_pre->row_array();
                    if(!empty($w_m_u_parr)){
                        //账号密码正确，进入同步登入
                        //调用Ucenter接口
                        include APPPATH.'config/ucenter.php';
                        include APPPATH.'../uc_client/client.php';
                        //同步登入，并跳转到回调界面
                        //session保存用户名和uid
                        session_start();
                        $_SESSION['username']=$username;
                        $_SESSION['uid']=$w_m_u_parr['olduid'];
                        echo uc_user_synlogin($w_m_u_parr['olduid'])."<script type='text/javascript'>
                        setTimeout(window.location.href='{$referer}',1000);</script>";//{$referer}
                    }else{
                        $error='密码错误';//用户名存在，密码错误
                        $this->load->view('mobile/login',array('error_ps'=>$error,'referer'=>$referer));
                    }
                }else{
                    $error='用户名错误';//用户名不存在
                    $this->load->view('mobile/login',array('error_us'=>$error,'referer'=>$referer));
                }
            }else{
                $error='验证码错误';
                $this->load->view('mobile/login',array('error_ve'=>$error,'referer'=>$referer));
            }
        }else{
            //字段验证错误
            //判断是否是其他页面重定向过来的
            if(!empty($_GET['redct'])){
                $redct=$_GET['redct'];
                switch($redct){
                    case 'uc':
                        $error='请先登入，再使用会员中心';
                        break;
                    default:
                        break;
                }
            }
            $this->load->view('mobile/login',array('error_up'=>$error,'referer'=>$referer));
        }
    }
    
    /**
     * 填写邮箱页面，给验证后的邮箱发邮件，重置密码
     */
    function getback_password(){
        //接收回调路径
        $referer=empty($_GET['referer'])?INDEX_PAGE:base64_decode($_GET['referer']);
        //字段验证
        $this->load->library('form_validation');
        $this->form_validation->set_rules('email','邮箱','trim|required|valid_email|max_length[100]');
        $re=$this->form_validation->run();
        if($re){
            //接收邮箱字段值，发送重置密码的邮件
            $email=$this->input->post('email');
            //通过邮箱判断邮箱是否被注册过
            $wenjuan=$this->load->database('wenjuan',true);
            $w_m_u_re=$wenjuan->select('olduid,username,emailstatus')->from('member_user')->where('email',$email)->get();
            $w_m_u_arr=$w_m_u_re->row_array();
            $error='';
            if(!empty($w_m_u_arr)){
                //判断邮箱是否激活状态
                if($w_m_u_arr['emailstatus'] == 2){
                    //邮箱已激活
                    $uid=$w_m_u_arr['olduid'];
                    $username=$w_m_u_arr['username'];
                    //使用noreply@91survey.com给用户发邮件
                    $to="{$email}";
                    //传递参数，以便修改密码时验证使用
                    session_start();
                    $_SESSION['email']=$email;
                    $oldtime=time();
                    $_SESSION['oldtime']=$oldtime;
                    $_SESSION['uid']=$uid;
                    $key=md5($oldtime.$email);//长度32,$oldtime和email的拼接
                    $url=fuel_url('bms/mobile/resetps_byemail')."?key={$key}";
                    $message=<<<EOF
                    <div style='padding:30px 0px 40px;background-color:#666666;height:413;' align='center'>
                    <table align='center' border='0' cellpadding='0' cellspacing='0' width='560' height='363' style='-webkit-border-radius:10px;background-color:#ffffff'>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp亲爱的&nbsp$username:
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp要重设您的 91survey ID 密码，请点击下面的链接。您将到达一个可以创建新密码的网页。
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp请注意，此链接将于电子邮件发送完三小时后过期。
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp<a target='_blank' style='color:#0088cc' href="$url">重设您的 91survey ID 密码</a>
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp<hr width='520' color='#666666' border='2' style='margin:1em o'/>
                        </td></tr>
                        <tr bgcolor='#ffffff' align='left' valign='middle' style='margin:0px;padding=6px 6px;width:524px;font-family: Lucida Grande, Arial, Helvetica, Geneva, Verdana, sans-serif; color:#000000;font-size:12px;line-height:1.25em'><td>
                             &nbsp&nbsp&nbsp&nbsp如果您没有尝试重设密码，不要担心，— 您的帐户依然安全，无人有权访问它。很可能是有人在<br/>&nbsp&nbsp&nbsp&nbsp尝试重设自己的密码时输错自己的电子邮件地址。
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
                        $email=base64_encode($email);
                        $referer=base64_encode($referer);
                        header('location:'.fuel_url('bms/mobile/next')."?email=$email&referer=$referer");
                    }else{
                        //邮件发送失败
                        $error='邮件发送失败，重新发送';
                        $this->load->view('mobile/back_password',array('referer'=>$referer,'error_em'=>$error));
                    } 
                }else{
                    //邮箱未激活，提示其进入会员中心首页激活
                    $error='邮箱未激活<br/>请点击左上角返回按钮，进入会员中心激活';
                    $this->load->view('mobile/back_password',array('referer'=>$referer,'error_em'=>$error,'back_url'=>fuel_url('bms/user_center/uc_index')));
                }
            }else{
                //该邮箱未被注册过
                $error='无法使用未注册的邮箱';
                $this->load->view('mobile/back_password',array('referer'=>$referer,'error_em'=>$error));
            }
        }else{
            //邮箱格式不正确
            $this->load->view('mobile/back_password',array('referer'=>$referer));
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
        $this->email->subject('如何重设您的 91survey ID密码。');
        
        $this->email->from($smtp_user, $this->config->item('site_name', 'fuel'));
        //$this->email->from("{$smtp_user}", '91survey');//发件人
        $this->email->to("{$to}");
        
        $this->email->message("{$message}");
        $re=$this->email->send();
        //echo $this->email->print_debugger();exit;
        return $re;
    }
    
    /**
     * 根据邮箱链接修改密码，链接3小时内有效
     */
    function resetps_byemail(){
        //接收授权参数
        session_start();
        if(empty($_SESSION['email']) || empty($_SESSION['oldtime']) || empty($_SESSION['uid']) || empty($_GET['key'])){
            //session为空，报出错，让用户重新发送邮件重置密码
            session_destroy();
            $params['error_str']='链接失效，请重新发送邮件！';
            $params['referer']=fuel_url('bms/mobile/getback_password');
            $params['right_url']=fuel_url('bms/mobile/getback_password');
            $params['left_str']='返回首页';
            $params['left_url']=INDEX_PAGE;
            $params['right_str']='返回上一步';
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
                    //进入密码修改页面
                    //验证两次密码是否一致
                    $this->load->library('form_validation');
                    $this->form_validation->set_rules('password','密码','trim|required|min_length[6]|max_length[32]|alpha_numeric');
                    $this->form_validation->set_rules('repassword','确认密码','trim|required|matches[password]');
                    $re=$this->form_validation->run();
                    //ajax验证
                    if($this->uri->segment(5) && $this->uri->segment(6)){
                        if($this->uri->segment(5) == 'ajax'){
                            switch ($this->uri->segment(6)) {
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
                    if($re){
                        //格式验证成功，进行修改密码操作
                        //更新wenjuan数据库数据
                        $wenjuan=$this->load->database('wenjuan',true);
                        $password=$this->input->post('password');
                        $wj_mu_re=$wenjuan->select('salt')->from('member_user')->where(array('olduid'=>$uid,'email'=>$email))->get();
                        $wj_mu_arr=$wj_mu_re->row_array();
                        $salt=$wj_mu_arr['salt'];
                        $new_password=md5(md5($password).$salt);
                        $arr_update_re['wenjuan__member_user']=$wenjuan->update('member_user',array('password'=>$new_password),array('olduid'=>$uid,'email'=>$email));
                        //更新club数据库数据
                        $club=$this->load->database('club',true);
                        $arr_update_re['club__pre_ucenter_members']=$club->update('pre_ucenter_members',array('password'=>$new_password),array('uid'=>$uid,'email'=>$email));
                        $arr_update_re['club__pre_common_member']=$club->update('pre_common_member',array('password'=>$new_password),array('uid'=>$uid,'email'=>$email));
                        //更新uhome数据库
                        $uhome=$this->load->database('uhome',true);
                        $arr_update_re['uhome__uchome_member']=$uhome->update('uchome_member',array('password'=>$new_password),array('uid'=>$uid));
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
                            $this->load->view('mobile/resetps_byemail',array('error'=>$error,'referer'=>INDEX_PAGE));
                        }else{
                            //密码修改成功，清除session
                            unset($_SESSION['uid']);
                            unset($_SESSION['email']);
                            unset($_SESSION['oldtime']);
                            unset($_SESSION['username']);
                            session_destroy();
                            //退出登入状态
                            //调用Ucenter接口
                            include APPPATH.'config/ucenter.php';
                            include APPPATH.'../uc_client/client.php';
                            //显示提示，密码修改成功，请登入
                            $params['title']='密码修改成功';
                            $params['referer']=fuel_url('bms/mobile/login');
                            $params['bigstr']='立即登入';
                            $params['bigstr_url']=fuel_url('bms/mobile/login');
                            $params['smallstr']='返回首页';
                            $params['smallstr_url']=INDEX_PAGE;
                            $params['synlogin']=uc_user_synlogout();
                            $this->success_register($params);
                        }
                    }else{
                        //验证不成功,前端提示错误信息
                        $this->load->view('mobile/resetps_byemail',array('referer'=>INDEX_PAGE));
                    }
                }else{
                    //链接已过期，请重新申请验证。报出错页面，内含连接，点击返回back_password页面
                    $params['error_str']='链接已过期，请重新发送邮件！';
                    $params['referer']=fuel_url('bms/mobile/getback_password');
                    $params['right_url']=fuel_url('bms/mobile/getback_password');
                    $params['left_str']='返回首页';
                    $params['left_url']=INDEX_PAGE;
                    $params['right_str']='返回上一步';
                    $this->errors($params);
                }
            }else{
                //非法操作，显示出错页面。
                $params['error_str']='无效的链接，请重新发送邮件！';
                $params['referer']=fuel_url('bms/mobile/getback_password');
                $params['right_url']=fuel_url('bms/mobile/getback_password');
                $params['left_str']='返回首页';
                $params['left_url']=INDEX_PAGE;
                $params['right_str']='返回上一步';
                $this->errors($params);
            }
        }
    }
    
    /**
     * @param string $email传入取回密码的验证邮箱
     * 此方法是已发送验证邮件后执行，跳转到next页面
     */
    function next(){
        //接收回调路径
        $referer=empty($_GET['referer'])?INDEX_PAGE:base64_decode($_GET['referer']);
        $email=empty($_GET['email'])?'':base64_decode($_GET['email']);
        //传递数据，加载next页
        if(!empty($email)){
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
            $e_k=$email_arr[1];
            if(!empty($email_list["$e_k"])){
                $email_url=$email_list["$e_k"];
                $email_str='点击查收';
            }else{
                $email_url='#';
                $email_str='邮箱首页无法确定，请手动登入邮箱';
            }
        }else{
            //$email=''
            $email_url='#';
            $email_str='邮箱首页无法确定，请手动登入邮箱';
        }
        $this->load->view('mobile/next',array('url'=>$email_url,'str'=>$email_str,'referer'=>$referer));
    }
    
    /**
     * @param array $arr 给前台success_register界面传参数
     * 作用是显示前台success_register界面
     */
    function success_register($arr=array()){
        $this->load->view('mobile/success_register',$arr);
    }
    /**
     * 调用QQ登入接口，进入QQ登入界面，然后跳转到$this->qq_go_back()
     */
    function qq_login(){
        //接收回调路径
        $referer=empty($_GET['referer'])?INDEX_PAGE:$_GET['referer'];
        //$referer=str_replace('localhost','91survey.com',$referer);
        require_once('wenjuan/modules/bms/assets/plugins/mobile/qq/qqConnectAPI.php');
        $qc=new QC();
        $qc->qq_login($referer);
    }
    
    /**
     * QQ登入成功后来到此界面。中转界面，保存登入后需要跳回登入操作前的界面
     */
    function qq_go_back(){
        //调用QQ接口，获取用户信息存入数组$qq_arr
        require_once('wenjuan/modules/bms/assets/plugins/mobile/qq/qqConnectAPI.php');
        $qc=new QC();
        $atk=$qc->qq_callback();
        $oid=$qc->get_openid();
        $qc=new QC($atk,$oid);
        $qq_arr = $qc->get_user_info();
        //接收回调路径
        $referer=empty($_GET['referer'])?INDEX_PAGE:base64_decode($_GET['referer']);
        //判断用户是否使用QQ登入过
        $wenjuan=$this->load->database('wenjuan',true);
        $wj_re=$wenjuan->select('userid,olduid,username,password,nickname,otherlogin')->from('member_user')->where('otherlogin','qq_'.$oid)->get();
        $wj_arr=$wj_re->row_array();
        //判断此otherlogin是否已存在，如果存在则用户不是第一次用QQ登入，不用再写入用户信息
        //调用Ucenter接口
        include APPPATH.'config/ucenter.php';
        include APPPATH.'../uc_client/client.php';
        if(empty($wj_arr)){
            //用户第一次登入，执行第三方登入注册流程
            //形成新增信息数组
            $insert['username']=$qq_arr['nickname'].time();
            $salt=mt_rand(100000,999999);
            $insert['salt']=$salt;
            $insert['password']=md5(md5('').$salt);
            $insert['email']='';
            $insert['regdate']=time();
            $insert['regip']=($_SERVER['REMOTE_ADDR'] == '::1'?'127.0.0.1':$_SERVER['REMOTE_ADDR']);
            //club数据库，数据入表pre_ucenter_members
            $club=$this->load->database('club',true);
            $arr_insert_re['club__pre_ucenter_members']=$club->insert('pre_ucenter_members',$insert);
            //获取pre_ucenter_members表的新增ID
            $uid=$club->insert_id();
            $insert['uid']=$uid;
            $insert['nickname']=$qq_arr['nickname'];
            //club数据库，数据入表pre_common_member
            $arr_insert_re['club__pre_common_member']=$club->insert('pre_common_member',
                array('uid'=>$insert['uid'],
                    'email'=>$insert['email'],
                    'username'=>$insert['username'],
                    'password'=>$insert['password']
                ));
            //wenjuan数据库，数据入表member_user
            $wenjuan=$this->load->database('wenjuan',true);
            $arr_insert_re['wenjuan__member_user']=$wenjuan->insert('member_user',
                array('olduid'=>$insert['uid'],
                    'username'=>$insert['username'],
                    'password'=>$insert['password'],
                    'nickname'=>$insert['nickname'],
                    'grade'=>1,
                    'coin91'=>0,
                    'cash'=>0,
                    'salt'=>$insert['salt'],
                    'otherlogin'=>'qq_'.$oid,
                    'logintype'=>'mobile',
                    'modifyflag'=>1//此字段默认值为2，1表示可以更改一次用户名
                ));
            $userid=$wenjuan->insert_id();
            //wenjuan数据库，数据入表member_base
            //将性别转换为数字
            if(!empty($qq_arr['gender'])){
                switch ($qq_arr['gender']){
                    case '男':
                        $insert['gender']=1;
                        break;
                    case '女':
                        $insert['gender']=2;
                        break;
                    default:
                        $insert['gender']=0;
                        break;
                }
            }
            //获取province和city的数字代码
            $sql_pro="SELECT `id` FROM `common_district` WHERE `name` LIKE '{$qq_arr['province']}%';";
            $pro_re=$wenjuan->query($sql_pro);
            $pro_arr=$pro_re->row_array();
            $insert['province']=$pro_arr['id'];
            
            $sql_cit="SELECT `id` FROM `common_district` WHERE `name` LIKE '%{$qq_arr['city']}%' AND  `upid` = {$pro_arr['id']};";
            $cit_re=$wenjuan->query($sql_cit);
            $cit_arr=$cit_re->row_array();
            $insert['city']=$cit_arr['id'];
            $arr_insert_re['wenjuan__member_base']=$wenjuan->insert('member_base',
                array(
                    'userid'=>$userid,
                    'gender'=>$insert['gender'],
                    'bornyear'=>$qq_arr['year'],
                    'provicne'=>$insert['province'],
                    'city'=>$insert['city']
                ));
            //uhome数据库，数据入表uchome_member
            $uhome=$this->load->database('uhome',true);
            $arr_insert_re['uhome__uchome_member']=$uhome->insert('uchome_member',
                array('uid'=>$insert['uid'],
                    'username'=>$insert['username'],
                    'password'=>$insert['password']
                ));
            //判断是否所有表都新增数据成功，如果有一个不成功，则把上一步所有表新增的数据删除，退到QQ登入之前的界面
            foreach($arr_insert_re as $k=>$v){
                if($v != true){
                    $is_del["$k"]='defeat';
                }else{
                    $is_del["$k"]='success';
                }
            }
            if(in_array('defeat',$is_del)){
                //注册失败，重新注册
                foreach($arr_insert_re as $k=>$v){
                    //只删除已经插入成功的表
                    if($v==true){
                        $arr_f=explode('__',$k);
                        if($arr_f['0'] == 'wenjuan'){
                            if($arr_f['1'] == 'member_user'){
                                $del=$this->load->database($arr_f['0'],true);
                                $del->delete("{$arr_f['1']}",array('olduid'=>$insert['uid']));
                            }else if($arr_f['1'] == 'member_base'){
                                $del=$this->load->database($arr_f['0'],true);
                                $del->delete("{$arr_f['1']}",array('userid'=>$insert['uid']));
                            }
                        }else{
                            $del=$this->load->database($arr_f['0'],true);
                            $del->delete("{$arr_f['1']}",array('uid'=>$insert['uid']));
                        }
                    }
                }
                //进入注册失败，出错界面，点击能弹回QQ登入之前的界面
                $params['error_str']='登入失败，请重新登入！';
                $params['referer']=$referer;
                $params['right_url']=$referer;
                $params['left_str']='返回首页';
                $params['left_url']=INDEX_PAGE;
                $params['right_str']='返回上一步';
                $this->errors($params);
            }else{
                //Ucenter同步登入
                //用户第一次使用QQ登入，需要跳转到注册成功页面，该界面提供是“完善资料”或“立即进入”的选择。
                //session保存用户名和uid
                $_SESSION['username']=$insert['username'];
                $_SESSION['uid']=$insert['uid'];
                $synlogin=uc_user_synlogin($insert['uid']);
                $params['title']='注册成功';
                $params['referer']=$referer;
                $params['bigstr']='绑定邮箱';
                $params['bigstr_url']=fuel_url('bms/user_center/uc_index');
                $params['smallstr']='立即返回';
                $params['smallstr_url']=$referer;
                $params['synlogin']=$synlogin;
                $this->success_register($params);
            }
        }else{
            //Ucenter同步登入，用户不是第一次登入，直接跳转到回调界面
            //session保存用户名和uid
            $_SESSION['username']=$wj_arr['username'];
            $_SESSION['uid']=$wj_arr['olduid'];
            echo uc_user_synlogin($wj_arr['olduid'])."<script type='text/javascript'>
            setTimeout(window.location.href='{$referer}',1000);</script>";
        }
    }

    /**
     * 微博登入方法
     */
    function wb_login(){
        //调用微博登入接口
        session_start();
        include_once('wenjuan/modules/bms/assets/plugins/mobile/weibo/config.php');
        include_once('wenjuan/modules/bms/assets/plugins/mobile/weibo/saetv2.ex.class.php');
        $o = new SaeTOAuthV2( WB_AKEY , WB_SKEY );
        //接收回调路径
        $referer=empty($_GET['referer'])?INDEX_PAGE:base64_decode($_GET['referer']);
        $code_url = $o->getAuthorizeURL( WB_CALLBACK_URL."?referer=".base64_encode($referer));
        //跳转到授权页面
        header("location:{$code_url}");
    }
    
    function wb_go_back(){
        session_start();
        include_once('wenjuan/modules/bms/assets/plugins/mobile/weibo/config.php');
        include_once('wenjuan/modules/bms/assets/plugins/mobile/weibo/saetv2.ex.class.php');
        $o = new SaeTOAuthV2( WB_AKEY , WB_SKEY );
        if (isset($_REQUEST['code'])) {
            $keys = array();
            $keys['code'] = $_REQUEST['code'];
            $keys['redirect_uri'] = WB_CALLBACK_URL;
            try {
                $token = $o->getAccessToken( 'code', $keys ) ;
            } catch (OAuthException $e) {
            }
        }
        //接收回调路径
        $referer=empty($_GET['referer'])?INDEX_PAGE:base64_decode($_GET['referer']);
        if (!empty($token) && $token) {
            $_SESSION['token'] = $token;
            setcookie( 'weibojs_'.$o->client_id, http_build_query($token) );
            //获取用户信息，并注册
            $c = new SaeTClientV2( WB_AKEY , WB_SKEY , $_SESSION['token']['access_token'] );
            $ms  = $c->home_timeline(); // done
            $uid_get = $c->get_uid();
            $uid = $uid_get['uid'];
            $wb_arr = $c->show_user_by_id( $uid);//根据ID获取用户等基本信息
            //用户信息入库
            //判断用户是否使用WB登入过
            $wenjuan=$this->load->database('wenjuan',true);
            $wj_re=$wenjuan->select('userid,olduid,username,password,nickname,otherlogin')->from('member_user')->where('otherlogin','wb_'.$wb_arr['idstr'])->get();
            $wj_arr=$wj_re->row_array();
            //判断此otherlogin是否已存在，如果存在则用户不是第一次用WB登入，不用再写入用户信息
            //调用Ucenter接口
            include APPPATH.'config/ucenter.php';
            include APPPATH.'../uc_client/client.php';
            if(empty($wj_arr)){
                //用户第一次登入，执行第三方登入注册流程
                //形成新增信息数组
                $insert['username']=$wb_arr['screen_name'].time();
                $salt=mt_rand(100000,999999);
                $insert['salt']=$salt;
                $insert['password']=md5(md5('').$salt);
                $insert['email']='';
                $insert['regdate']=time();
                $insert['regip']=($_SERVER['REMOTE_ADDR'] == '::1'?'127.0.0.1':$_SERVER['REMOTE_ADDR']);
                //club数据库，数据入表pre_ucenter_members
                $club=$this->load->database('club',true);
                $arr_insert_re['club__pre_ucenter_members']=$club->insert('pre_ucenter_members',$insert);
                //获取pre_ucenter_members表的新增ID
                $uid=$club->insert_id();
                $insert['uid']=$uid;
                $insert['nickname']=$wb_arr['screen_name'];
                //club数据库，数据入表pre_common_member
                $arr_insert_re['club__pre_common_member']=$club->insert('pre_common_member',
                    array('uid'=>$insert['uid'],
                        'email'=>$insert['email'],
                        'username'=>$insert['username'],
                        'password'=>$insert['password']
                    ));
                //wenjuan数据库，数据入表member_user
                $wenjuan=$this->load->database('wenjuan',true);
                $arr_insert_re['wenjuan__member_user']=$wenjuan->insert('member_user',
                    array('olduid'=>$insert['uid'],
                        'username'=>$insert['username'],
                        'password'=>$insert['password'],
                        'nickname'=>$insert['nickname'],
                        'grade'=>1,
                        'coin91'=>0,
                        'cash'=>0,
                        'salt'=>$insert['salt'],
                        'otherlogin'=>'wb_'.$wb_arr['idstr'],
                        'logintype'=>'mobile',
                        'modifyflag'=>1
                    ));
                $userid=$wenjuan->insert_id();
                //wenjuan数据库，数据入表member_base
                //将性别转换为数字
                if(!empty($wb_arr['gender'])){
                    switch ($wb_arr['gender']){
                        case 'm':
                            $insert['gender']=1;
                            break;
                        case 'f':
                            $insert['gender']=2;
                            break;
                        default:
                            $insert['gender']=0;
                            break;
                    }
                }
                //获取province的数字代码
                $sql_pro="SELECT `id` FROM `common_district` WHERE `name` LIKE '{$wb_arr['location']}%';";
                $pro_re=$wenjuan->query($sql_pro);
                $pro_arr=$pro_re->row_array();
                $insert['province']=$pro_arr['id'];
            
                $arr_insert_re['wenjuan__member_base']=$wenjuan->insert('member_base',
                    array(
                        'userid'=>$userid,
                        'gender'=>$insert['gender'],
                        'provicne'=>$insert['province']
                    ));
                //uhome数据库，数据入表uchome_member
                $uhome=$this->load->database('uhome',true);
                $arr_insert_re['uhome__uchome_member']=$uhome->insert('uchome_member',
                    array('uid'=>$insert['uid'],
                        'username'=>$insert['username'],
                        'password'=>$insert['password']
                    ));
                //判断是否所有表都新增数据成功，如果有一个不成功，则把上一步所有表新增的数据删除，退到WB登入之前的界面
                foreach($arr_insert_re as $k=>$v){
                    if($v != true){
                        $is_del["$k"]='defeat';
                    }else{
                        $is_del["$k"]='success';
                    }
                }
                if(in_array('defeat',$is_del)){
                    //注册失败，重新注册
                    foreach($arr_insert_re as $k=>$v){
                        //只删除已经插入成功的表
                        if($v==true){
                            $arr_f=explode('__',$k);
                            if($arr_f['0'] == 'wenjuan'){
                                if($arr_f['1'] == 'member_user'){
                                    $del=$this->load->database($arr_f['0'],true);
                                    $del->delete("{$arr_f['1']}",array('olduid'=>$insert['uid']));
                                }else if($arr_f['1'] == 'member_base'){
                                    $del=$this->load->database($arr_f['0'],true);
                                    $del->delete("{$arr_f['1']}",array('userid'=>$insert['uid']));
                                }
                            }else{
                                $del=$this->load->database($arr_f['0'],true);
                                $del->delete("{$arr_f['1']}",array('uid'=>$insert['uid']));
                            }
                        }
                    } 
                    //进入注册失败，出错界面，点击能弹回WB登入之前的界面
                    $params['error_str']='登入失败，请重新登入！';
                    $params['referer']=$referer;
                    $params['right_url']=$referer;
                    $params['left_str']='返回首页';
                    $params['left_url']=INDEX_PAGE;
                    $params['right_str']='返回上一步';
                    $this->errors($params);
                }else{
                    //Ucenter同步登入
                    //用户第一次使用WB登入，需要跳转到注册成功页面，该界面提供是“完善资料”或“立即进入”的选择。
                    //session保存用户名和uid
                    $_SESSION['username']=$insert['username'];
                    $_SESSION['uid']=$insert['uid'];
                    $synlogin=uc_user_synlogin($insert['uid']);
                    $params['title']='注册成功';
                    $params['referer']=$referer;
                    $params['bigstr']='绑定邮箱';
                    $params['bigstr_url']=fuel_url('bms/user_center/uc_index');
                    $params['smallstr']='立即返回';
                    $params['smallstr_url']=$referer;
                    $params['synlogin']=$synlogin;
                    $this->success_register($params);
                }
            }else{
                //Ucenter同步登入，无需显示注册成功界面，直接跳转到回调界面
                //session保存用户名和uid
                $_SESSION['username']=$wj_arr['username'];
                $_SESSION['uid']=$wj_arr['olduid'];
                echo uc_user_synlogin($wj_arr['olduid'])."<script type='text/javascript'>
                setTimeout(window.location.href='{$referer}',1000);</script>";
            }
        } else {
            //WB授权失败，重新授权
            $params['error_str']='授权失败，请重新授权！';
            $params['referer']=fuel_url('bms/mobile/login');
            $params['right_url']=fuel_url('bms/mobile/login');
            $params['left_str']='返回登录';
            $params['left_url']=fuel_url('bms/mobile/login');
            $params['right_str']='返回上一步';
            $this->errors($params);
        }
    }
    
    /**
     * @param array $arr 调用“出错了”页面，通过传值显示不同提示和链接
     */
    function errors($arr=array()){
        $this->load->view('mobile/errors',$arr);
    }
}