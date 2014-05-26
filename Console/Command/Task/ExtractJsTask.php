<?php

App::uses('AppShell', 'Console/Command');
App::uses('File', 'Utility');
App::uses('Folder', 'Utility');
App::uses('ExtractTask', 'Console/Command/Task');

/**
 * Regular expression pattern used to localize JavaScript strings.
 *
 * Drupal 8.
 * @see https://api.drupal.org/api/drupal/core!modules!locale!locale.module/function/_locale_parse_js_file/8
 */
	const LOCALE_JS_STRING = '(?:(?:\'(?:\\\\\'|[^\'])*\'|"(?:\\\\"|[^"])*")(?:\s*\+\s*)?)+';

/**
 * Regular expression pattern used to match simple JS object literal.
 *
 * This pattern matches a basic JS object, but will fail on an object with
 * nested objects. Used in JS file parsing for string arg processing.
 *
 * Drupal 8.
 */
	const LOCALE_JS_OBJECT = '\{.*?\}';

/**
 * Regular expression to match an object containing a key 'context'.
 *
 * Pattern to match a JS object containing a 'context key' with a string value,
 * which is captured. Will fail if there are nested objects.
 *
 * Drupal 8.
 */
define('LOCALE_JS_OBJECT_CONTEXT', '
  \{              # match object literal start
  .*?             # match anything, non-greedy
  (?:             # match a form of "context"
    \'context\'
    |
    "context"
    |
    context
  )
  \s*:\s*         # match key-value separator ":"
  (' . LOCALE_JS_STRING . ')  # match context string
  .*?             # match anything, non-greedy
  \}              # match end of object literal
');

/**
 * Language string extractor
 *
 * @package				I18nJs.Console.Command.Task
 */
class ExtractJsTask extends ExtractTask {

	public function __construct() {
		parent::__construct();
		$this->path = APP::pluginPath('I18nJs');
	}

	public function execute() {
		// Reset core i18n translations
		$this->_translations = array();

		// todo: not only webroot
		$dir = new Folder(APP . 'webroot' . DS . 'js');
		$files = $dir->find('.*\.js');
		foreach ($files as $file) {
			$this->_locale_parse_js_file($dir->pwd() . DS . $file);
		}

		// Create POT file
		$this->_buildFiles();
		$this->_output = APP . 'Locale' . DS;
		$this->_writeFiles();
		$this->out();
		$this->out(__d('i18n_js', '<info>I18nJs POT files created in %s</info>', $this->_output));
	}

	/**
	 * The code in this function has for a great majority been taken from
	 * Drupal 8. See the core locale.module.
	 * -------------------------------------------------------------------
	 *
	 * Parses a JavaScript file, extracts strings wrapped in Drupal.t() and
	 * Drupal.formatPlural() and inserts them into the database.
	 *
	 * @param string $filepath
	 *	 File name to parse.
	 *
	 * @return array
	 *	 Array of string objects to update indexed by context and source.
	 */
	public function _locale_parse_js_file($filepath) {

		$file = file_get_contents($filepath);

		// Match all calls to I18nJs.t() in an array.
		// Note: \s also matches newlines with the 's' modifier.
		preg_match_all('~
		 [^\w]I18nJs\s*\.\s*t\s*											 # match "I18nJs.t" with whitespace
		 \(\s*																				 # match "(" argument list start
		 (' . LOCALE_JS_STRING . ')\s*								 # capture string argument
		 (?:,\s*' . LOCALE_JS_OBJECT . '\s*						 # optionally capture str args
			 (?:,\s*' . LOCALE_JS_OBJECT_CONTEXT . '\s*) # optionally capture context
		 ?)?																					 # close optional args
		 [,\)]																				 # match ")" or "," to finish
		 ~sx', $file, $t_matches);

		// Match all I18nJs.formatPlural() calls in another array.
		preg_match_all('~
			[^\w]I18nJs\s*\.\s*formatPlural\s*  # match "Drupal.formatPlural" with whitespace
			\(                                  # match "(" argument list start
			\s*.+?\s*,\s*                       # match count argument
			(' . LOCALE_JS_STRING . ')\s*,\s*   # match singular string argument
			(                             # capture plural string argument
				(?:                         # non-capturing group to repeat string pieces
					(?:
						\'                      # match start of single-quoted string
						(?:\\\\\'|[^\'])*       # match any character except unescaped single-quote
						@count                  # match "@count"
						(?:\\\\\'|[^\'])*       # match any character except unescaped single-quote
						\'                      # match end of single-quoted string
						|
						"                       # match start of double-quoted string
						(?:\\\\"|[^"])*         # match any character except unescaped double-quote
						@count                  # match "@count"
						(?:\\\\"|[^"])*         # match any character except unescaped double-quote
						"                       # match end of double-quoted string
					)
					(?:\s*\+\s*)?             # match "+" with possible whitespace, for str concat
				)+                          # match multiple because we supports concatenating strs
			)\s*                          # end capturing of plural string argument
			(?:,\s*' . LOCALE_JS_OBJECT . '\s*          # optionally capture string args
				(?:,\s*' . LOCALE_JS_OBJECT_CONTEXT . '\s*)?  # optionally capture context
			)?
			[,\)]
			~sx', $file, $plural_matches);
		//debug($plural_matches);
		$matches = array();

		// Add strings from I18nJs.t().
		foreach ($t_matches[1] as $key => $string) {
			$matches[] = array(
				'string' => $string,
				'context' => $t_matches[2][$key],
			);
		}

		// Add string from I18nJs.formatPlural().
		foreach ($plural_matches[1] as $key => $string) {
			$matches[] = array(
				'string' => $string,
				'context' => $plural_matches[3][$key],
			);

			// If there is also a plural version of this string, add it to the strings array.
			if (isset($plural_matches[2][$key])) {
				$matches[] = array(
					'string' => $plural_matches[2][$key],
					'context' => $plural_matches[3][$key],
				);
			}
		}

		// Loop through all matches and process them.
		foreach ($matches as $key => $match) {

			// Remove the quotes and string concatenations from the string and context.
			$msgid = implode('', preg_split('~(?<!\\\\)[\'"]\s*\+\s*[\'"]~s', substr($match['string'], 1, -1)));
			$context = implode('', preg_split('~(?<!\\\\)[\'"]\s*\+\s*[\'"]~s', substr($match['context'], 1, -1)));

			// Add translations according to the CakePHP I18n module
			$domain = (empty($context)) ? 'i18n_js' : 'i18n_js.' . $context;
			$details = array(
				'file' => $filepath
			);
			$this->_addTranslation('LC_MESSAGES', $domain, $msgid, $details);
		}
	}

}
