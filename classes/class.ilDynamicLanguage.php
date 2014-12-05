<?php

/**
 * Class ilDynamicLanguage
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 */
class ilDynamicLanguage {

	const MODE_DEV = 1;
	const MODE_PROD = 2;
	const K_PART = 'part';
	const K_VAR = 'var';
	const GLUE = ';';
	const QUOTING = '"';
	/**
	 * @var int
	 */
	protected $mode = self::MODE_DEV;
	/**
	 * @var string
	 */
	protected $module = '';
	/**
	 * @var array
	 */
	protected $languages = array( 'de', 'en' );
	/**
	 * @var string
	 */
	protected $csv_file = '';
	/**
	 * @var ilDynamicLanguageInterface
	 */
	protected $parent_object = NULL;
	/**
	 * @var bool
	 */
	protected $is_plugin = false;
	/**
	 * @var ilDynamicLanguage
	 */
	protected static $instance;
	/**
	 * @var array
	 */
	protected static $module_cache = array();
	/**
	 * @var array
	 */
	protected static $csv_cache = array();
	/**
	 * @var array
	 */
	protected $csv_keys = array();
	/**
	 * @var array
	 */
	protected static $missing = array();


	/**
	 * @param ilDynamicLanguageInterface $parent_object
	 * @param int                        $mode
	 *
	 * @return mixed
	 */
	public static function getInstance(ilDynamicLanguageInterface $parent_object, $mode = self::MODE_PROD) {
		if (!isset(self::$instance[$mode])) {
			self::$instance[$mode] = new self($parent_object, $mode);
		}

		return self::$instance[$mode];
	}


	/**
	 * @param ilDynamicLanguageInterface $parent_object
	 * @param int                        $mode
	 */
	protected function __construct(ilDynamicLanguageInterface $parent_object, $mode = self::MODE_PROD) {
		$this->mode = $mode;
		$this->parent_object = $parent_object;
		if ($this->parent_object instanceof ilPlugin) {
			$this->is_plugin = true;
		}
		$this->csv_file = $this->parent_object->getCsvPath();
		if ($this->mode == self::MODE_DEV) {
			$this->loadLanguageModule();
			$this->loadCsv();
			$this->writeCsv();
			$this->writeLanguageFiles();
			$this->parent_object->updateLanguages();
		}
	}


	/**
	 * @param $key
	 *
	 * @return mixed
	 */
	public function txt($key) {
		if ($this->mode == self::MODE_PROD) {
			return $this->parent_object->txt($key);
		} else {
			global $ilUser;
			/**
			 * @var $ilUser ilObjUser
			 */

			if (!isset(self::$module_cache[$ilUser->getLanguage()][$this->parent_object->getPrefix() . '_' . $key])) {
				self::$missing[] = $key;
			}

			$csv = self::$csv_cache[$ilUser->getLanguage()][$key];
			if (!$csv) {
				$csv = "[missing]" . $key . " [/missing]";
			}

			return $csv;
		}
	}


	protected function loadCsv() {
		ini_set('auto_detect_line_endings', true);
		$part_index = 0;
		$var_index = 1;
		foreach (file($this->parent_object->getCsvPath()) as $n => $row) {
			$data = str_getcsv($row, self::GLUE);;
			$part = $data[$part_index];
			$var = $data[$var_index];

			if ($part == self::K_PART AND $var == self::K_VAR) {
				continue;
			}
			foreach ($this->languages as $i => $lng) {
				$txt = $data[$i + 2];
				if ($txt) {
					$key = implode('_', array( $part, $var ));
					self::$csv_cache[$lng][$key] = $txt;
					$this->csv_keys[$key] = array( self::K_PART => $part, self::K_VAR => $var );
				}
			}
		}
		ksort($this->csv_keys);
	}


	public function __destruct() {
		if ($this->mode == self::MODE_DEV) {

			$echo = "<script src='http://malsup.github.com/jquery.form.js'></script> ";
			$echo .= "<div id='dyno_lng' style='z-index: 999999; position: absolute; top:0; right: 0; background-color: #F5F5F5;padding: 20px;'>";
			$echo .= "<form id='dyno_lng_form'>";
			foreach (self::$missing as $missed) {
				foreach ($this->languages as $lng) {
					$echo .= $lng . ": " . $missed . "<br>";
				}
			}

			//			$echo .= "<script>$(document).ready(function() {
			//            $('#dyno_lng_form').ajaxForm({url: '" . $this->parent_object->getAjaxLink() . "', type: 'post'});}); </script>";
			//			$echo .= "<input type='submit'>";
			$echo .= "</form></div>";

			echo $echo;
		}
	}


	protected function writeCsv() {
		$lines[] = implode(self::GLUE, array_merge(array( self::K_PART, self::K_VAR ), $this->languages));
		foreach ($this->csv_keys as $key => $parts) {
			$entry = array( $parts[self::K_PART], $parts[self::K_VAR] );
			foreach ($this->languages as $lng) {
				$entry[] = self::QUOTING . self::$csv_cache[$lng][$key] . self::QUOTING;
			}

			$lines[] = implode(self::GLUE, $entry);
		}

		$implode = implode(PHP_EOL, $lines);
		file_put_contents($this->parent_object->getCsvPath(), $implode);
	}


	protected function writeLanguageFiles() {
		foreach ($this->languages as $lng) {
			$lines = array();
			$lines[] = '<!-- language file start -->';
			foreach (self::$csv_cache[$lng] as $key => $value) {
				$lines[] = implode('#:#', array( $key, $value ));
			}
			file_put_contents(dirname($this->parent_object->getCsvPath()) . '/ilias_' . $lng . '.lang', implode(PHP_EOL, $lines));
		}
	}


	protected function loadLanguageModule() {
		global $ilDB;
		/**
		 * @var $ilDB ilDB
		 */
		$set = $ilDB->query('SELECT * FROM lng_data WHERE module = ' . $ilDB->quote($this->parent_object->getPrefix()));
		while ($rec = $ilDB->fetchObject($set)) {
			self::$module_cache[$rec->lang_key][$rec->identifier] = $rec->value;
		}
	}
}

/**
 * Interface ilDynamicLanguageInterface
 */
interface ilDynamicLanguageInterface {

	/**
	 * @param $key
	 *
	 * @return mixed
	 */
	public function txt($key);


	/**
	 * @return void
	 */
	public function updateLanguages();


	/**
	 * @return string
	 */
	public function getCsvPath();


	/**
	 * @return string
	 */
	public function getPrefix();


	/**
	 * @return string
	 */
	public function getAjaxLink();
}

?>