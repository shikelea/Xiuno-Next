<?php
/* 【开始】form_func 扩展 */
// IDIOTS DON’T TOUCH WHAT YOU DON’T UNDERSTAND!!!!

/**
 * 可自定义类型的输入框（HTML5）
 * @param string $name 设置项的name
 * @param string $value 预先填充的内容
 * @param int $width 文本框的宽度（最好别用），false是不定义宽度，单位像素
 * @param string $type 文本框的类型，如color、number、range等
 * @param string $placeholder 若未填写文字时的占位文字
 * @return string 对应的HTML代码
 */
function form_custom_type($name, $value, $width = FALSE, $type = 'text', $placeholder = '') {
	$style = '';
	if ($width !== FALSE) {
		is_numeric($width) and $width .= 'px';
		$style = " style=\"width: $width\"";
	}
	$s = "<input type=\"$type\" name=\"$name\" id=\"$name\" placeholder=\"$placeholder\" value=\"$value\" class=\"form-control\"$style />";
	return $s;
}

/**
 * 颜色框输入框（HTML5）
 * @param string $name 设置项的name
 * @param string $value 预先填充的内容
 * @param string $value_default 默认值
 * @return string 对应的HTML代码
 */
function form_color($name, $value, $value_default = "#888888") {
	$s = '<label for="' . $name . '" class="input-group">'
		. '<input type="color" name="' . $name . '" id="' . $name . '" value="' . $value . '" title="' . $value . '" class="form-control" style="background-color:' . $value . ';flex: 0 1 5em" onchange="this.style.backgroundColor = this.value;this.setAttribute(\'title\',this.value)" data-toggle="tooltip" />'
		. '<span class="input-group-append input-group-text">' . lang("Select Color") . '</span>'
		. '<button type="button" class="btn btn-secondary" hx-on:click="document.getElementById(\'' . $name . '\').value = this.getAttribute(\'data-value-original\');document.getElementById(\'' . $name . '\').style.backgroundColor = this.getAttribute(\'data-value-original\');" data-value-original="' . $value_default . '">' . lang('Reset') . '</button>'
		. '</label>';
	return $s;
}

/**
 * 滑动输入条（又叫滑杆、推子、范围输入滑块、slider）（数字框）
 * @param string $name 设置项的name
 * @param string $value 预先填充的内容
 * @param int $width 滑动条的宽度（最好别用），false是不定义宽度，单位像素
 * @param int|float $min 滑动条的最小值（左侧数值）
 * @param int|float $max 滑动条的最大值（右侧数值）
 * @param int|float $step 滑动条的数字间隔（一格增减多少）（如果 step="3"，则合法数字是 -3,0,3,6，以此类推）
 * @param bool $label 在滑动条旁显示数字显示（最小值和最大值）
 * @param bool $vertical 垂直滑动条
 * @return string 对应的HTML代码
 */
function form_range($name, $value, $width = FALSE, $min = 0, $max = 10, $step = 1, $label = FALSE, $vertical = FALSE) {
	$style = '';
	$verticaladd = $vertical ? ' orient="vertical"' : '';
	if ($width !== FALSE) {
		is_numeric($width) and $width .= 'px';
		$style = " style=\"width: $width\"";
	}

	$s = "<input type='range' name=\"$name\" id=\"$name\" min=\"$min\" max=\"$max\" step=\"$step\" value=\"$value\" class=\"form-control-range form-range\" $verticaladd $style />";
	if ($label) {
		$s .= "<div class='clearfix small text-secondary range-label' $verticaladd ><span class='float-left'>$min(+$step)</span><span class='float-right'>$max</span></div>";
	}
	return $s;
}

/**
 * 数字输入框（HTML5）
 * @param string $name 设置项的name
 * @param int|float $value 预先填充的内容
 * @param int $width 数字框的宽度（最好别用），false是不定义宽度，单位像素
 * @param int|float $min 数字框的最小值（左侧数值）
 * @param int|float $max 数字框的最大值（右侧数值）
 * @param int|float $step 数字框的数字间隔（一格增减多少）（如果 step="3"，则合法数字是 -3,0,3,6，以此类推）
 * @param string $placeholder 若未填写文字时的占位文字
 * @return string 对应的HTML代码
 */
function form_number($name, $value, $width = FALSE, $min = 0, $max = 100, $step = 1, $placeholder = '') {
	$style = '';
	if ($width !== FALSE) {
		is_numeric($width) and $width .= 'px';
		$style = " style=\"width: $width\"";
	}

	$s = "<input type='number' name=\"$name\" id=\"$name\" min=\"$min\" max=\"$max\" step=\"$step\" value=\"$value\" class=\"form-control\" placeholder=\"$placeholder\" $style />";

	return $s;
}

