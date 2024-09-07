<?php
function epay_config() {
    $configarray = [
        "FriendlyName" => [
            "Type" => "System", 
            "Value" => "彩虹易支付"
        ],
        "apiurl" => [
            "FriendlyName" => "接口地址", 
            "Type" => "text", 
            "Size" => "128"
        ],
        "pid" => [
            "FriendlyName" => "商户ID", 
            "Type" => "text", 
            "Size" => "10"
        ],
        "key" => [
            "FriendlyName" => "商户密钥", 
            "Type" => "text", 
            "Size" => "32"
        ],
        "apitype" => [
            "FriendlyName" => "指定支付接口",
            "Type" => "dropdown",
            "Size" => "32",
            "Options" => [
                "epay" => "[默认]聚合支付",
                "alipay" => "支付宝支付",
                "bank" => "银联支付",
                "wxpay" => "微信支付",
                "qqpay" => "QQ支付",
                "jdpay" => "京东支付",
                "paypal" => "PayPal",
                "usdt" => "USDT(请确保插件已安装)"
            ]
        ]
    ];
    
    return $configarray;
}

function epay_link($params) {
	global $_LANG;
        # Gateway Specific Variables
        $gatewayURL = $params['apiurl'];
        $gatewayPID = $params['pid'];
        $gatewayKEY = $params['key'];
        $gatewayTYPE = $params['apitype'];
        # Invoice Variables
        $invoiceid = $params['invoiceid'];
        $description = $params["description"];
        $amount = $params['amount']; # Format: ##.##

        # System Variables
        $companyname            = $params['companyname'];
        $systemurl                      = $params['systemurl'];
        $return_url                     = $systemurl."/modules/gateways/epay/return.php";
        $notify_url                     = $systemurl."/modules/gateways/epay/notify.php";
    $parameter = [
        "pid" => trim($gatewayPID),
        "out_trade_no" => $invoiceid,
        "name" => $companyname . $_LANG['invoicenumber']. $invoiceid,
        "money" => $amount,
        "notify_url" => $notify_url,
        "return_url" => $return_url,
        "sitename" => $companyname,
    ];

	if ($gatewayTYPE !== 'epay') { $parameter['type'] = $gatewayTYPE;}

        $epay = new Epay($gatewayURL,$gatewayPID,$gatewayKEY);

        if (strpos($_SERVER['PHP_SELF'], 'viewinvoice')!==false) {
                $html_text = $epay->buildRequestForm($parameter,$params['langpaynow'] , false);
                return $html_text;
        } else {
                $html_text = $epay->buildRequestForm($parameter,  $_LANG['ajaxcartcheckout'], true);
                return $html_text;
        }
} 

function epay_refund($params)
{
        $gatewayURL = $params['apiurl'];
        $gatewayPID = $params['pid'];
        $gatewayKEY = $params['key'];
        $epay = new Epay($gatewayURL,$gatewayPID,$gatewayKEY);

    try {
        $responseData = $epay->refund( $params['transid'] , $params['amount']);
        return [
            'status' => ($responseData->code === '1') ? 'success' : 'error',
            'rawdata' => $responseData,
            'transid' => $params['transid'],
            'fees' => $params['amount'],
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'rawdata' => $e->getMessage(),
            'transid' => $params['transid'],
            'fees' => $params['amount'],
        ];
    }
}



class Epay {
	/**
	 * 支付接口配置信息
	 * @var array
	 */
	private $payment;
	private $gateway_url;
	public function __construct($apiurl, $pid, $key) {
	    $this->payment = [
	        'pid' => $pid,
	        'key' => $key,
	        'sign_type' => 'MD5'
	    ];
	    $this->gateway_url = $apiurl . 'submit.php';
	    $this->mapi_url = $apiurl . 'mapi.php';
            $this->api_url = $apiurl . 'api.php';
	}


	/**
	 * 建立请求，以表单HTML形式构造（默认）
	 * @param $para_temp 请求参数数组
	 *
	 */
	public function buildRequestForm($para_temp, $button_name='正在跳转', $isauto = false) {
		//待请求参数数组
		$para = $this->buildRequestPara($para_temp);

		$sHtml = "<form id='epaysubmit' name=epaysubmit' action='".$this->gateway_url."' method='POST'>";
		foreach ($para as $key=>$val) {
            $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }

        $sHtml .= "<input type='submit' value='".$button_name."'></form>";
		
		if($isauto) $sHtml .= "<script>document.forms['epaysubmit'].submit();</script>";
		
		return $sHtml;
	}
	/**
     * 建立请求，以跳转链接
     * @param $para_temp 请求参数数组
     * @return 跳转链接
     */
	public function buildRequestUrl($para_temp) {
		//待请求参数数组
		$para = $this->buildRequestPara($para_temp);
		
		//把参数组中所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
		$request_data = $this->createLinkstringUrlencode($para);

		$url = $this->gateway_url . '?' . $request_data;
		
		return $url;
	}

