<?php
!defined('DEBUG') AND exit('Access Denied.');
$action = param(3);
if(empty($action)){
    if($method == 'GET'){//设置页面
        include _include(APP_PATH.'plugin/tt_credits/setting.htm');
    } elseif($method=="POST") {
        $gettype = param('op');
        if($gettype=='0') {
            $username = param('username');$golds = param('golds_change');$rmbs = param('rmbs_change');$exps = param('exp_change');
            $db_array = array();
            $user = db_find_one('user', array('username' => $username));
            if (empty($username) || !$user) {
                message("-1", "NO_SUCH_USER");
                return;
            }
            $db_array['golds'] = $golds;$db_array['rmbs'] = $rmbs; $db_array['credits'] = $exps;
            db_update('user', array('username' => $username), $db_array);
            $user = db_find_one('user', array('username' => $username));
            $now_user_array = array("exp" => $user['credits'], "golds" => $user['golds'], "rmbs" => $user['rmbs']);
            message("0", $now_user_array);
        } elseif($gettype=='4') {
            $username = param('username');
            $user = db_find_one('user', array('username' => $username));
            $now_user_array = array("exp" => $user['credits'], "golds" => $user['golds'], "rmbs" => $user['rmbs']);
            message("0", $now_user_array);
        } elseif($gettype=='1') {
            $_uid = param('_useruid'); $_tid = param('_tid');
            db_insert('paylist',array('tid' => $_tid, 'uid' => $_uid, 'num'=>0,'credit_type'=>1, 'type' => 1, 'paytime' => time()));
            message("0","操作成功");
        } elseif ($gettype=='2') {
            $update_lists=param('sell_group',array(0)); $status=0;
            if(empty($update_lists))
                message(-1, '设置失败！');
            $tablepre = $db->tablepre;
            $sql = 'UPDATE '.$tablepre.'group SET allowsell="0";';
            foreach($update_lists as $k => $v)
                $sql .= 'UPDATE '.$tablepre.'group SET allowsell="1" WHERE gid="'.$k.'";';
            db_exec($sql);
            group_list_cache_delete();
            message(0, '设置成功！');
        } elseif ($gettype=='3') {
            $settings = array();
            foreach($g_credits_item_array as $set)
                $settings[$set] = param($set);
            $settings['limit']  = param('limit');
            $settings['min']  = param('min');
            $settings['convert_exchange']=param('convert_exchange')=='convert_exchange'?'1':'0';
            $settings['exchange_n']=param('exchange_n','1');
            $settings['exchange_c']=param('exchange_c','1');
            $settings['buy_push'] = param('buy_push')=='buy_push' ? '1' : '0';
            setting_set('tt_credits',$settings);
            message(0, '设置成功！');
        } elseif($gettype=='5') {
            $credit = param('d_credit');$gold=param('d_gold');$rmb=param('d_rmb');
            $tablepre = $db->tablepre;
            $sql="alter table {$tablepre}user alter column credits set default ".$credit.";";
            db_exec($sql);
            $sql="alter table {$tablepre}user alter column golds set default ".$gold.";";
            db_exec($sql);
            $sql="alter table {$tablepre}user alter column rmbs set default ".$rmb.";";
            db_exec($sql);
            message(0, '设置成功！');
        } elseif($gettype=='6') {
            $username = param('username','');
            if(empty($username)) {message(-1, 'ERROR');die();}
            $user = db_find_one('user',array('username'=>$username));
            user_update_group($user['uid']);
            message(0,'设置成功！');
        }
    }
}

?>