/**
 * 多个颜色单选框（选中“红或绿或蓝”）
 * @param string $name 设置项的name
 * @param array $arr 选项数组，使用“键-值”模式：键是颜色，值是对外显示的标签
 * @param mixed $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_color_select($name, $arr, $checked = 0) {

	empty($arr) && $arr = array(
		'#000000' => lang('no'),
		'#ffffff' => lang('yes')
	);
	$s = '<ol class="list-unstyled">';

	foreach ((array)$arr as $k => $v) {
		$add = $k == $checked ? ' checked="checked"' : '';
		$s .= "<li><label class=\"custom-input custom-radio badge d-block my-1 p-0 w-25 text-left\" style=\"background:$k\"><span class='badge badge-light text-gray p-1'><input type=\"radio\" name=\"$name\" value=\"$k\"$add /> $v</span></label></li>" . PHP_EOL;
	}
	$s .= '</ol>';

	return $s;
}

/**
 * 配色方案单选框（选中“红橙黄或绿蓝紫”）
 * @param string $name 设置项的name
 * @param array $arr 选项数组，使用“键-值”模式：键是配色方案的ID，值是数组，分为对外显示的标签和展示用颜色
 * @param mixed $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_color_scheme_select($name, $arr, $checked = 0) {

	empty($arr) && $arr = array(
		'dark' => array(
			'label' => 'Dark',
			'colors' => array('#000000')
		),
		'light' => array(
			'label' => 'Light',
			'colors' => array('#ffffff')
		)
	);
	$s = '<ol class="list-unstyled color-scheme">';

	foreach ((array)$arr as $k => $v) {
		$add = $k == $checked ? ' checked="checked"' : '';
		$s .= "<li><label class=\"custom-input custom-radio d-flex\" ><span><input type=\"radio\" name=\"$name\" value=\"$k\"$add /> " . $v['label'] . "</span><span class=\"badge-group d-inline-flex color-scheme-item ml-2\">";
		foreach ($v['colors'] as $color_palette_item) {
			$s .= "<span class=\"badge flex-grow-1\" style=\"background:$color_palette_item\" data-toggle='tooltip' title=\"$color_palette_item\">&emsp;</span>";
		}
		$s .= "</span></label></li>" . PHP_EOL;
	}
	$s .= '</ol>';
	return $s;
}

/**
 * 多选下拉框的选择（甲、乙、丙）
 * @param array $arr 选项数组，使用“键-值”模式：键是value，值是对外显示的标签
 * @param array $checked 预先选中的选项数组
 * @return string 对应的HTML代码
 */
function form_options_multiple($arr, $checked = array()) {
	$s = '';
	$checked_imploded = implode(',', $checked);
	foreach ((array)$arr as $k => $v) {
		$add = count(explode($k, $checked_imploded)) > 1 ? ' selected="selected"' : '';
		$s .= "<option value=\"$k\"$add>$v</option> " . PHP_EOL;
	}
	return $s;
}

/**
 * 多选下拉框（选中“甲和乙和丙”）
 * @param string $name 设置项的name
 * @param array $arr 选项数组，使用“键-值”模式：键是value，值是对外显示的标签
 * @param array $checked 预先选中的选项数组
 * @param bool|string $id 该控件的ID，true为该控件的name，否则是自定义的ID
 * @return string 对应的HTML代码
 */
function form_select_multiple($name, $arr, $checked = array(), $id = TRUE) {
	if (empty($arr)) return '';

	$idadd = $id === TRUE ? "id=\"$name\"" : ($id ? "id=\"$id\"" : '');
	$sizeadd = count($checked) == 0 ? 1 : count($checked);

	$s = "<select name='" . $name . "[]' class=\"custom-select\" multiple=\"multiple\" size=\"\" $idadd> " . PHP_EOL;
	$s .= form_options_multiple($arr, $checked);
	$s .= "</select> " . PHP_EOL;

	return $s;
}

if (!function_exists('form_checkbox_multiple')) {
/**
 * 真正的多个复选框（选中“甲和乙和丙”）
 *
 * @param string $name 设置项的name
 * @param array $arr 选项数组，使用“键-值”模式：键是value，值是对外显示的标签
 * @param array $checked 预先选中的选项数组
 * @return string 对应的HTML代码
 */
function form_checkbox_multiple($name, $arr, $checked = array()) {
	$s = '';
	foreach ($arr as $value => $text) {
		$ischecked = in_array($value, $checked);
		$add = $ischecked ? ' checked="checked"' : '';
		$s .= '<label class="custom-input custom-checkbox form-check mr-4">'
			. '<input type="checkbox" name="' . $name . '[]" value="' . $value . '"' . $add . ' class="form-check-input" />'
			. $text
			. '</label>';
	}
	return $s;
}
}

/**
 * 多个单选框——开关按钮风格（选中“甲或乙或丙”）
 * @param string $name 设置项的name
 * @param array $arr 选项数组，使用“键-值”模式：键是value，值是对外显示的标签
 * @param mixed $checked 预先选中的选项
 * @param string $color 开关按钮的颜色
 * @return string 对应的HTML代码
 */
