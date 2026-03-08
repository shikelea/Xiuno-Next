<?php exit;
$spay_url = url('thread-sPay-' . $tid);
if ($thread['content_buy_type'] == '3') {
	$thread['content_buy'] /= 100.0;
}
if ($route == 'mip') {
	$html_pay = <<<HTML
<strong>您好，本帖含有付费内容，请您点击下方"查看完整版网页"获取！</strong>
HTML;
} else {
	$lang_purchase = lang("purchase");
	$lang_have_pay = lang("have_pay");
	$lang_after_see = lang("after_see");
	$lang_credits_content_buy_type = lang('credits' . $thread['content_buy_type']);
	$html_pay = <<<HTML
<div class="card border border-warning">
	<div class="card-header text-warning bg-warning-subtle">
	<i class="la la-shopping-cart" aria-hidden="true"></i>
    {$conf['sitename']} - {$lang_purchase}
	</div>
	<div class="card-body">
		<div class="d-flex align-items-center justify-content-between">
			<p>
				{$lang_have_pay}
				<b>{$thread['content_buy']}{$lang_credits_content_buy_type}</b>
				{$lang_after_see}
			</p>
			<button role="button" data-target="tt_credits_purchase_modal" hx-on:click="toggleModal(event)" class="btn btn-warning">{$lang_purchase}</button>
		</div>
	</div>
</div>
HTML;
}

$preg_pay = preg_match_all('/\[ttPay\](.*?)\[\/ttPay\]/i', $first['message_fmt'], $array);
$first['purchased'] = '1';
$content_pay = db_find_one('paylist', array('tid' => $tid, 'uid' => $uid, 'type' => 1));
$is_set = 0;
if ($thread['content_buy']) {
	if ($preg_pay) {
		$array_count = count($array[0]);
		for ($i = 0; $i < $array_count; $i++) {
			$a = $array[0][$i];
			$lang_see_paid = lang("see_paid");
			$b = <<<HTML
<div class="card border border-success">
<div class="card-header text-success bg-success-subtle">
<i class="la la-shopping-cart" aria-hidden="true"></i>
    {$conf['sitename']} - {$lang_see_paid}
	<div class="float-end">
		<a href="{$spay_url}" data-modal-url="{$spay_url}" data-modal-title="查看购买记录" data-modal-size="lg" >查看购买记录</a>
	</div>
</div>
<div class="card-body">
	{$array[1][$i]}
</div>
</div>
HTML;
			if ($content_pay || $thread['uid'] == $uid) {
				$first['message_fmt'] = str_replace($a, $b, $first['message_fmt']);
			}
			// hook credits_verify_end.php
			else {
				$first['message_fmt'] = str_replace($a, $is_set == 0 ? $html_pay : '', $first['message_fmt']);
				$is_set = 1;
				$first['purchased'] = '0';
			}
		}
	}
} else {
	$first['message_fmt'] = str_replace('[ttPay]', '', $first['message_fmt']);
	$first['message_fmt'] = str_replace('[/ttPay]', '', $first['message_fmt']);
}
