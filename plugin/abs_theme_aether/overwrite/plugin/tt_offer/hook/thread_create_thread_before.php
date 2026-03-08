<?php exit;
$offer_num = param('offer_num', 0);
$offer_status = param('offer_status','');
if ($offer_num < 0) {
    message(-1, '输入数字不合法！不能为负。');
    die();
}
if ($offer_status && $offer_num > 0) {
    $set_offer = setting_get('tt_offer');
    if ($user[get_credits_name_by_type($set_offer['credits_type'])] - $offer_num < 0) {
        message(-1, '您的余额不足，无法发表悬赏贴！');
        die();
    }
}
