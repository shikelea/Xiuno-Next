<?php !defined('DEBUG') AND exit('Forbidden');include _include(ADMIN_PATH.'view/htm/header.inc.htm');
    $num_pid = db_count('user_pay');
	$page = param(1,1);
	$data =  db_find('user_pay',array(), array('time'=>-1), $page, $pagesize = 20);
    $pagination = pagination(url("credits_log-{page}"), $num_pid, $page, $pagesize);
    function username($uid){
        $r = db_find_one('user',array("uid"=>$uid));return $r['username'];
    }
?>
    <div class="row"><div class="col-lg-10 mx-auto"><div class="card"><div class="card-body" >
<table class="table"><tr><th>ID</th><th>用户</th><th>时间</th><th>状态</th><th>积分</th><th>记录</th></tr>
<?php foreach($data as $v){$c_type = $v['credit_type'];?>
<tr><td><?php echo $v['cid'];?></td><td><?php echo username($v['uid']);?></td><td><?php echo date('Y-m-d H:i:s',$v['time']); ?></td><td><?php echo $g_credits_type_array[$v['type']];?>,<?php echo $g_credits_status_array[$v['status']+1];?></td><td><?php echo $c_type=='3'?$v['num']/100.0:$v['num'],' '.lang('credits'.$c_type); ?></td><td><?php if(!empty($v['code'])) echo '记录:',$v['code']; ?></td>
</tr><?php }?></table>
<nav class="text-center"><ul class="pagination"><?php echo $pagination; ?></ul></nav>
</div></div></div></div>
<?php include _include(ADMIN_PATH.'view/htm/footer.inc.htm');?>