function form_radio_toggle($name, $arr, $checked = 0, $color = "primary") {
	empty($arr) && $arr = array(lang('no'), lang('yes'));
	$s = '';
	$s .= '<div class="btn-group btn-group-toggle" data-toggle="buttons">';
	foreach ((array)$arr as $k => $v) {
		$add = $k == $checked ? ' checked="checked"' : '';
		$activeadd = $k == $checked ? ' active' : '';
		$s .= '<label class="btn btn-' . $color . ' ' . $activeadd . '"><input type="radio" value="' . $k . '" name="' . $name . '" id="' . $name . '_' . $k . '"' . $add . ' />' . $v . '</label>' . PHP_EOL;
	}
	$s .= '</div>';
	return $s;
}
if (!function_exists('form_radio_image')) {

	/**
	 * 多个单选框——图片风格（选中“甲或乙或丙”）
	 * @param string $name 设置项的name
	 * @param array $arr 选项数组，使用“键-值”模式：键是value，值是对外显示的标签
	 * @param string $color 开关按钮的颜色
	 * @param mixed $checked 预先选中的选项
	 * @return string 对应的HTML代码
	 */
	function form_radio_image($name, $arr, $checked = 0, $color = "primary") {
		empty($arr) && $arr = array(lang('no'), lang('yes'));
		$s = '';
		$s .= '<div class="btn-group btn-group-toggle" data-toggle="buttons">';
		foreach ((array)$arr as $k => $v) {
			$add = $k == $checked ? ' checked="checked"' : '';
			$activeadd = $k == $checked ? ' active' : '';
			$s .= '<label class="btn btn-' . $color . ' ' . $activeadd . '"><input type="radio" value="' . $k . '" name="' . $name . '" id="' . $name . '_' . $k . '"' . $add . ' />'
				. '<img class="rounded" src="'
				. $v['url']
				. '" /><br>'
				. $v['label']
				. '</label>'
				. PHP_EOL;
		}
		$s .= '</div>';
		return $s;
	}
}
/** 
 * 切换开关（是或否）
 * 是form_radio_toggle的简写，只提供“是”“否”两种选择。
 * @param string $name 设置项的name
 * @param int|bool $checked 是否预先为“是”
 * @return string 对应的HTML代码
 */
function form_radio_toggle_yes_no($name, $checked = 0) {
	$checked = intval($checked);
	return form_radio_toggle($name, array(1 => lang('yes'), 0 => lang('no')), $checked, 'outline-primary');
}

/**
 * HTML文本框——所见即所得 (TinyMCE)
 * @param string $name 设置项的name
 * @param string $value 预先填充的内容
 * @param int $width 文本框的宽度（最好别用），false是不定义宽度，单位像素
 * @param int $height 文本框的高度，false是不定义宽度，单位像素
 * @return string 对应的HTML代码
 */
function form_textarea_html($name, $value, $width = FALSE, $height = FALSE) {
	$style = '';

	if ($width !== FALSE) {
		is_numeric($width) and $width .= 'px';
		is_numeric($height) and $height .= 'px';
		$style = " style=\"width: $width; height: $height; \"";
	}

	$s = '<textarea name="' . $name . '" id="' . preg_replace('/\//', '_', $name) . '" class=\"form-control" ' . $style . '>' . $value . '</textarea>';

	$s .= "<script>tinymce.init({selector: '#" . preg_replace('/\//', '_', $name) . "',menubar:false,toolbar: 'formatselect bold italic strikethrough link bullist numlist blockquote aligncenter alignright | superscript subscript table hr charmap | undo redo | code fullscreen',plugins: ['code','table','hr','fullscreen','lists','link'],setup:function(editor){editor.on('keyup',function(e){lastKeyupTime=e.timeStamp;setTimeout(function(){if(lastKeyupTime-e.timeStamp==0){editor.save()}},1000)})}});</script>";
	return $s;
}



/**
 * 可增减数量的文本输入框
 * 
 * Incrementable 表示可以增加或增减的
 *
 * @param string $name 设置项的name
 * @param array $values 已有的输入值，一维顺序数组
 * @param array $columns_label 每一列的名字
 * @return string 对应的HTML代码
 */
function form_incrementable_text($name, $columns_label = array(), $values = array()) {
	$s = '<table class="table" id="' . $name . '_incrementable_table">';
	$columns_label[] = lang('action');
	$s .= '<thead><tr>';
	foreach ($columns_label as $label) {
		$s .= '<th>' . $label . '</th>';
		
	}
	$s .= '</tr></thead>';
	$s .= '<tbody>';
	foreach ($values as $value) {
		$s .= '<tr>';
		$s .= '<td>' . form_text($name . '[]',$value) . '</td>';
		$s .= '<td><button type="button" class="btn btn-success btn-small add_row">+</button><button type="button" class="btn btn-success btn-danger remove_row">-</button></td>';

		$s .= '</tr>';
	}
	$s .= '<template class="teble_row_template"><tr>';
	$s .= '<td>' . form_text($name . '[]', '') . '</td>';
	$s .= '<td><button type="button" class="btn btn-success btn-small add_row">+</button><button type="button" class="btn btn-success btn-danger remove_row">-</button></td>';

	$s .= '</tr></template>';
	$s .= '</table>';

	$s .= "<script>
if (typeof addRow !== 'function') {
	function addRow(table, current_row) {
		const new_row = table.querySelector('template.teble_row_template').content.cloneNode(true);
		table.querySelector('tbody').insertBefore(new_row, current_row.nextElementSibling);
		bindPlusButtons(table);
		bindMinusButtons(table);
	};
}
if (typeof removeRow !== 'function') {
	function removeRow(table, row) {
		row.remove();
	};
}
if (typeof bindPlusButtons !== 'function') {
	function bindPlusButtons(table) {
		const plusButtons = table.querySelectorAll('button.add_row');
		plusButtons.forEach(button => {
			button.onclick = function () { addRow(table, button.parentNode.parentNode) }
		})
	};
}
if (typeof bindMinusButtons !== 'function') {
	function bindMinusButtons(table) {
		const minusButtons = table.querySelectorAll('button.remove_row');
		minusButtons.forEach(button => {
			button.onclick = function () { removeRow(table, button.parentNode.parentNode) }
		});
	}
}

bindPlusButtons(document.querySelector('#" . str_replace('/','\\\\/',$name) . "_incrementable_table'));
bindMinusButtons(document.querySelector('#" . str_replace('/','\\\\/',$name) . "_incrementable_table'));</script>";

	return $s;
}

