<?php exit;
$content_num = $thread['content_buy'];
$content_type = $thread['content_buy_type']=='0'?'1': $thread['content_buy_type'];
if($group['allowsell']=="1") {
    $input['content_num_status'] = form_radio_yes_no('content_num_status', $content_num > 0 ? 1 : 0);
}
?>