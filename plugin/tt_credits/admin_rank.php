<?php !defined('DEBUG') AND exit('Forbidden');include _include(ADMIN_PATH.'view/htm/header.inc.htm');
    $num_pid = db_count('user');
	$page = param(1,1);
	$type = param('type','rmbs');
	$data =  db_find('user',array(), array($type=>-1), $page, $pagesize = 20);
    $pagination = pagination(url("credits_rank-{page}").'?type='.$type, $num_pid, $page, $pagesize);
?>
 <div class="row"><div class="col-lg-10 mx-auto"><div class="card"><div class="card-body" >
 <a href="<?php echo url("credits_rank-$page").'?type=credits';?>">按经验排序</a>&nbsp;&nbsp;<a href="<?php echo url("credits_rank-$page").'?type=golds';?>">按金币排序</a>&nbsp;&nbsp;<a href="<?php echo url("credits_rank-$page").'?type=rmbs';?>">按人民币排序</a>
 </div></div><div class="card"><div class="card-body"><table class="table"><th>用户</th><th>经验</th><th>金币</th><th>人民币</th>
<?php foreach($data as $v){?><tr><td><a href="/<?php echo url("user-$v[uid]")?>" ><?php echo$v['username'];?></a></td><td><?php echo $v['credits'];?></td><td><?php echo $v['golds'];?></td><td><?php echo $v['rmbs']/100.0;?></td></tr><?php }?></table>
<nav class="text-center"><ul class="pagination"><?php echo $pagination; ?></ul></nav>
</div></div></div></div>
<?php include _include(ADMIN_PATH.'view/htm/footer.inc.htm');?>