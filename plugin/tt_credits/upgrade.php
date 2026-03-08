<?php !defined('DEBUG') AND exit('Forbidden');
$tablepre = $db->tablepre;
//20180720 ADD VIP
$sql = "ALTER TABLE {$tablepre}user ADD COLUMN vip_end INT(12) DEFAULT '0'";
db_exec($sql);
//20180727-28 ADD SETTING ITEM
$set = setting_get('tt_credits');
$set['exchange_n']=1; $set['exchange_c']=1;
$set['digest1_rmb']='1';$set['digest2_rmb']='2';$set['digest3_rmb']='3';
$set['digest1_gold']='1';$set['digest2_gold']='2';$set['digest3_gold']='3';
$set['digest1_exp']='1';$set['digest2_exp']='2';$set['digest3_exp']='3';
$set['buy_push'] = '1';
setting_set('tt_credits',$set);


?>