/**
 * 可增减行数的矩阵文本输入框
 * 
 * Incrementable 表示可以增加或增减的
 *
 * @param string $name 设置项的name
 * @param int $columns_label 每一列的名字
 * @param array $values 已有的输入值，二维数组
 * @return string 对应的HTML代码
 */
function form_incrementable_text_matrix($name, $columns_label = array(), $values = array()) {
	$s = '<table class="table" id="' . $name . '_incrementable_table">';
	$columns_label[] = lang('action');
	$s .= '<thead><tr>';
	foreach ($columns_label as $label) {
		$s .= '<th>' . $label . '</th>';
		
	}
	$s .= '</tr></thead>';
	$s .= '<tbody>';
	$columns = count($columns_label);
	foreach ($values as $row) {
		$s .= '<tr>';
		for ($i=0; $i < ($columns - 1); $i++) { 
			$s .= '<td>' . form_text($name . '[]', $row[$i]) . '</td>';
		}
		$s .= '<td><button type="button" class="btn btn-success btn-small add_row">+</button><button type="button" class="btn btn-success btn-danger remove_row">-</button></td>';

		$s .= '</tr>';
	}
	$s .= '<template class="teble_row_template"><tr>';
	for ($i=0; $i < ($columns - 1); $i++) { 
		$s .= '<td>' . form_text($name . '[]', '') . '</td>';
	}
	$s .= '<td><button type="button" class="btn btn-success btn-small add_row">+</button><button type="button" class="btn btn-success btn-danger remove_row">-</button></td>';

	$s .= '</tr></template>';
	$s .= '</table>';

	$s .= "<script>
if (typeof addRow !== 'function') {
	function addRow(table, current_row) {
		const new_row = table.querySelector('template.teble_row_template').content.cloneNode(true);
		table.querySelector('tbody').insertBefore(new_row, current_row.nextElementSibling);
		bindPlusButtons(table);
		bindMinusButtons(table);
	};
}
if (typeof removeRow !== 'function') {
	function removeRow(table, row) {
		row.remove();
	};
}
if (typeof bindPlusButtons !== 'function') {
	function bindPlusButtons(table) {
		const plusButtons = table.querySelectorAll('button.add_row');
		plusButtons.forEach(button => {
			button.onclick = function () { addRow(table, button.parentNode.parentNode) }
		})
	};
}
if (typeof bindMinusButtons !== 'function') {
	function bindMinusButtons(table) {
		const minusButtons = table.querySelectorAll('button.remove_row');
		minusButtons.forEach(button => {
			button.onclick = function () { removeRow(table, button.parentNode.parentNode) }
		});
	}
}

bindPlusButtons(document.querySelector('#" . str_replace('/','\\\\/',$name) . "_incrementable_table'));
bindMinusButtons(document.querySelector('#" . str_replace('/','\\\\/',$name) . "_incrementable_table'));</script>";

	return $s;
}
/* 【结束】form_func 扩展 */

/* 【开始】金桔框架专用控件 */
/**
 * 排版框（字体、字号、字重、字体风格（普通/斜体）、行间距）
 * @param string $name 设置项的name
 * @param array $value 期待值如下
 * $value = array( 
 *     'font-style' => 'normal', 
 *     'font-weight' => 400, 
 *     'font-size' => '16', 
 *     'font-size_unit' => 'px',
 *     'line-height' => '1.5', 
 *     'font-family' => 'monospace'
 * )；
 * @param array $args 参数,默认值详见$args_default
 * @return string 对应的HTML代码
 */
