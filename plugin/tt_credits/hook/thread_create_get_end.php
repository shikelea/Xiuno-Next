<?php exit;
$content_num = 0;
$content_num_type = 1;
if($group['allowsell']=="1") {
    $input['content_num_status'] = form_radio_yes_no('content_num_status', 0);
}
?>