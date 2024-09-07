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

if (!$GATEWAY["type"]) die("Module Not Activated"); 

$gatewayURL		= $GATEWAY['apiurl'];
$gatewayPID		= $GATEWAY['pid'];
$gatewayKEY		= $GATEWAY['key'];
$epay = new Epay($gatewayURL,$gatewayPID,$gatewayKEY);


$verify_result = $epay->verifyNotify();
if($verify_result) {//验证成功
	# Get Returned Variables
	$status = $_GET['trade_status'];
	$invoiceid = $_GET['out_trade_no']; //获取传递过来的订单号
	$transid = $_GET['trade_no'];       //获取传递过来的交易号
	$amount = $_GET['money'];       //获取传递过来的总价格
	$fee = 0;

$redirect = $url . "/viewinvoice.php?paymentsuccess=true&id=". $invoiceid ;
echo "<html><head>";
echo "<meta http-equiv='refresh' content='0;url=$redirect'>";
echo "<title>Redirecting...</title>";
echo "</head><body>";
echo "Redirecting to invoice page";
echo "</body></html>";

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
	logTransaction($GATEWAY["name"],$_GET,"success");

}else {
	echo "fail";
	logTransaction($GATEWAY["name"],$_GET,"fail");
}
?>