function form_css_typography($name, $value = array(), $args = array()) {
	$s = '<section class="form-group row kumquat-typography">'; //输出结果
	//var_dump($value);
	$args_default = array(
		'font-style' => array(
			'normal' => lang("font-style_normal"),
			'italic' => lang("font-style_italic")
		), //字体样式 下拉框
		'font-weight' => array(
			100 => lang("font-weight_100"),
			200 => lang("font-weight_200"),
			300 => lang("font-weight_300"),
			400 => lang("font-weight_400"),
			500 => lang("font-weight_500"),
			600 => lang("font-weight_600"),
			700 => lang("font-weight_700"),
			800 => lang("font-weight_800"),
			900 => lang("font-weight_900")
		), //字重 下拉框
		'font-size' => array('0', '200'), //字号 数字框；分别为最小值和最大值；单位见下一条
		'font-size_unit' => array('px' => 'px', 'em' => 'em', 'rem' => 'rem', '%' => lang('Percentage')), //字号单位
		'line-height' => array('0', '4'), //行间距 数字框；分别为最小值和最大值；单位为em
		'font-family' => array(
			"serif"      => lang("font-family_serif"),
			"sans-serif" => lang("font-family_sans-serif"),
			"monospace"  => lang("font-family_monospace"),
			"cursive"    => lang("font-family_cursive"),
			"fantasy"    => lang("font-family_fantasy"),
			"fangsong"   => lang("font-family_fangsong"),
			"system-ui"  => lang("font-family_system-ui"),
			"var(--font-family-sans-serif)" => lang("Custom Font"),
		), //字体 下拉框；键为选项ID，值[0]为显示名，值[1]为具体值
		'content_demo' =>  lang("typography_demo") //预览文字
	);

	$value_default = array(
		'font-style' => 'normal',
		'font-weight' => 400,
		'font-size' => '1',
		'font-size_unit' => 'em',
		'line-height' => '1.5',
		'font-family' => 'sans-serif'
	);

	if (!isset($value['font-size_unit'])) {
		$value = array_merge($value, $value_default);
	}

	if (!isset($args['font-family'][$value['font-family']])) {
		$value['font-family'] = 'sans-serif';
	}

	//var_dump($args);
	if (isset($args['args'])) {
		$args = array_merge_recursive($args, $args_default);
	} else {
		$args = $args_default;
	}

	foreach ($args['font-weight'] as $fw_key => $fw_value) {
		$args['font-weight'][$fw_key] = $fw_key . ' ' . $fw_value;
	}
	$typography_demo_id = str_replace('/', '_', $name) . '_demo';

	$s .= '<div id="' . $typography_demo_id . '" class="card-body col-12 content-demo" style="font:' . kumquat_css_font_assemble($value) . '">';
	$s .= $args['content_demo'];
	$s .= '</div>';

	$s .= '<div class="form-group col-12 col-md-4 kumquat-font-family">';
	$s .= kumquat_control_label(lang('Font Family'), 'span');
	$s .= form_select($name . '[font-family]', $args['font-family'], $value['font-family']);
	$s .= '</div>';

	$s .= '<div class="form-group col-6 col-md-2 kumquat-font-style">';
	$s .= kumquat_control_label(lang('Font Style'), 'span');
	$s .= form_select($name . '[font-style]', $args['font-style'], $value['font-style']);
	$s .= '</div>';

	$s .= '<div class="form-group col-6 col-md-2 kumquat-font-weight">';
	$s .= kumquat_control_label(lang('Font Weight'), 'span');
	$s .= form_select($name . '[font-weight]', $args['font-weight'], $value['font-weight']);
	$s .= kumquat_control_label(lang('Not all fonts support all Font Weight'), 'small');
	$s .= '</div>';

	$s .= '<div class="form-group col-6 col-md-2 kumquat-font-size">';
	$s .= kumquat_control_label(lang('Font Size'), 'span');
	$s .= '<div class="input-group">';
	$s .= form_number($name . '[font-size]', $value['font-size'], false, $args['font-size'][0], $args['font-size'][1], 0.01);
	$s .= form_select($name . '[font-size_unit]', $args['font-size_unit'], $value['font-size_unit']);
	$s .= '</div>';
	$s .= '</div>';

	$s .= '<div class="form-group col-6 col-md-2 kumquat-line-height">';
	$s .= kumquat_control_label(lang('Line Height'), 'span');
	$s .= form_number($name . '[line-height]', $value['line-height'], false, $args['line-height'][0], $args['line-height'][1], 0.01);
	$s .= '</div>';

	$s .= '<script>';
	$s .= "document.getElementById('" . $name . "[font-family]').setAttribute('onchange','document.getElementById(\"" . $typography_demo_id . "\").style.fontFamily = this.value');";
	$s .= "document.getElementById('" . $name . "[font-style]').setAttribute('onchange','document.getElementById(\"" . $typography_demo_id . "\").style.fontStyle = this.value');";
	$s .= "document.getElementById('" . $name . "[font-weight]').setAttribute('onchange','document.getElementById(\"" . $typography_demo_id . "\").style.fontWeight = this.value');";
	$s .= "document.getElementById('" . $name . "[font-size]').setAttribute('onchange','document.getElementById(\"" . $typography_demo_id . "\").style.fontSize = this.value+document.getElementById(\"" . $name . "[font-size_unit]\").value');";
	$s .= "document.getElementById('" . $name . "[font-size_unit]').setAttribute('onchange','document.getElementById(\"" . $typography_demo_id . "\").style.fontSize = document.getElementById(\"" . $name . "[font-size]\").value+this.value');";
	$s .= "document.getElementById('" . $name . "[line-height]').setAttribute('onchange','document.getElementById(\"" . $typography_demo_id . "\").style.lineHeight = this.value');";
	$s .= '</script>';

	$s .= "</section>";
	return $s;
}
/*
font-family_recommend => array(
	array("Arial","Arial, Helvetica, sans-serif"),
	array("Arial Black","'Arial Black', Gadget, sans-serif"),
	array("Bookman Old Style","'Bookman Old Style', serif"),
	array("Comic Sans","'Comic Sans MS', cursive"),
	array("Courier","Courier, monospace"),
	array("Garamond","Garamond, serif"),
	array("Georgia","Georgia, serif"),
	array("Impact","Impact, Charcoal, sans-serif"),
	array("Lucida Console","'Lucida Console', Monaco, monospace"),
	array("Lucida Sans","'Lucida Sans Unicode', 'Lucida Grande', sans-serif"),
	array("Geneva","'MS Sans Serif', Geneva, sans-serif"),
	array("New York","'MS Serif', 'New York', sans-serif"),
	array("Palatino Linotype","'Palatino Linotype', 'Book Antiqua', Palatino, serif"),
	array("Tahoma","Tahoma, Geneva, sans-serif"),
	array("Times New Roman","'Times New Roman', Times, serif"),
	array("Trebuchet MS","'Trebuchet MS', Helvetica, sans-serif"),
	array("Verdana","Verdana, Geneva, sans-serif)
)
*/

