<?php

App::uses('AppShell', 'Console/Command');
App::uses('File', 'Utility');
App::uses('Folder', 'Utility');

/**
 * Create JS files from PO
 *
 * @package       I18nJs.Console.Command.Task
 */
class CreateJsTask extends AppShell {

	public function execute() {

		// I18nProfile class: http://stackoverflow.com/questions/8433686/is-there-a-php-library-for-parsing-gettext-po-pot-files
		App::import('Lib', 'I18nJs.PoParser');

		$dir = new Folder(APP . 'Locale');
		$locales = $dir->read();

		foreach ($locales[0] as $localeDir) {
			$msgids = array();
			$language = $localeDir;
			$localeDir = new Folder(APP . 'Locale' . DS . $localeDir);
			$files = $localeDir->findRecursive('i18n_js.*\.po');

			// Loop over PO i18n_js PO files
			foreach ($files as $file) {
				$file = new File($file);

				// Get language
				if (preg_match('%Locale/(.*?)/LC_MESSAGES%', $file->path, $regs)) {
					$language = $regs[1];
				}
				else {
					// todo return
					$this->out(__d('i18n_js', '<error>Unable to determine language of PO file:</error>') . $file->path);
					return;
				}

				// Get context, domain
				$context = '';
				if (strpos($file->name(), '.')) {
					$context = explode('.', $file->name());
					$context = $context[1];
				}

				// Parse PO file
				$poparser = new \Sepia\PoParser();
				$translations = $poparser->parse($file->path);
				foreach ($translations as $key => $translation) {
					if (empty($key))
						continue;
					if (is_array($translation['msgid'])) {
						$translation['msgid'] = implode('', $translation['msgid']);
					}
					if (is_array($translation['msgstr'])) {
						$translation['msgstr'] = implode('', $translation['msgstr']);
					}
					$msgids[$context][$translation['msgid']] = $translation['msgstr'];
				}

			}
			if (empty($msgids)) continue;

			// Write JS file
			$outputFile = new File(WWW_ROOT . 'js' . DS . 'Locale' . DS . 'i18n_js.' . $language . '.js', true);
			$data = "I18nJs.locale = { ";
			$data .= "'pluralFormula': function (\$n) { return Number((\$n != 1)); }, ";
			$data .= "'strings': " . json_encode($msgids, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . " };";
			if ($outputFile->write($data)) {
				$this->out(__d('i18n_js', '<info>%s created</info>', $outputFile->path));
			}
			else {
				$this->out(__d('i18n_js', '<error>Unable to write: %s</error>', $outputFile->path));
			}
		}
	}

}
