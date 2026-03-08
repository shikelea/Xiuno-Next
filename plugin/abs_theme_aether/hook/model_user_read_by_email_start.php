
$user = db_find_one('user', array('email'=>$email));
if(empty($user)) {
    return null; // 立刻返回null来解决报错
}

