<?php !defined('DEBUG') AND exit('Forbidden');include _include(ADMIN_PATH.'view/htm/header.inc.htm');
    $num_pid = db_count('paylist');
	$page = param(1,1);
	$data =  db_find('paylist',array('type'=>1), array('paytime'=>-1), $page, $pagesize = 20);
    $pagination = pagination(url("credits_paylog-{page}"), $num_pid, $page, $pagesize);
    function subject($tid)
    {$r = db_find_one('thread',array("tid"=>$tid));
    return $r['subject'];}
    function username($uid)
    {$r = db_find_one('user',array("uid"=>$uid));
    return $r['username'];}
?>
    <div class="row"><div class="col-lg-10 mx-auto"><div class="card"><div class="card-body" ><table class="table"><th>帖子</th><th>用户</th><th>消费</th><th>时间</th>
<?php foreach($data as $v){?><tr>
    <td><a href="/<?php echo url("thread-{$v['tid']}");?>"><?php echo subject($v['tid'])?></a></td><td><a href="/<?php echo url("user-$v[uid]");?>" ><?php echo username($v['uid']); ?></a></td><td><?php echo $v['num'],lang('credits'.$v['type']);?></td><td><?php echo date('Y-m-d',$v['paytime']); ?></td>
</tr><?php }?></table>
<nav class="text-center"><ul class="pagination"><?php echo $pagination; ?></ul></nav>
</div></div></div></div>
<?php include _include(ADMIN_PATH.'view/htm/footer.inc.htm');?>