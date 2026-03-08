<?php !defined('DEBUG') AND exit('Forbidden');include _include(ADMIN_PATH.'view/htm/header.inc.htm');?>
<div class="row"><div class="col-lg-10 mx-auto"><div class="card"><div class="card-body" >
<h3>添加(模拟)用户购买主题</h3><form action="<?php echo url("plugin-setting-tt_credits");?>" method="post" id="form">
<div class="input-group mb-3"><div class="input-group-prepend"><span class="input-group-text">用户UID</span></div><input class="form-control" name="_useruid" maxlength="10" value="1" width="30%"/></div>
<div class="input-group mb-3"><div class="input-group-prepend"><span class="input-group-text">主题帖tid</span></div><input class="form-control" name="_tid" maxlength="10" value="1" width="30%"/></div>
<button type="submit" class="btn btn-success btn-block" id="submit" data-loading-text="<?php echo lang('submiting');?>..."><?php echo lang('confirm');?></button>
</form></div></div><div class="card"><div class="card-body" ><h3>强制升级用户组</h3>
<div class="input-group mb-3"><div class="input-group-prepend"><span class="input-group-text">用户名</span></div><input class="form-control" id="up_username" maxlength="20" value="admin" width="30%"/></div>
<button type="button" class="btn btn-success btn-block" id="upgrade" data-loading-text="<?php echo lang('submiting');?>..."><?php echo lang('confirm');?></button>
</div></div></div></div>
<?php include _include(ADMIN_PATH.'view/htm/footer.inc.htm');?>
<script>
    var jform=$("#form");var jsubmit=$("#submit"); var jupgrade = $("#upgrade"); var jup_username=$("#up_username");
    jform.on('submit',function(){
        jform.reset(); jsubmit.button('loading');var postdata = jform.serialize();postdata+="&op=1";
        $.xpost(jform.attr('action'),postdata,function(code,message){
            if(code==0){
                setTimeout(function(){jsubmit.button('reset');
                },1000); return;
            } else {
                $.alert(message); jsubmit.button('reset');}
        });return false;});
    jupgrade.on('click',function(){
        jupgrade.button('loading'); var postdata = "username="+jup_username.val()+"&op=6";
        $.xpost(jform.attr('action'),postdata,function(){
            jupgrade.button('reset');
        });
    });
</script>