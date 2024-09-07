<?php
# 同步返回页面
# Required File Includes
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
include("../epay.php");

$gatewaymodule = "epay";
$GATEWAY = getGatewayVariables($gatewaymodule);

$url			= $GATEWAY['systemurl'];
$companyname 	= $GATEWAY['companyname'];
$currency		= $GATEWAY['currency'];

if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

$gatewayURL		= $GATEWAY['apiurl'];
$gatewayPID		= $GATEWAY['pid'];
$gatewayKEY		= $GATEWAY['key'];
$epay = new Epay($gatewayURL,$gatewayPID,$gatewayKEY);


$verify_result = $epay->verifyReturn();
if($verify_result) {//验证成功

	# Get Returned Variables
	$status = $_GET['trade_status'];
	$invoiceid = $_GET['out_trade_no']; //获取传递过来的订单号
	$transid = $_GET['trade_no'];       //获取传递过来的交易号
	$amount = $_GET['money'];       //获取传递过来的总价格
	$fee = 0;

	header("Location: ".$CONFIG["SystemURL"]."/viewinvoice.php?id=".$invoiceid);
	$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]);
	if (isset($GATEWAY["convertto"]) && strlen($GATEWAY["convertto"]) > 0) {
		$data = get_query_vals("tblinvoices", "userid,total", array("id" => $invoiceid));
		$total = $data["total"];
		$currencyArr = getCurrency($data["userid"]);
		$amount = convertCurrency($amount, $GATEWAY["convertto"], $currencyArr["id"]);
		if (round($amount, 1) == round($total, 1)) {
			$amount = $total;
		}
	}
	checkCbTransID($transid);
	addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule);
}
else {
	exit("入账失败，请联系管理员为您手工入账！<a href=\"".$CONFIG["SystemURL"]."/viewinvoice.php?id=".$invoiceid."\">返回账单页面</a>");
}
?>