/**
 * CSS背景图（网址、位置、大小、重复、固定方式）
 * @param $name 设置项的name
 * @param array $value 期待值如下
 * $value = array( 
 *     'background-url' => "https://via.placeholder.com/150",
 *     'background-repeat' => 'repeat',
 *     'background-position' => 'center',
 *     'background-size' => 'auto',
 *     'background-attachment' => 'initial',
 * )；
 * @param array $args 参数,默认值详见$args_default
 * @return string 对应的HTML代码
 */
function form_css_background_image($name, $value = array(), $args = array()) {
	$s = '<section class="form-group row kumquat-bgimage">'; //输出结果



	$args_default = array(
		'background-repeat' => array(
			'repeat'    => lang('background-repeat_repeat'),
			'repeat-x'  => lang('background-repeat_repeat-x'),
			'repeat-y'  => lang('background-repeat_repeat-y'),
			'no-repeat' => lang('background-repeat_no-repeat'),
		),
		'background-position' => array(
			'left top'     => lang('background-position_left_top'),
			'left'         => lang('background-position_left_center'),
			'left bottom'  => lang('background-position_left_bottom'),
			'top'          => lang('background-position_center_top'),
			'center'       => lang('background-position_center_center'),
			'bottom'       => lang('background-position_center_bottom'),
			'right top'    => lang('background-position_right_top'),
			'right'        => lang('background-position_right_center'),
			'right bottom' => lang('background-position_right_bottom'),
		),
		'background-size' => array(
			'auto'    => lang('background-size_auto'),
			'100%'    => lang('background-size_100'),
			'cover'   => lang('background-size_cover'),
			'contain' => lang('background-size_contain'),
		),
		'background-attachment' => array(
			'initial' => lang('background-attachment_initial'),
			'fixed' =>   lang('background-attachment_fixed'),
		)
	);
	$value_default = array(
		'background-repeat' => 'repeat',
		'background-position' => 'center',
		'background-size' => 'auto',
		'background-attachment' => 'initial',
	);

	if (empty($value) || !isset($value['background-url'])) {
		$value = array();
		$value['background-url'] = "https://via.placeholder.com/150";
		$value = array_merge_recursive($value, $value_default);
	}
	//var_dump($value);
	$args = array_merge_recursive($args, $args_default);

	$bgimage_demo_id = str_replace('/', '_', $name) . '_demo';
	$s .= '<div id="' . $bgimage_demo_id . '" class="content-demo bgimage_demo" style="background:' . kumquat_css_background_image_assemble($value) . '">' . lang('Preview') . '</div>';

	if (isset($args['args']['choices'])) {
		$s .= '<div class="form-group col-12 col-md-6 kumquat-background-presets">';
		$s .= kumquat_control_label(lang('Select an image'), 'span');
		//$s .= form_select($name . '_presets', $args['args']['choices']);
		/* ↓form_select */
		$s .= '<div class="form-group"><select name="' . $name . '" class="form-select form-control flex-grow-1 kumquat-background-presets-select" id="' . $name . '">';
		foreach ($args['args']['choices'] as $k => $v) {
			$s .= '<option value="' . $k . '" data-value="' . $v['href'] . '">' . $v['label'] . '</option> ';
		}
		$s .= '</select></div>';

		$s .= '</div>';
		$s .= '<div class="form-group col-12 col-md-6 kumquat-background-url">';
		$s .= kumquat_control_label(lang('Background Image URL'), 'span');
		$s .= form_text($name . '[background-url]', $value['background-url'], false, 'https://');
		$s .= '</div>';
	} else {
		$s .= '<div class="form-group col-12 kumquat-background-url">';
		$s .= kumquat_control_label(lang('Background Image URL'), 'span');
		$s .= form_text($name . '[background-url]', $value['background-url'], false, 'https://');
		$s .= '</div>';
	}

	$s .= '<div class="form-group col-6 col-md-3 kumquat-background-position">';
	$s .= kumquat_control_label(lang('Background Position'), 'span');
	$s .= form_select($name . '[background-position]', $args['background-position'], $value['background-position']);
	$s .= '</div>';

	$s .= '<div class="form-group col-6 col-md-3 kumquat-background-size">';
	$s .= kumquat_control_label(lang('Background Size'), 'span');
	$s .= form_select($name . '[background-size]', $args['background-size'], $value['background-size']);
	$s .= '</div>';

	$s .= '<div class="form-group col-6 col-md-3 kumquat-background-repeat">';
	$s .= kumquat_control_label(lang('Background Repeat'), 'span');
	$s .= form_select($name . '[background-repeat]', $args['background-repeat'], $value['background-repeat']);
	$s .= '</div>';

	$s .= '<div class="form-group col-6 col-md-3 kumquat-background-attachment">';
	$s .= kumquat_control_label(lang('Background Attachment'), 'span');
	$s .= form_select($name . '[background-attachment]', $args['background-attachment'], $value['background-attachment']);
	$s .= '</div>';

	$s .= '<script>';
	$s .= "document.getElementById('" . $name . "[background-url]').setAttribute('onchange','document.getElementById(\"" . $bgimage_demo_id . "\").style.backgroundImage = \"url(\"+this.value+\")\"');";
	$s .= "document.getElementById('" . $name . "[background-position]').setAttribute('onchange','document.getElementById(\"" . $bgimage_demo_id . "\").style.backgroundPosition = this.value');";
	$s .= "document.getElementById('" . $name . "[background-size]').setAttribute('onchange','document.getElementById(\"" . $bgimage_demo_id . "\").style.backgroundSize = this.value');";
	$s .= "document.getElementById('" . $name . "[background-repeat]').setAttribute('onchange','document.getElementById(\"" . $bgimage_demo_id . "\").style.backgroundRepeat = this.value');";
	$s .= "document.getElementById('" . $name . "[background-attachment]').setAttribute('onchange','document.getElementById(\"" . $bgimage_demo_id . "\").style.backgroundAttachment = this.value');";

	$s .= '</script>';

	$s .= "</section>";
	return $s;
}

