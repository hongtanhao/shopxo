<?php

namespace Api\Controller;

/**
 * 用户
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2017-03-02T22:48:35+0800
 */
class UserController extends CommonController
{
	/**
	 * [_initialize 前置操作-继承公共前置方法]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-02T22:48:35+0800
	 */
	public function _initialize()
	{
		// 调用父类前置方法
		parent::_initialize();

        // 是否ajax请求
        if(!IS_AJAX)
        {
            $this->error(L('common_unauthorized_access'));
        }
	}

	/**
	 * [Reg 用户注册-数据添加]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-07T00:08:36+0800
	 */
	public function Reg()
	{
		// 账户校验
		$this->UserRegAccountsCheck();

		// 验证码校验
		$verify_param = array(
				'key_prefix' => 'reg',
				'expire_time' => MyC('common_verify_expire_time')
			);
		$obj = new \Library\Sms($verify_param);

		// 是否已过期
		if(!$obj->CheckExpire())
		{
			$this->ajaxReturn(L('common_verify_expire'), -10);
		}
		// 是否正确
		if(!$obj->CheckCorrect(I('verify')))
		{
			$this->ajaxReturn(L('common_verify_error'), -11);
		}

		// 绑定
		$m = M('User');
        $type_field = I('app_type', 'alipay').'_openid';
		$data = array(
            $type_field         => I('alipay_openid'),
			'mobile'            => I('mobile'),
            'referrer'          => intval(I('referrer', 0)),
            'nickname'          => I('nickname'),
            'avatar'            => I('avatar'),
            'province'          => I('province'),
            'city'              => I('city'),
            'gender'            => intval(I('gender', 0)),
		);

        $where = [$type_field=>I('alipay_openid'), 'is_delete_time'=>0];
        $temp = $m->where($where)->find();
        if(empty($temp))
        {
            $data['add_time'] = time();
            $user_id = $m->add($data);
        } else {
            $data['upd_time'] = time();
            if($m->where($where)->save($data))
            {
                $user_id = $temp['id'];
            }
        }
		
		if(isset($user_id) && $user_id > 0)
		{
			// 清除验证码
			$obj->Remove();

			$this->ajaxReturn(L('common_bind_success'), 0, $m->find($user_id));
		} else {
			$this->ajaxReturn(L('common_bind_error'), -100);
		}
	}

	/**
	 * [RegVerifySend 用户注册-验证码发送]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-05T19:17:10+0800
	 */
	public function RegVerifySend()
	{
		// 账户校验
		$this->UserRegAccountsCheck();

		// 验证码公共基础参数
		$verify_param = array(
				'key_prefix' => 'reg',
				'expire_time' => MyC('common_verify_expire_time'),
				'time_interval'	=> MyC('common_verify_time_interval'),
			);

		// 发送验证码
		$obj = new \Library\Sms($verify_param);
		$code = GetNumberCode(6);
		$status = $obj->SendCode(I('mobile'), $code, MyC('home_sms_user_reg'));
		
		// 状态
		if($status)
		{
			$this->ajaxReturn(L('common_send_success'), 0);
		} else {
			$this->ajaxReturn(L('common_send_error').'['.$obj->error.']', -100);
		}
	}

	/**
	 * [UserRegAccountsCheck 用户注册账户校验]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-10T10:06:29+0800
	 */
	private function UserRegAccountsCheck()
	{
		// 参数
		$accounts = I('mobile');
		if(empty($accounts))
		{
			$this->ajaxReturn(L('common_param_error'), -1);
		}

		// 手机号码格式
		if(!CheckMobile($accounts))
		{
			$this->ajaxReturn(L('common_mobile_format_error'), -2);
		}

		// 手机号码是否已存在
		if($this->IsExistAccounts($accounts, 'mobile'))
		{
			$this->ajaxReturn(L('common_mobile_exist_error'), -3);
		}
	}

	/**
	 * [IsExistAccounts 账户是否存在]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2017-03-08T10:27:14+0800
	 * @param    [string] $accounts 	[账户名称]
	 * @param    [string] $field 		[字段名称]
	 * @return   [boolean] 				[存在true, 不存在false]
	 */
	private function IsExistAccounts($accounts, $field = 'mobile')
	{
		$id = M('User')->where(array('is_delete_time'=>0, $field=>$accounts))->getField('id');
		return !empty($id);
	}

	/**
     * [GetAlipayUserInfo 获取支付宝用户信息]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2017-09-23T21:52:49+0800
     */
    public function GetAlipayUserInfo()
    {
        $result = (new \Library\Alipay())->GetAlipayUserInfo(I('authcode'), C('alipay_mini_appid'));
        if($result === false)
        {
            $this->ajaxReturn('获取授权信息失败');
        } else {
        	$data = [
            	'alipay_openid'		=> $result['user_id'],
            	'nickname'			=> empty($result['nick_name']) ? '' : $result['nick_name'],
            	'avatar'			=> empty($result['avatar']) ? '' : $result['avatar'],
            	'gender'			=> empty($result['gender']) ? 0 : ($result['gender'] == 'm') ? 2 : 1,
            	'province'			=> empty($result['province']) ? '' : $result['province'],
            	'city'				=> empty($result['city']) ? '' : $result['city'],
                'referrer'          => intval(I('referrer', 0)),
            ];
            $m = M('User');
            $where = ['alipay_openid'=>$result['user_id'], 'is_delete_time'=>0];
        	$user = $m->where($where)->find();
        	if(!empty($user))
        	{
        		$data['upd_time'] = time();
        		if($m->where($where)->save($data) === false)
        		{
        			$this->ajaxReturn('用户更新失败');
        		}
                $data = $user;
        	}

            // 返回成功
        	$this->ajaxReturn('授权成功', 0, $data);
        }
    }

    /**
     * [ClientCenter 用户中心]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2018-05-21T15:21:52+0800
     */
    public function Center()
    {
        // 登录校验
        $this->Is_Login();

        // 初始化数据
        $result = array(
            'integral'                          => $this->user['integral'],
            'avatar'                            => $this->user['avatar'],
            'nickname'                          => $this->user['nickname'],
            'username'                          => $this->user['username'],
            'customer_service_tel'              => MyC('common_customer_service_tel'),
            'common_user_center_notice'         => MyC('common_user_center_notice'),
        );

        // 返回数据
        $this->ajaxReturn(L('common_operation_success'), 0, $result);
    }
}
?>