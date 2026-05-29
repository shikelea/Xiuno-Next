<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1);

$system_group = array(0, 1, 2, 3, 4, 5, 6, 7, 101);

// hook admin_group_start.php

if(empty($action) || $action == 'list') {
	
	// hook admin_group_list_get_post.php
	
	if($method == 'GET') {
		
		// hook admin_group_list_get_start.php
		
		$header['title']        = lang('group_admin');
		$header['mobile_title'] = lang('group_admin');
		
		$maxgid = group_maxid();
		
		// hook admin_group_list_get_end.php
		
		include _include(ADMIN_PATH."view/htm/group_list.htm");
	
	} elseif($method == 'POST') {
		
		$gidarr = group_post_array('_gid');
		$namearr = group_post_array('name');
		$creditsfromarr = group_post_array('creditsfrom');
		$creditstoarr = group_post_array('creditsto');
		$deletegidarr = param('delete_gid', array(0));
		$arrlist = array();
		
		// hook admin_group_list_post_start.php
		group_post_array_keys_match($gidarr, array('name'=>$namearr, 'creditsfrom'=>$creditsfromarr, 'creditsto'=>$creditstoarr));
		$deleteids = group_delete_ids($deletegidarr, $grouplist);
		
		foreach ($gidarr as $k=>$v) {
			$k = intval($k);
			$k < 0 AND message(-1, 'Bad Request');
			$arr = array(
				'gid'=>$k,
				'name'=>array_value($namearr, $k),
				'creditsfrom'=>array_value($creditsfromarr, $k),
				'creditsto'=>array_value($creditstoarr, $k),
			);
			if(!isset($grouplist[$k])) {
				// 添加 / add
				group_create($arr);
			} else {
				// 编辑 / edit
				group_update($k, $arr);
			}
		}
		
		// 删除 / delete
		foreach($deleteids as $k) {
			if(in_array($k, $system_group)) continue;
			group_delete($k);
		}
		
		group_list_cache_delete();
		
		// hook admin_group_list_post_end.php
		
		message(0, lang('save_successfully'));
	}

} elseif($action == 'update') {
	
	$_gid = param(2, 0);
	$_group = group_read($_gid);
	empty($_group) AND message(-1, lang('group_not_exists'));
	
	// hook admin_group_update_get_post.php
	
	if($method == 'GET') {
		
		// hook admin_group_update_get_start.php
		
		$header['title']        = lang('group_admin');
		$header['mobile_title'] = lang('group_admin');
		
		$input = array();
		$input['name'] = form_text('name', $_group['name']);
		$input['creditsfrom'] = form_text('creditsfrom', $_group['creditsfrom']);
		$input['creditsto'] = form_text('creditsto', $_group['creditsto']);
		$input['allowread'] = form_checkbox('allowread', $_group['allowread']);
		$input['allowthread'] = form_checkbox('allowthread', $_group['allowthread'] && $_gid != 0);
		$input['allowpost'] = form_checkbox('allowpost', $_group['allowpost'] && $_gid != 0);
		$input['allowattach'] = form_checkbox('allowattach', $_group['allowattach'] && $_gid != 0);
		$input['allowdown'] = form_checkbox('allowdown', $_group['allowdown']);
		$input['allowtop'] = form_checkbox('allowtop', $_group['allowtop']);
		$input['allowupdate'] = form_checkbox('allowupdate', $_group['allowupdate']);
		$input['allowdelete'] = form_checkbox('allowdelete', $_group['allowdelete']);
		$input['allowmove'] = form_checkbox('allowmove', $_group['allowmove']);
		$input['allowbanuser'] = form_checkbox('allowbanuser', $_group['allowbanuser']);
		$input['allowdeleteuser'] = form_checkbox('allowdeleteuser', $_group['allowdeleteuser']);
		$input['allowviewip'] = form_checkbox('allowviewip', $_group['allowviewip']);
		
		// hook admin_group_update_get_end.php
		
		include _include(ADMIN_PATH."view/htm/group_update.htm");
	
	} elseif($method == 'POST') {	
		
		$name = param('name');
		$creditsfrom = param('creditsfrom');
		$creditsto = param('creditsto');
		$allowread = param('allowread', 0);
		$allowthread = param('allowthread', 0);
		$allowpost = param('allowpost', 0);
		$allowattach = param('allowattach', 0);
		$allowdown = param('allowdown', 0);
		
		// hook admin_group_update_post_start.php
		
		$arr = array (
			'name'       => $name,
			'creditsfrom' => $creditsfrom,
			'creditsto'   => $creditsto,
			'allowread'  => $allowread,
			'allowthread'  => $allowthread,
			'allowpost'  => $allowpost,
			'allowattach'  => $allowattach,
			'allowdown'  => $allowdown,
			
		);
		if($_gid >=1 && $_gid <= 5) {
			
			$allowtop = param('allowtop', 0);
			$allowupdate = param('allowupdate', 0);
			$allowdelete = param('allowdelete', 0);
			$allowmove = param('allowmove', 0);
			$allowbanuser = param('allowbanuser', 0);
			$allowdeleteuser = param('allowdeleteuser', 0);
			$allowviewip = param('allowviewip', 0);
			$arr += array(
				'allowtop'  => $allowtop,
				'allowupdate'  => $allowupdate,
				'allowdelete'  => $allowdelete,
				'allowmove'  => $allowmove,
				'allowbanuser'  => $allowbanuser,
				'allowdeleteuser'  => $allowdeleteuser,
				'allowviewip'  => $allowviewip
			);
		}
		group_update($_gid, $arr);
		
		// hook admin_group_update_post_end.php
		
		message(0, lang('edit_sucessfully'));	
	}
	
}

function group_post_array($key) {
	if(!isset($_POST[$key]) || !is_array($_POST[$key]) || empty($_POST[$key])) {
		message(-1, 'Bad Request');
	}
	return param($key, array());
}

function group_post_array_keys_match($keys, $arrays) {
	foreach($keys as $k=>$v) {
		foreach($arrays as $name=>$arr) {
			if(!isset($arr[$k])) message(-1, 'Bad Request');
		}
	}
}

function group_delete_ids($deletearr, $grouplist) {
	$ids = array();
	if(empty($deletearr) || !is_array($deletearr)) return $ids;
	foreach($deletearr as $gid=>$v) {
		$gid = intval($gid);
		if($gid < 0 || empty($v) || !isset($grouplist[$gid])) continue;
		$ids[$gid] = $gid;
	}
	return $ids;
}

// hook admin_group_start.php

?>
