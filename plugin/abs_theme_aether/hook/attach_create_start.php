<?php exit;

// 检查是否为 multipart/form-data 类型
$is_multipart = false;
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    $is_multipart = true;
}

if ($is_multipart && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    $upload_errors = [
        0 => '上传成功',
        1 => '文件大小超过php.ini限制',
        2 => '文件大小超过HTML表单限制',
        3 => '文件只有部分被上传',
        4 => '没有文件被上传',
        6 => '找不到临时文件夹',
        7 => '文件写入失败',
        8 => 'PHP扩展停止文件上传'
    ];

    // 关键修复：在循环前只调用一次sess_restart()
    sess_restart();

    // 确保session数组存在
    if (!isset($_SESSION['tmp_files'])) {
        $_SESSION['tmp_files'] = array();
    }

    $attachments = []; // 存储成功的附件

    // 判断是单文件还是多文件
    $is_multi = is_array($file['name']);

    // 统一处理：都当作数组处理
    $file_count = $is_multi ? count($file['name']) : 1;

    for ($i = 0; $i < $file_count; $i++) {
        // 获取当前文件信息
        $current_name = $is_multi ? $file['name'][$i] : $file['name'];
        $current_tmp_name = $is_multi ? $file['tmp_name'][$i] : $file['tmp_name'];
        $current_error = $is_multi ? $file['error'][$i] : $file['error'];
        $current_size = $is_multi ? $file['size'][$i] : $file['size'];

        // 检查上传错误
        if ($current_error !== UPLOAD_ERR_OK) {
            // 可以记录错误但不中断其他文件
            continue;
        }

        // 检查文件大小
        if ($current_size > 20480000) {
            continue;
        }

        // 读取文件内容
        $file_data = file_get_contents($current_tmp_name);
        $file_name = $current_name;

        // ========== 文件处理逻辑 ==========
        empty($group['allowattach']) and $gid != 1 and message(-1, '您无权上传');
        empty($file_data) and message(-1, lang('data_is_empty'));

        $ext = file_ext($file_name, 7);
        $filetypes = include APP_PATH . 'conf/attach.conf.php';
        !in_array($ext, $filetypes['all']) and $ext = '_' . $ext;

        $tmpanme = $uid . '_' . xn_rand(15) . '.' . $ext;
        $tmpfile = $conf['upload_path'] . 'tmp/' . $tmpanme;
        $tmpurl = $conf['upload_url'] . 'tmp/' . $tmpanme;

        $filetype = attach_type($file_name, $filetypes);

        file_put_contents($tmpfile, $file_data) or message(-1, lang('write_to_file_failed'));

        // 关键修复：计算当前文件在session中的索引
        $n = count($_SESSION['tmp_files']);
        $filesize = filesize($tmpfile);

        $attach = array(
            'url' => $tmpurl,
            'path' => $tmpfile,
            'orgfilename' => $file_name,
            'filetype' => $filetype,
            'filesize' => $filesize,
            'width' => $width,
            'height' => $height,
            'isimage' => $is_image,
            'downloads' => 0,
            'aid' => '_' . $n
        );

        // 添加到session（累积）
        $_SESSION['tmp_files'][] = $attach;

        // 准备返回给前端的附件信息
        $attach_return = $attach;
        unset($attach_return['path']);
        $attachments[] = $attach_return;
    }

    // 如果有HTMX请求，返回HTML
    if ($IS_HTMX && !empty($attachments)) {
        echo aether_post_file_list_html($attachments, true);
        die;
    }

    // 返回所有结果
    message(0, ['attachments' => $attachments]);
    die;
}
