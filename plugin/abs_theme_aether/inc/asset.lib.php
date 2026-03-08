<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'JShrink.php');
/**
 * 【aether主题】管理CSS和JS文件的类
 * 
 * 借鉴了Flatboard的Asset类，并拓展其功能
 * 
 * 使用调试模式可以让所有资源直通不压缩
 */
class Asset {
	/**  
	 * 缓存目录的真实路径   
	 * @var string  
	 */
	private $cacheDir;

	/**  
	 * 缓存目录的URL路径   
	 * @var string  
	 */
	private $cacheDirUrl;

	/**  
	 * 存储CSS文件路径（会被压缩）   
	 * @var array  
	 */
	private $cssFiles = [];

	/**  
	 * 存储CSS文件路径（直通不会压缩）   
	 * @var array  
	 */
	private $cssFilesDirect = [];

	/**  
	 * 存储CSS代码字符串（会被压缩）   
	 * @var array  
	 */
	private $cssCodes = [];

	/**  
	 * 存储JS文件路径（会被压缩）   
	 * @var array  
	 */
	private $jsFiles = [];

	/**  
	 * 存储JS文件路径（直通不会压缩）   
	 * @var array  
	 */
	private $jsFilesDirect = [];

	/**  
	 * 存储JS代码字符串（会被压缩）   
	 * @var array  
	 */
	private $jsCodes = [];

	/**
	 * 是否要进行压缩？
	 * @var bool 建议设置成true
	 */
	private $do_minify = true;