/* 【结束】金桔框架专用控件 */

/* 【开始】Xiuno BBS专用控件 */

/**
 * 论坛板块选择——多选
 * @param string $name 设置项的name
 * @param array $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_forumlist_multi($name, $checked = array()) {
	$the_forumlist = array();
	$cache_forumlist = forum_list_cache();

	foreach ($cache_forumlist as $_forum) {
		$the_forumlist[$_forum['fid']] = $_forum['name'];
	}

	/* ↓form_multi_checkbox */
	$s = '<ol class="list-unstyled">';
	foreach ($the_forumlist as $value => $text) {
		$ischecked = in_array($value, $checked);
		$add = $ischecked ? ' checked="checked"' : '';
		$s .= '<li><label class="custom-input custom-checkbox form-check"><input type="checkbox" name="' . $name . '[' . $value . ']' . '" value="' . $value . '"' . $add . ' class="form-check-input" /> ' . $text . '</label></li>';
	}
	$s .= '</ol>';
	unset($cache_forumlist);
	return $s;
}

/**
 * 论坛板块选择——单选
 * @param string $name 设置项的name
 * @param mixed $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_forumlist_single($name, $checked = 0) {
	$the_forumlist = array(
		0 => lang('unset')
	);
	$cache_forumlist = forum_list_cache();

	foreach ($cache_forumlist as &$_forum) {
		$the_forumlist[$_forum['fid']] = $_forum['name'];
	}

	$s = '<ol class="list-unstyled">';
	foreach ($the_forumlist as $value => $text) {
		$add = $value == $checked ? ' checked="checked"' : '';
		$s .= '<li><label class="custom-input custom-radio form-check"><input type="radio" name="' . $name . '" value="' . $value . '"' . $add . ' class="form-check-input" /> ' . $text . '</label></li>';
	}
	$s .= '</ol>';
	unset($cache_forumlist);
	return $s;
}

/**
 * 每个论坛板块使用一种文本输入框
 * @param string $name 设置项的name
 * @param array $arr 选项数组，使用“键-值”模式：键是value，值是对外显示的标签
 * @param array $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_text_per_forum($name, $values = array()) {
	$the_forumlist = array();
	$cache_forumlist = forum_list_cache();
	foreach ($cache_forumlist as $_forum) {
		$the_forumlist[$_forum['fid']] = $_forum['name'];
	}

	$s = '<ol class="list-unstyled">';
	foreach ($the_forumlist as $value => $text) {
		$s .= '<li><div class="input-group">';
		$s .= '<label class="input-group-prepend input-group-text" for="' . $name . '[' . $value . ']">' . $text . '</label>';
		$s .= form_text($name . '[' . $value . ']', isset($values[$value]) ? $values[$value] : '', FALSE, '');
		$s .= '</div></li>';
	}
	$s .= '</ol>';

	unset($cache_forumlist);
	return $s;
}

/**
 * 每个论坛板块使用一种数字输入框
 * @param string $name 设置项的name
 * @param array $arr 选项数组，使用“键-值”模式：键是value，值是对外显示的标签
 * @param int $width 数字框的宽度（最好别用），false是不定义宽度，单位像素
 * @param int|float $min 数字框的最小值（左侧数值）
 * @param int|float $max 数字框的最大值（右侧数值）
 * @param int|float $step 数字框的数字间隔（一格增减多少）（如果 step="3"，则合法数字是 -3,0,3,6，以此类推）
 * @param string $placeholder 若未填写文字时的占位文字
 * @return string 对应的HTML代码
 */
