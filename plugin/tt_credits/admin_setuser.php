<?php !defined('DEBUG') AND exit('Forbidden');include _include(ADMIN_PATH.'view/htm/header.inc.htm');?>
 <div class="row"><div class="col-lg-10 mx-auto"><div class="card"><div class="card-body" >
<h3>设置可以发表收费贴的用户组</h3>
<form action="<?php echo url("plugin-setting-tt_credits");?>" method="post" id="form">
<table cellspacing="0" class="table"><tr><th>用户组名称</th><th>发表收费贴权限</th></tr>
<?php foreach($grouplist as $_group){?><tr>
<td> <?php echo $_group['name']; ?></td><td ><input type="checkbox" name="sell_group[<?php echo $_group['gid']?>]" <?php echo $_group['allowsell']=="1"?'checked="checked"':''?>/></td></tr><?php }?>
</table>
<button type="submit" class="btn btn-success btn-block" id="submit" data-loading-text="<?php echo lang('submiting');?>..."><?php echo lang('confirm');?></button>
</form></div></div></div></div>

<?php include _include(ADMIN_PATH.'view/htm/footer.inc.htm');?>
<script>
    var jform = $("#form");
    var jsubmit = $("#submit");
    jform.on('submit', function(){
        jform.reset();
        jsubmit.button('loading');
        var postdata = jform.serialize();
        postdata+= "&op=2";
        $.xpost(jform.attr('action'), postdata, function(code, message) {
            if(code == 0) {
                $.alert(message);
                setTimeout(function() {
                    window.location.reload();
                    jsubmit.button('reset');
                }, 1000);
                return;
            } else {
                $.alert(message);
                jsubmit.button('reset');
            }
        });
        return false;
    });
</script>