	/**  
	 * 构造函数，初始化缓存目录及其URL   
	 * @param string $cacheDir 缓存目录的绝对路径   
	 */
	public function __construct($cacheDir) {
		$this->cacheDir = $cacheDir;
		$this->cacheDirUrl = str_replace([APP_PATH, '/', '\\', '\\\\'], ['', DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $cacheDir);
	}

	/**  
	 * 添加一个CSS文件到将被压缩的列表中   
	 * @param string $filePath CSS文件的路径   
	 */
	public function addCssFile($filePath) {
		$this->cssFiles[] = $filePath;
	}

	/**  
	 * 添加一个CSS文件到直通列表（不会被压缩）   
	 * @param string $filePath CSS文件的路径   
	 */
	public function addCssFileDirect($filePath) {
		$this->cssFilesDirect[] = $filePath;
	}

	/**  
	 * 添加CSS代码字符串到将被压缩的列表中   
	 * @param string $code CSS代码字符串   
	 */
	public function addCssCode($code) {
		$this->cssCodes[] = $code;
	}

	/**
	 * 重置CSS文件被压缩的列表
	 */
	public function clearcCssFile() {
		$this->cssFiles = [];
	}

	/**
	 * 重置CSS文件直通列表
	 */
	public function clearCssFileDirect() {
		$this->cssFilesDirect = [];
	}

	/**
	 * 重置CSS代码列表
	 */
	public function clearCssCode() {
		$this->cssCodes = [];
	}

	/**  
	 * 添加一个JS文件到将被压缩的列表中   
	 * @param string $file JS文件的路径   
	 */
	public  function addJsFile($file) {
		$this->jsFiles[] = $file;
	}

	/**  
	 * 添加一个JS文件到直通列表（不会被压缩）   
	 * @param string $filePath JS文件的路径   
	 */
	public function addJsFileDirect($filePath) {
		$this->jsFilesDirect[] = $filePath;
	}

	/**  
	 * 添加JS代码字符串到将被压缩的列表中   
	 * @param string $code JS代码字符串   
	 */
	public  function addJsCode($code) {
		$this->jsCodes[] = $code;
	}

	/**
	 * 清空JS文件被压缩的列表
	 */
	public function clearJsFile() {
		$this->jsFiles = [];
	}

	/**
	 * 清空JS文件直通列表
	 */
	public function clearJsFileDirect() {
		$this->jsFilesDirect = [];
	}

	/**
	 * 清空JS代码列表
	 */
	public function clearJsCode() {
		$this->jsCodes = [];
	}

	/**
	 * 获取当前是否压缩代码
	 * @return bool
	 */
	public function getDoMinify() {
		return $this->do_minify;
	}

	/**
	 * 设置是否压缩代码
	 * 
	 * @param bool $n true是压缩，false是不压缩
	 */
	public function setDoMinify($n) {
		$this->do_minify = boolval($n);
	}

	/**  
	 * 生成压缩与合并的CSS样式表
	 * @param string $destFileName 输出的CSS文件名
	 * @return string HTML link代码，可直接使用
	 */
	public function Stylesheet($destFileName = 'styles.min.css') {
		$cacheFilePath = $this->cacheDirUrl . DIRECTORY_SEPARATOR . $destFileName;
		$result = '';
		if (DEBUG == 2) {
			$temp_total_file_size_uncompressed = 0;

			$result .= '<!-- 👉 Asset DEBUG Stylesheet BEGIN 👈 -->' . PHP_EOL;

			$result .= '<!-- cssFilesDirect -->' . PHP_EOL;
			foreach ($this->cssFilesDirect as $value) {
				$temp_total_file_size_uncompressed += filesize($value);
				$ver_suffix = '?ver=' .date("Ymd_His", filemtime($value));
				$result .= '<link rel="stylesheet" href="' . $value . $ver_suffix . '" data-filesize="' . filesize($value) . '">' . PHP_EOL;
			}
			$result .= '<!-- cssFilesCombined -->' . PHP_EOL;
			foreach ($this->cssFiles as $value) {
				$temp_total_file_size_uncompressed += filesize($value);
				$ver_suffix = '?ver=' .date("Ymd_His", filemtime($value));
				$result .= '<link rel="stylesheet" href="' . $value . $ver_suffix . '" data-filesize="' . filesize($value) . '">' . PHP_EOL;
			}
			$result .= '<!-- cssCodesCombined -->' . PHP_EOL;
			foreach ($this->cssCodes as $value) {
				$temp_total_file_size_uncompressed += strlen($value);
				$result .= '<style data-filesize="' . strlen($value) . '">' . $value . '</style>' . PHP_EOL;
			}

			$compressedCssFiles = $this->compressAndJoinCSSFiles($this->cssFiles);
			$compressedCssCodes = $this->compressAndJoinCSSCodes($this->cssCodes);
			file_put_contents($cacheFilePath, $compressedCssFiles . $compressedCssCodes);
			$temp_compressed_size = filesize($cacheFilePath);
			if ($temp_total_file_size_uncompressed > 0) {
				$temp_compress_rate = (($temp_total_file_size_uncompressed - $temp_compressed_size) / $temp_total_file_size_uncompressed) * 100;
			} else {
				$temp_compress_rate = 0;
			}
			

			$result .= PHP_EOL . '<!-- 👉 Asset DEBUG Stylesheet STATS 👈 '
				. PHP_EOL . 'Total Uncompressed Size (Bytes): ' . $temp_total_file_size_uncompressed
				. PHP_EOL . 'Compressed Size (Bytes): ' . $temp_compressed_size
				. PHP_EOL . '(' . round($temp_compress_rate, 3) . '% smaller)'
				. PHP_EOL . 'Files joined compression: '
				. ((int)count($this->cssFiles) . ' files') . ' + '
				. ((int)count($this->cssCodes) . ' codes') . ' = '
				. ((int)count($this->cssFiles) + (int)$this->cssCodes)
				. PHP_EOL . '-->' . PHP_EOL;
			$result .= '<!-- 👉 Asset DEBUG Stylesheet END 👈 -->' . PHP_EOL;
		} elseif ($this->do_minify) {
			if (!file_exists($cacheFilePath . DIRECTORY_SEPARATOR . $destFileName)) {
				$compressedCssFiles = $this->compressAndJoinCSSFiles($this->cssFiles);
				$compressedCssCodes = $this->compressAndJoinCSSCodes($this->cssCodes);
				file_put_contents($cacheFilePath, $compressedCssFiles . $compressedCssCodes);
			}
			$ver_suffix = '?ver=' .date("Ymd_His", filemtime($cacheFilePath));
			foreach ($this->cssFilesDirect as $value) {
				$result .= '<link rel="stylesheet" href="' . $value . '">';
			}
			$result .= '<link rel="stylesheet" id="aether_css_min" href="' . $cacheFilePath . $ver_suffix . '">';
		} elseif (!$this->do_minify) {
			foreach ($this->cssFilesDirect as $value) {
				$result .= '<link rel="stylesheet" href="' . $value . '" >' . PHP_EOL;
			}
			foreach ($this->cssFiles as $value) {
				$result .= '<link rel="stylesheet" href="' . $value . '" >' . PHP_EOL;
			}
			foreach ($this->cssCodes as $value) {
				$result .= '<style >' . $value . '</style>' . PHP_EOL;
			}
		}
		return $result;
	}

	/**  
	 * 生成压缩与合并的JavaScript代码
	 * @param string $destFileName 输出的JS文件名
	 * @return string HTML script代码，可直接使用
	 */
	public function JavaScript($destFileName = 'scripts.min.js') {
		$cacheFilePath = $this->cacheDirUrl . DIRECTORY_SEPARATOR . $destFileName;
		$result = '';
		if (DEBUG == 2) {
			$temp_total_file_size_uncompressed = 0;
			$result .= '<!-- 👉 Asset DEBUG JavaScript 👈 -->' . PHP_EOL;
			$result .= '<!-- jsFilesDirect -->' . PHP_EOL;
			foreach ($this->jsFilesDirect as $value) {
				$ver_suffix = '?ver=' .date("Ymd_His", filemtime($value));
				$result .= '<script src="' . $value . $ver_suffix . '" data-filesize="' . filesize($value) . '"></script>' . PHP_EOL;
				$temp_total_file_size_uncompressed += filesize($value);
			}
			$result .= '<!-- jsFilesCombined -->' . PHP_EOL;
			foreach ($this->jsFiles as $value) {
				$ver_suffix = '?ver=' .date("Ymd_His", filemtime($value));
				$result .= '<script src="' . $value . $ver_suffix . '" data-filesize="' . filesize($value) . '"></script>' . PHP_EOL;
				$temp_total_file_size_uncompressed += filesize($value);
			}
			$result .= '<!-- jsCodesCombined -->' . PHP_EOL;
			foreach ($this->jsCodes as $value) {
				$result .= '<script data-filesize="' . strlen($value) . '">' . $value . '</script>' . PHP_EOL;
				$temp_total_file_size_uncompressed += strlen($value);
			}

			$compressedJsFiles = $this->compressAndJoinJSFiles($this->jsFiles);
			$compressedJsCodes = $this->compressAndJoinJSCodes($this->jsCodes);
			file_put_contents($cacheFilePath, $compressedJsFiles . $compressedJsCodes);

			$temp_compressed_size = filesize($cacheFilePath);
			if ($temp_total_file_size_uncompressed > 0) {
				$temp_compress_rate = (($temp_total_file_size_uncompressed - $temp_compressed_size) / $temp_total_file_size_uncompressed) * 100;
			} else {
				$temp_compress_rate = 0;
			}
			
			$result .= PHP_EOL . '<!-- 👉 Asset DEBUG JavaScript STATS 👈 '
				. PHP_EOL . 'Total Uncompressed Size (Bytes): ' . $temp_total_file_size_uncompressed
				. PHP_EOL . 'Compressed Size (Bytes): ' . $temp_compressed_size
				. PHP_EOL . '(' . round($temp_compress_rate, 3) . '% smaller)'
				. PHP_EOL . 'Files joined compression: '
				. ((int)count($this->jsFiles) . ' files') . ' + '
				. ((int)count($this->jsCodes) . ' codes') . ' = '
				. ((int)count($this->jsFiles) + (int)$this->jsCodes)
				. PHP_EOL . '-->' . PHP_EOL;
			$result .= '<!-- 👉 Asset DEBUG JavaScript END 👈 -->' . PHP_EOL;
		} elseif ($this->do_minify) {
			if (!file_exists($cacheFilePath . DIRECTORY_SEPARATOR . $destFileName)) {
				$compressedJsFiles = $this->compressAndJoinJSFiles($this->jsFiles);
				$compressedJsCodes = $this->compressAndJoinJSCodes($this->jsCodes);
				file_put_contents($cacheFilePath, $compressedJsFiles . $compressedJsCodes);
			}
			foreach ($this->jsFilesDirect as $value) {
				$result .= '<script src="' . $value . '"></script>';
			}
			$ver_suffix = '?ver=' .date("Ymd_His", filemtime($cacheFilePath));
			$result .= '<script id="aether_js_min" src="' . $cacheFilePath . '"></script>';
		} elseif (!$this->do_minify) {
			foreach ($this->jsFilesDirect as $value) {
				$result .= '<script src="' . $value . '" data-filesize="' . filesize($value) . '"></script>' . PHP_EOL;
			}
			foreach ($this->jsFiles as $value) {
				$result .= '<script src="' . $value . '" data-filesize="' . filesize($value) . '"></script>' . PHP_EOL;
			}
			foreach ($this->jsCodes as $value) {
				$result .= '<script data-filesize="' . strlen($value) . '">' . $value . '</script>' . PHP_EOL;
			}
		}
		return $result;
	}
	/**
	 * 压缩并合并CSS文件
	 * @param array $files 要处理的文件，应为`$this->cssFiles`
	 * @return string
	 */
	private function compressAndJoinCSSFiles($files) {
   		$compressedCss = '@charset "UTF-8";/**'
   			. PHP_EOL  . ' * Aether主题 业务CSS'
   			. PHP_EOL  . ' * 禁止将Xiuno BBS用于搭建诈骗、赌博、色情等违法违规站点'
   			. PHP_EOL  . ' * 本主题仅仅负责外观部分，无法控制展示的内容 任何用户输入和/或展示的内容由用户自行承担风险和责任 本主题的作者不对任何因为使用不当造成的任何直接或间接的损失（包括但不限于数据丢失、停机、业务中断等）负责 '
   			. PHP_EOL  . ' */' . PHP_EOL;
   
   		foreach ($files as $file) {
   			if (file_exists($file)) {
   				$content = file_get_contents($file);
   				$compressedCss .= $this->compressCSS($content) . PHP_EOL;
   			} else {
   				trigger_error("[Asset] File $file not exists. Will skip this file for now." . E_USER_NOTICE);
   				continue;
   			}
   		}
   		return $compressedCss;
   	}
  	

	/**
	 * 压缩并合并CSS代码
	 * @param array $codes 要处理的文件，应为`$this->cssCodes`
	 * @return string
	 */
	private function compressAndJoinCSSCodes($codes) {
		$compressedCss = '/**'
			. PHP_EOL  . ' * Aether主题 业务CSS'
			. PHP_EOL  . ' * 禁止将Xiuno BBS用于搭建诈骗、赌博、色情等违法违规站点'
			. PHP_EOL  . ' * 本主题仅仅负责外观部分，无法控制展示的内容 **任何用户输入和/或展示的内容由用户自行承担风险和责任** 本主题的作者不对任何因为使用不当造成的任何直接或间接的损失（包括但不限于数据丢失、停机、业务中断等）负责 '
			. PHP_EOL  . ' */' . PHP_EOL;

		foreach ($codes as $code) {
			$compressedCss .= $this->compressCSS($code);
		}

		return $compressedCss;
	}

	/**
	 * 压缩并合并JS文件
	 * @param array $files 要处理的文件，应为`$this->jsFiles`
	 * @return string
	 */
	private function compressAndJoinJSFiles($files) {
		$compressedJs = '/**'
			. PHP_EOL  . ' * Aether主题 业务JS'
			. PHP_EOL  . ' * 禁止将Xiuno BBS用于搭建诈骗、赌博、色情等违法违规站点'
			. PHP_EOL  . ' * 本主题仅仅负责外观部分，无法控制展示的内容 **任何用户输入和/或展示的内容由用户自行承担风险和责任** 本主题的作者不对任何因为使用不当造成的任何直接或间接的损失（包括但不限于数据丢失、停机、业务中断等）负责 '
			. PHP_EOL  . ' */' . PHP_EOL;

		foreach ($files as $file) {
			if (file_exists($file)) {
				$content = file_get_contents($file);
				$compressedJs .= $this->compressJS($content) . PHP_EOL;
			} else {
				trigger_error("[Asset] File $file not exists. Will skip this file for now." . E_USER_NOTICE);
				continue;
			}
		}
		return $compressedJs;
	}

	/**
	 * 压缩并合并JS字符串
	 * @param array $codes 要处理的文件，应为`$this->jsCodes`
	 * @return string
	 */
	private function compressAndJoinJSCodes($codes) {
		$compressedJs = '' . PHP_EOL;

		foreach ($codes as $code) {
			$compressedJs .= $this->compressJS($code);
		}

		return $compressedJs;
	}

	/**
	 * 压缩CSS代码
	 * @param string $filedata 代码内容
	 * @return string
	 */
	private function compressCSS($filedata) {
		$filedata = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '   ', '    '), '', $filedata);
		$filedata = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $filedata);
		$filedata = str_replace(
			[' {', '{ ', ' }', '} ', ' ;', '; ', ' ,', ', ', ': ',],
			['{', '{',  '}', '}', ';', ';', ',', ',', ':',],
			$filedata
		);
		return $filedata;
	}

	/**
	 * 压缩JS代码
	 * 
	 * （使用 JShrink）
	 * 
	 * @param string $filedata 代码内容
	 * @return string
	 */
	private function compressJS($filedata) {
		return \JShrink\Minifier::minify($filedata);
	}
}
