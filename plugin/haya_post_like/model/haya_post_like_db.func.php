<?php

function haya_post_like_db_table_exists($table) {
	if (!empty($table)) {
		$data = db_sql_find_one("SHOW TABLES LIKE '{$table}'");
		if (!empty($data)) {
			$data = array_values($data);
			if (in_array($table, $data)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	} else {
		return false;
	}
}	

function haya_post_like_db_field_exists($tablename, $fieldname) {
	$isexists = db_sql_find_one("DESCRIBE " . $tablename . " `{$fieldname}`");
	return !empty($isexists) ? true : false;
}

function haya_post_like_db_index_exists($tablename, $indexname) {
	if (!empty($indexname)) {
		$indexs = db_sql_find("SHOW INDEX FROM " . $tablename);
		if (!empty($indexs) && is_array($indexs)) {
			foreach ($indexs as $row) {
				if ($row['Key_name'] == $indexname) {
					return true;
				}
			}
		}
	}
	return false;
}



?>