function form_number_per_forum( $name, $values  = array(), $width = FALSE, $min = 0, $max = 100, $step = 1, $placeholder = '') {
	$the_forumlist = array();
	$cache_forumlist = forum_list_cache();
	foreach ($cache_forumlist as $_forum) {
		$the_forumlist[$_forum['fid']] = $_forum['name'];
	}

	$s = '<ol class="list-unstyled">';
	foreach ($the_forumlist as $value => $text) {
		$s .= '<li><div class="input-group">';
		$s .= '<label class="input-group-prepend input-group-text" for="' . $name . '[' . $value . ']">' . $text . '</label>';
		$s .= form_number($name . '[' . $value . ']', isset($values[$value]) ? $values[$value] : $min, $width, $min, $max, $step, $placeholder);
		$s .= '</div></li>';
	}
	$s .= '</ol>';

	unset($cache_forumlist);
	return $s;
}

/**
 * 每个论坛板块使用一种下拉框
 * @param string $name 设置项的name
 * @param array $arr 选项数组，使用“键-值”模式：键是value，值是对外显示的标签
 * @param array $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_dropdown_per_forum($name, $arr, $checked = array()) {
	$the_forumlist = array();
	$cache_forumlist = forum_list_cache();
	empty($arr) && $arr = array(lang('no'), lang('yes'));
	foreach ($cache_forumlist as $_forum) {
		$the_forumlist[$_forum['fid']] = $_forum['name'];
	}

	$s = '<ol class="list-unstyled">';
	foreach ($the_forumlist as $value => $text) {
		$s .= '<li><div class="input-group">';
		$s .= '<label class="input-group-prepend input-group-text" for="' . $name . '[' . $value . ']">' . $text . '</label>';
		$s .= '<select class="form-select flex-grow-1" name="' . $name . '[' . $value . ']">';
		foreach ($arr as $option_value => $option_name) {
			$ischecked = (isset($checked[$value]) && $option_value == $checked[$value]) ? true : false;
			$add = $ischecked ? ' selected="selected"' : '';
			$s .= '<option value="' . $option_value . '" ' . $add . '>' . $option_name . '</option>';
		}
		$s .= '</select></div></li>';
	}
	$s .= '</ol>';

	unset($cache_forumlist);
	return $s;
}

/**
 * 论坛板块选择——下拉框(单选)
 * @param  string $name 设置项的name
 * @param mixed $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_forumlist_dropdown($name, $checked = 0) {
	$the_forumlist = array(
		0 => lang('unset')
	);
	$cache_forumlist = forum_list_cache();

	foreach ($cache_forumlist as &$_forum) {
		$the_forumlist[$_forum['fid']] = $_forum['name'];
	}

	$s = '<div class="input-group"><select class="form-select form-control flex-grow-1" name="' . $name . '">';
	foreach ($the_forumlist as $value => $text) {
		$add = ($value == $checked) ? ' checked="checked"' : '';
		$s .= '<option value="' . $value . '" ' . $add . '>' . $text . '</option>';
	}
	$s .= '</select></div>';
	unset($cache_forumlist);
	return $s;
}

/**
 * 用户组选择——多选
 * @param $name 设置项的name
 * @param array $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_usergroup_multi($name, $checked = array()) {

	$the_usergroup = array();
	$cache_usergroup = group_list_cache();
	foreach ($cache_usergroup as $_usergroup) {
		$the_usergroup[$_usergroup['gid']] = $_usergroup['name'];
	}
	/* ↓form_multi_checkbox */
	$s = '<ol class="list-unstyled">';
	foreach ($the_usergroup as $value => $text) {
		$ischecked = in_array($value, $checked);
		$add = $ischecked ? ' checked="checked"' : '';
		$s .= '<li><label class="custom-input custom-checkbox form-check"><input type="checkbox" name="' . $name . '[' . $value . ']' . '" value="' . $value . '"' . $add . ' class="form-check-input" /> ' . $text . '</label></li>';
	}
	$s .= '</ol>';
	unset($cache_usergroup);
	return $s;
}

/**
 * 用户组选择——单选
 * @param $name 设置项的name
 * @param mixed $checked 预先选中的选项
 * @return string 对应的HTML代码
 */
function form_usergroup_single($name, $checked = 0) {


	$the_usergroup = array();
	$cache_usergroup = group_list_cache();
	foreach ($cache_usergroup as $_usergroup) {
		$the_usergroup[$_usergroup['gid']] = $_usergroup['name'];
	}
	$s = '<ol class="list-unstyled">';
	foreach ($the_usergroup as $value => $text) {
		$add = $value == $checked ? ' checked="checked"' : '';
		$s .= '<li><label class="custom-input custom-radio form-check"><input type="radio" name="' . $name . '" value="' . $value . '"' . $add . ' class="form-check-input" /> ' . $text . '</label></li>';
	}
	$s .= '</ol>';
	unset($cache_usergroup);
	return $s;
}

/* 【结束】Xiuno BBS专用控件 */