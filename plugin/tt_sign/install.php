<?php

!defined('DEBUG') AND exit('Forbidden');
$tablepre = $db->tablepre;

$sql="CREATE TABLE IF NOT EXISTS `{$tablepre}sign` (
  `qid` int(10) NOT NULL AUTO_INCREMENT,
  `uid` int(10) DEFAULT '0',
  `credits` int(10) DEFAULT '0',
  `golds` int(10) DEFAULT '0',
  `rmbs` int(10) DEFAULT '0',
  `time` int(20) DEFAULT '0',
  PRIMARY KEY (qid),					# 
	KEY (uid),						# 
	UNIQUE KEY (qid, uid)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";
db_exec($sql);
$settings = array('credits_from'=>'1','credits_to'=>'3','golds_from'=>'1','golds_to'=>'3','rmbs_from'=>'1','rmbs_to'=>'3','first_credits'=>'1','first_golds'=>'1','first_rmbs'=>'0','con_credits'=>'1','con_golds'=>'1','con_rmbs'=>'0','week_credits'=>'1','week_golds'=>'1','week_rmbs'=>'0','month_credits'=>'1','month_golds'=>'1','month_rmbs'=>'0');
setting_set('tt_sign',$settings);
?>