	/**
	 * 通知地址验证
	 * @return bool
	 */
	public function verifyNotify() {
		//计算得出通知验证结果
		$Notify = $this->getSignVeryfy($_GET,$_GET['sign']);
		if($Notify) {//验证成功
			if($_GET['trade_status']=='TRADE_SUCCESS'){
				return true;
			}else{
		    	return false;
			}
		}else {
		   //验证失败
		    return false;
		}
	}
	/**
	 * 返回地址验证
	 * @return bool
	 */
	public function verifyReturn() {
		$Notify = $this->getSignVeryfy($_GET,$_GET['sign']);
		if($Notify) {//验证成功
			if($_GET['trade_status']=='TRADE_SUCCESS'){
				return true;
			}else{
				return false;
			}
		}
		else {
		    //验证失败
		    return false;
		}
	}

	/**
     * 获取返回时的签名验证结果
     * @param $para_temp 通知返回来的参数数组
     * @param $sign 返回的签名结果
     * @return 签名验证结果
     */
	private function getSignVeryfy($para_temp, $sign) {
		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($para_temp);
		
		//对待签名参数数组排序
		$para_sort = $this->argSort($para_filter);
		
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = $this->createLinkstring($para_sort);
		
		$isSgin = false;
		$isSgin = $this->md5Verify($prestr, $sign, $this->payment['key']);
	
		return $isSgin;
	}
	/**
	 * 验证签名
	 * @param $prestr 需要签名的字符串
	 * @param $sign 签名结果
	 * @param $key 私钥
	 * return 签名结果
	 */
	private function md5Verify($prestr, $sign, $key) {
		$prestr = $prestr . $key;
		$mysgin = md5($prestr);
		if($mysgin == $sign) {
			return true;
		}else {
			return false;
		}
	}

	/**
	 * 生成要请求给云闪付的参数数组
	 * @param $para_temp 请求前的参数数组
	 * @return 要请求的参数数组
	 */
	private function buildRequestPara($para_temp) {
		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($para_temp);
		//对待签名参数数组排序
		$para_sort = $this->argSort($para_filter);
		//生成签名结果
		$mysign = $this->buildRequestMysign($para_sort,$this->payment['key']);
		
		//签名结果与签名方式加入请求提交参数组中
		$para_sort['sign'] = $mysign;
		$para_sort['sign_type'] = strtoupper(trim($this->payment['sign_type']));
		
		return $para_sort;
	}
	/**
	 * 除去数组中的空值和签名参数
	 * @param $para 签名参数组
	 * return 去掉空值与签名参数后的新签名参数组
	 */
	private function paraFilter($para) {
		$para_filter = array();
		foreach ($para as $key=>$val) {
			if($key == "sign" || $key == "sign_type" || $val == "")continue;
			else	$para_filter[$key] = $para[$key];
		}
		return $para_filter;
	}
	/**
	 * 对数组排序
	 * @param $para 排序前的数组
	 * return 排序后的数组
	 */
	private function argSort($para) {
		ksort($para);
		reset($para);
		return $para;
	}
	/**
	 * 生成签名结果
	 * @param $para_sort 已排序要签名的数组
	 * return 签名结果字符串
	 */
	private function buildRequestMysign($para_sort,$key) {
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = $this->createLinkstring($para_sort);
		$mysign = $this->md5Sign($prestr, $key);
		return $mysign;
	}
	private function md5Sign($prestr, $key) {
		$prestr = $prestr . $key;
		return md5($prestr);
	}
	/**
	 * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	private function createLinkstring($para) {
		$arg  = "";
		foreach ($para as $key=>$val) {
			$arg.=$key."=".$val."&";
		}
		//去掉最后一个&字符
		$arg = substr($arg,0,-1);
		
		return $arg;
	}

	/**
	 * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	private function createLinkstringUrlencode($para) {
		$arg  = "";
		foreach ($para as $key=>$val) {
			$arg.=$key."=".urlencode($val)."&";
		}
		//去掉最后一个&字符
		$arg = substr($arg,0,-1);

		return $arg;
	}

    public function refund($trade_no, $money){
        $url = $this->api_url.'?act=refund';
        $post = 'pid=' . $this->payment['pid'] . '&key=' . $this->payment['key'] . '&trade_no=' . $trade_no . '&money=' . $money;
        $response = $this->getHttpResponse($url, $post);
        return $response;
    }

	// 请求外部资源
	private function getHttpResponse($url, $post = false, $timeout = 10){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$httpheader[] = "Accept: */*";
		$httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
		$httpheader[] = "Connection: close";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if($post){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
}
?>
