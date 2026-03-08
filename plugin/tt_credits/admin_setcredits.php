<?php !defined('DEBUG') AND exit('Forbidden');include _include(ADMIN_PATH.'view/htm/header.inc.htm');
$set = setting_get('tt_credits'); $s_index=0; ?>
<div class="row"><div class="col-lg-10 mx-auto"><div class="card"><div class="card-body" ><h3>设置积分规则及兑换比例</h3>增加积分时，请不要输入+号，如果要扣积分输入-，不要输入符号、字母、无意义字符等，防止出错。<br>不科学地设置可能会出现用户刷分的情况，比如说下载附件时 经验+1 金币-1 ，会导致用户变相刷经验升级！请务必合理设置。<br><form action="<?php echo url("plugin-setting-tt_credits");?>" method="post" id="form"><table cellspacing="0" class="table"><tr><th>-</th><th>发表主题</th><th>发表回帖</th><th>下载附件</th><th>精华1</th><th>精华2</th><th>精华3</th></tr>
<tr><td>经验</td><?php for($a=0;$a<6;$a++) {echo '<td><input class="form-control" name="',$g_credits_item_array[$s_index],'" maxlength="8" value="',$set[$g_credits_item_array[$s_index]],'"/></td>'; $s_index++;}?></tr>
<tr><td>金币</td><?php for($a=0;$a<6;$a++) {echo '<td><input class="form-control" name="',$g_credits_item_array[$s_index],'" maxlength="8" value="',$set[$g_credits_item_array[$s_index]],'"/></td>'; $s_index++;}?></tr>
<tr><td>人民币(单位:分)</td><?php for($a=0;$a<6;$a++) {echo '<td><input class="form-control" name="',$g_credits_item_array[$s_index],'" maxlength="8" value="',$set[$g_credits_item_array[$s_index]],'"/></td>'; $s_index++;}?></tr></tr></table>每日通过发表主题/回帖加分的最高次数(减分不限,输入0为无限次数):<input class="form-control" name="limit" value="<?php echo $set['limit'];?>"/><br>最低兑换金额(单位:分)，不要输入小数点和字符，防止出错:<input class="form-control" name="min" value="<?php echo $set['min'];?>"/>
 正向兑换比例(人民币:分→金币):<input class="form-control" name="exchange_n" value="<?php echo $set['exchange_n'];?>"/><br>
 反向兑换比例(金币→人民币:分):<input class="form-control" name="exchange_c" value="<?php echo $set['exchange_c'];?>"/><br>
<input type="checkbox" name="convert_exchange" id="convert_exchange" value="convert_exchange" <?php if($set&&$set['convert_exchange']) echo 'checked'; ?>/><label for="convert_exchange">开启反向兑换(金币→人民币)</label><br>
<input type="checkbox" name="buy_push" id="buy_push" value="buy_push" <?php if($set&&$set['buy_push']) echo 'checked'; ?>/><label for="buy_push">购买后消息通知卖家</label>
<button type="submit" class="btn btn-success btn-block" id="submit" data-loading-text="<?php echo lang('submiting');?>..."><?php echo lang('confirm');?></button></form></div></div><div class="card"><div class="card-body" >
<h3>设置注册默认积分</h3>请不要输入特殊字符等，防止出错，人民币单位为分。该工具可能会出错，请谨慎使用！！！后期不建议更改，本工具暂时不支持查看当前状态，请从PMA或前台注册一个用户，查看是否生效。<br>
 <form action="<?php echo url("plugin-setting-tt_credits");?>" method="post" id="form2">
<div class="input-group mb-3"><div class="input-group-prepend"><span class="input-group-text">经验</span></div><input class="form-control" name="d_credit" value="0"/></div>
<div class="input-group mb-3"><div class="input-group-prepend"><span class="input-group-text">金币</span></div><input class="form-control" name="d_gold" value="0"/></div>
<div class="input-group mb-3"><div class="input-group-prepend"><span class="input-group-text">人民币（单位：分）</span></div><input class="form-control" name="d_rmb" value="0" id="d_rmb"/></div>
     合人民币 ¥ <span style="color:red;" id="show_rmb">0</span><br>
     <button type="submit" class="btn btn-success btn-block" id="submit2" data-loading-text="<?php echo lang('submiting');?>..."><?php echo lang('confirm');?></button></form></div></div>
<?php include _include(ADMIN_PATH.'view/htm/footer.inc.htm');?>
<script>
    var jform = $("#form");var jsubmit = $("#submit"); var jform2=$("#form2"); var jsubmit2=$("#submit2");
    jform.on('submit', function(){
        jform.reset();jsubmit.button('loading');
        var postdata = jform.serialize();postdata+= "&op=3";
        $.xpost(jform.attr('action'), postdata, function(code, message) {
            if(code == 0) {
                $.alert(message);setTimeout(function() {window.location.reload();jsubmit.button('reset');}, 1000);
                return; } else {$.alert(message);jsubmit.button('reset');}
        });return false;});
    jform2.on('submit', function(){
        jform2.reset();jsubmit2.button('loading');
        var postdata = jform2.serialize();postdata+= "&op=5";
        $.xpost(jform2.attr('action'), postdata, function(code, message) {
            if(code == 0) {$.alert(message); setTimeout(function() {window.location.reload();jsubmit2.button('reset');}, 1000);
                return;} else {$.alert(message);jsubmit2.button('reset');}
        });return false;});
    var jinput2=$("#d_rmb");var jrmbs=$("#show_rmb");
    jinput2.bind('input propertychange',function(){
        ONInput2(jinput2);
    });
    function ONInput2(input){
        input.val(input.val().replace(/[^0-9]/g, ''));
        jrmbs.text(input.val()==''?'0':input.val()/100.0);
    }
</script>