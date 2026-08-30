<?php

// Portable public plugin directory identifier shared by Web/runtime and CLI tooling.
// Keep this helper dependency-free: CLI scaffolding loads it before application bootstrap.
function xn_plugin_dir_is_valid($dir) {
	return is_string($dir) && preg_match('~^[A-Za-z0-9_][A-Za-z0-9_-]{0,63}$~D', $dir);
}

?>
